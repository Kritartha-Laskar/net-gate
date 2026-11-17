<?php

namespace App\Services;

use App\Models\Answer;
use App\Models\Chapter;
use App\Models\CourseName;
use App\Models\Question;
use App\Models\QuestionPaper;
use App\Models\Subject;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AiQuestionPaperService
{
    private const MAX_BATCH_SIZE = 12;

    /**
     * Resolve active provider name ("gemini" or "openai").
     */
    public function activeProvider(?string $override = null): string
    {
        $provider = strtolower(trim((string) ($override ?: config('ai.provider', 'gemini'))));

        return in_array($provider, ['gemini', 'openai'], true) ? $provider : 'gemini';
    }

    /**
     * Report configuration readiness for each AI provider.
     */
    public function providerStatus(?string $provider = null): array
    {
        $active = $this->activeProvider($provider);
        $providers = config('ai.providers', []);
        $statuses = [];

        foreach (['gemini', 'openai'] as $key) {
            $cfg = $providers[$key] ?? [];
            $statuses[$key] = [
                'key'        => $key,
                'label'      => $cfg['label'] ?? strtoupper($key),
                'model'      => $cfg['model'] ?? '',
                'configured' => filled($cfg['api_key'] ?? null),
            ];
        }

        return [
            'active'     => $active,
            'configured' => $statuses[$active]['configured'] ?? false,
            'providers'  => $statuses,
        ];
    }

    /**
     * Ensure the CourseName and its standard CSE Subjects & Chapters exist.
     */
    public function ensureExamStructure(string $examKey): CourseName
    {
        $preset = config("ai.exams.{$examKey}");
        if (!$preset) {
            throw new RuntimeException("Unknown exam preset: {$examKey}");
        }

        $course = CourseName::firstOrCreate(
            ['name' => $preset['course_name']],
            ['status' => 'active']
        );

        foreach ($preset['subjects'] as $subjectName => $meta) {
            $subject = Subject::firstOrCreate(
                ['course_id' => $course->id, 'name' => $subjectName]
            );

            foreach (($meta['chapters'] ?? []) as $index => $chapterName) {
                Chapter::firstOrCreate(
                    ['subject_id' => $subject->id, 'name' => $chapterName],
                    [
                        'course_id'      => $course->id,
                        'chapter_number' => (string) ($index + 1),
                        'sort_order'     => $index + 1,
                        'description'    => "{$preset['exam_name']} syllabus topic: {$chapterName}",
                    ]
                );
            }
        }

        return $course;
    }

    /**
     * Build subject-level generation batches (<= 12 questions per API call) for reliable XAMPP execution.
     */
    public function buildGenerationPlan(
        string $examKey,
        string $paperType,
        ?string $subjectName,
        int $totalQuestions
    ): array {
        $preset = config("ai.exams.{$examKey}");
        if (!$preset) {
            throw new RuntimeException("Unknown exam preset: {$examKey}");
        }

        $totalQuestions = max(1, min(150, $totalQuestions));
        $subjects = $preset['subjects'] ?? [];

        if ($paperType === 'mocktest') {
            $chosenSubject = $subjectName ?: array_key_first($subjects);
            return $this->splitIntoBatches($chosenSubject, $totalQuestions);
        }

        // Distribute totalQuestions across syllabus subjects proportionally by weight
        $totalWeight = array_sum(array_map(fn ($s) => (int) ($s['weight'] ?? 10), $subjects));
        $allocations = [];
        $allocated = 0;

        foreach ($subjects as $name => $meta) {
            $weight = (int) ($meta['weight'] ?? 10);
            $count = (int) floor(($totalQuestions * $weight) / max(1, $totalWeight));
            $allocations[$name] = $count;
            $allocated += $count;
        }

        // Distribute any remainder to highest-weight subjects first
        $remainder = $totalQuestions - $allocated;
        $sortedNames = array_keys($subjects);
        usort($sortedNames, fn ($a, $b) => ($subjects[$b]['weight'] ?? 0) <=> ($subjects[$a]['weight'] ?? 0));

        $idx = 0;
        while ($remainder > 0 && count($sortedNames) > 0) {
            $target = $sortedNames[$idx % count($sortedNames)];
            $allocations[$target]++;
            $remainder--;
            $idx++;
        }

        $batches = [];
        foreach ($allocations as $name => $count) {
            if ($count <= 0) {
                continue;
            }
            array_push($batches, ...$this->splitIntoBatches($name, $count));
        }

        return $batches;
    }

    /**
     * Generate a batch of exam-level or PYQ-style questions using the configured AI provider.
     */
    public function generateQuestionBatch(array $params): array
    {
        $examKey = $params['exam_key'];
        $preset = config("ai.exams.{$examKey}");
        if (!$preset) {
            throw new RuntimeException("Unknown exam preset: {$examKey}");
        }

        $provider = $this->activeProvider($params['provider'] ?? null);
        $subjectName = $params['subject_name'];
        $chapterName = $params['chapter_name'] ?? null;
        $count = max(1, min(self::MAX_BATCH_SIZE, (int) ($params['count'] ?? 5)));
        $difficulty = $params['difficulty'] ?? 'exam_level';
        $mode = $params['mode'] ?? 'mocktest_ai';
        $examYear = $params['exam_year'] ?? date('Y');
        $customPrompt = trim((string) ($params['custom_prompt'] ?? ''));
        $avoidSnippets = $params['existing_questions'] ?? [];

        $systemPrompt = $this->buildSystemPrompt($preset['exam_name'], $examKey);
        $userPrompt = $this->buildBatchUserPrompt(
            $preset,
            $examKey,
            $subjectName,
            $chapterName,
            $count,
            $difficulty,
            $mode,
            $examYear,
            $customPrompt,
            $avoidSnippets
        );

        $decoded = $this->callProviderJson($provider, $systemPrompt, $userPrompt);

        return $this->normalizeQuestions($decoded['questions'] ?? [], $subjectName, $preset['default_q_marks'] ?? 2);
    }

    /**
     * Extract structured questions from an uploaded official PYQ PDF or pasted raw text/answer key.
     */
    public function extractFromPdfOrText(array $params): array
    {
        $examKey = $params['exam_key'];
        $preset = config("ai.exams.{$examKey}");
        if (!$preset) {
            throw new RuntimeException("Unknown exam preset: {$examKey}");
        }

        $provider = $this->activeProvider($params['provider'] ?? null);
        /** @var UploadedFile|null $pdfFile */
        $pdfFile = $params['pdf_file'] ?? null;
        $rawText = trim((string) ($params['raw_text'] ?? ''));
        $answerKeyText = trim((string) ($params['answer_key_text'] ?? ''));

        if (!$pdfFile && $rawText === '') {
            throw new RuntimeException('Please upload a PDF file or paste the previous-year paper text.');
        }

        $pdfBase64 = null;
        if ($pdfFile) {
            if ($provider === 'openai') {
                $extracted = $this->tryLocalPdfToText($pdfFile->getRealPath());
                if ($extracted !== '') {
                    $rawText = trim($extracted . "\n\n" . $rawText);
                } else {
                    throw new RuntimeException(
                        'Direct PDF file parsing requires Google Gemini (AI_PROVIDER=gemini) or pdftotext installed. '
                        . 'Switch Provider to Gemini or paste the PDF text into the Raw Text field.'
                    );
                }
            } else {
                $pdfBase64 = base64_encode(file_get_contents($pdfFile->getRealPath()));
            }
        }

        $subjectList = implode(', ', array_keys($preset['subjects'] ?? []));
        $systemPrompt = $this->buildSystemPrompt($preset['exam_name'], $examKey);
        $userPrompt = <<<PROMPT
Extract all Computer Science / exam questions from the provided {$preset['exam_name']} previous-year paper into valid JSON.
Classify each question's "subject" into one of: {$subjectList}.
If the answer key is included below or inside the document, mark the exact correct option(s) with "is_correct": true and provide a concise step-by-step explanation in "reason".
For numerical (NAT) questions without options, put question_type as "descripted" and include the numeric answer as a single option with "is_correct": true.
Format all mathematical notation using LaTeX inline delimiters \\( ... \\).
{$this->formatAnswerKeyBlock($answerKeyText)}
{$this->formatRawTextBlock($rawText)}

Return ONLY JSON matching:
{
  "questions": [
    {
      "subject": "Subject Name",
      "chapter": "Topic Name",
      "question_type": "mcq",
      "marks": 2,
      "question": "Question text...",
      "reason": "Step-by-step solution...",
      "options": [
        { "answer": "Option A", "is_correct": false, "reason": null },
        { "answer": "Option B", "is_correct": true, "reason": "Why B is correct" },
        { "answer": "Option C", "is_correct": false, "reason": null },
        { "answer": "Option D", "is_correct": false, "reason": null }
      ]
    }
  ]
}
PROMPT;

        $decoded = $this->callProviderJson($provider, $systemPrompt, $userPrompt, $pdfBase64);

        return $this->normalizeQuestions(
            $decoded['questions'] ?? [],
            array_key_first($preset['subjects'] ?? []) ?? 'Computer Science',
            $preset['default_q_marks'] ?? 2
        );
    }

    /**
     * Save normalized questions, answers, subjects, chapters, and paper pivot rows atomically.
     */
    public function persistGeneratedQuestions(
        QuestionPaper $paper,
        CourseName $course,
        array $questionsData,
        ?Subject $defaultSubject = null,
        ?Chapter $defaultChapter = null
    ): int {
        if (empty($questionsData)) {
            return 0;
        }

        return DB::transaction(function () use ($paper, $course, $questionsData, $defaultSubject, $defaultChapter) {
            $maxOrder = (int) (DB::table('question_paper_question')
                ->where('question_paper_id', $paper->id)
                ->max('order') ?? 0);

            $addedCount = 0;

            foreach ($questionsData as $item) {
                $subject = $defaultSubject;
                if (!$subject) {
                    $subjectName = trim((string) ($item['subject'] ?? ''));
                    if ($subjectName !== '') {
                        $subject = Subject::where('course_id', $course->id)
                            ->whereRaw('LOWER(name) = ?', [strtolower($subjectName)])
                            ->first()
                            ?: Subject::create([
                                'course_id' => $course->id,
                                'name'      => $subjectName,
                            ]);
                    }
                }

                if (!$subject) {
                    continue;
                }

                $chapter = $defaultChapter;
                if (!$chapter && !empty($item['chapter'])) {
                    $chapter = Chapter::firstOrCreate(
                        ['subject_id' => $subject->id, 'name' => trim((string) $item['chapter'])],
                        ['course_id' => $course->id]
                    );
                }

                $question = Question::create([
                    'subject_id'    => $subject->id,
                    'chapter_id'    => $chapter?->id,
                    'question'      => $item['question'],
                    'question_type' => $item['question_type'],
                    'reason'        => $item['reason'],
                ]);

                foreach ($item['options'] as $opt) {
                    Answer::create([
                        'question_id' => $question->id,
                        'answer'      => $opt['answer'],
                        'is_correct'  => (bool) $opt['is_correct'],
                        'reason'      => $opt['reason'] ?? null,
                    ]);
                }

                $maxOrder++;
                $marks = max(1, min(10, (int) ($item['marks'] ?? 2)));

                DB::table('question_paper_question')->insert([
                    'question_paper_id' => $paper->id,
                    'question_id'       => $question->id,
                    'order'             => $maxOrder,
                    'marks'             => $marks,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]);

                $addedCount++;
            }

            $this->refreshPaperTotals($paper);

            return $addedCount;
        });
    }

    /**
     * Recalculate total_questions, total_marks, and subject_quotas on QuestionPaper from pivot rows.
     */
    public function refreshPaperTotals(QuestionPaper $paper): void
    {
        $rows = DB::table('question_paper_question as qpq')
            ->join('questions as q', 'q.id', '=', 'qpq.question_id')
            ->leftJoin('subjects as s', 's.id', '=', 'q.subject_id')
            ->where('qpq.question_paper_id', $paper->id)
            ->whereNull('q.deleted_at')
            ->selectRaw('COALESCE(s.name, "General") as subject_name, qpq.marks')
            ->get();

        $quotas = [];
        $totalMarks = 0;

        foreach ($rows as $row) {
            $quotas[$row->subject_name] = ($quotas[$row->subject_name] ?? 0) + 1;
            $totalMarks += (int) $row->marks;
        }

        $paper->update([
            'total_questions' => $rows->count(),
            'total_marks'     => $totalMarks,
            'subject_quotas'  => $quotas,
        ]);
    }

    private function splitIntoBatches(string $subjectName, int $count): array
    {
        $batches = [];
        $remaining = $count;

        while ($remaining > 0) {
            $batchCount = min(self::MAX_BATCH_SIZE, $remaining);
            $batches[] = [
                'subject_name' => $subjectName,
                'count'        => $batchCount,
            ];
            $remaining -= $batchCount;
        }

        return $batches;
    }

    private function buildSystemPrompt(string $examName, string $examKey): string
    {
        $styleGuide = $examKey === 'gate_cse'
            ? 'Questions must match authentic IIT GATE Computer Science analytical depth: code tracing, recurrence/complexity analysis, OS/DBMS/CN numericals, cache/pipeline math, and carefully crafted distractor options. Mix 1-mark and 2-mark questions.'
            : 'Questions must match authentic NTA UGC NET Computer Science (Paper 1 / Paper 2) depth: conceptual clarity, assertion-reason, match-the-following style statements, and standard CS definitions/algorithms. All questions carry 2 marks.';

        return "You are an expert Computer Science professor creating exam-level {$examName} questions. "
            . "{$styleGuide} "
            . "Always wrap mathematical formulas in \\( ... \\) LaTeX delimiters. "
            . "Every MCQ must have 4 options with exactly 1 option marked \"is_correct\": true. "
            . "Every MSQ must have 4 options with 1 to 3 options marked \"is_correct\": true. "
            . "Return strictly valid JSON with no markdown fences.";
    }

    private function buildBatchUserPrompt(
        array $preset,
        string $examKey,
        string $subjectName,
        ?string $chapterName,
        int $count,
        string $difficulty,
        string $mode,
        int|string $examYear,
        string $customPrompt,
        array $avoidSnippets
    ): string {
        $chapters = $preset['subjects'][$subjectName]['chapters'] ?? [];
        $chapterHint = $chapterName
            ? "Focus specifically on chapter/topic: {$chapterName}."
            : (!empty($chapters) ? 'Cover topics across: ' . implode(', ', $chapters) . '.' : '');

        $difficultyLabel = match ($difficulty) {
            'foundation' => 'Foundation / Concept Building',
            'hard'       => 'Hard / Challenging Rank-Booster',
            default      => 'Official Exam-Level Standard',
        };

        $modeInstruction = $mode === 'pyq_ai'
            ? "Create {$count} {$preset['exam_name']} {$examYear} previous-year style questions for subject \"{$subjectName}\" matching that exam year's pattern and syllabus."
            : "Create {$count} brand-new {$difficultyLabel} mock test questions for {$preset['exam_name']} in subject \"{$subjectName}\".";

        $extra = $customPrompt !== '' ? "\nAdditional admin instruction: {$customPrompt}" : '';
        $avoid = '';
        if (!empty($avoidSnippets)) {
            $sample = implode("\n- ", array_slice($avoidSnippets, 0, 15));
            $avoid = "\nDo NOT repeat or closely paraphrase any of these existing questions:\n- {$sample}";
        }

        $allowedMarks = $examKey === 'gate_cse' ? '1 or 2' : '2';

        return <<<PROMPT
{$modeInstruction}
{$chapterHint}{$extra}{$avoid}

Return strictly valid JSON using this exact schema:
{
  "questions": [
    {
      "subject": "{$subjectName}",
      "chapter": "Specific topic name",
      "question_type": "mcq",
      "marks": {$allowedMarks},
      "question": "Full clear question text (use \\\\( ... \\\\) for math)",
      "reason": "Clear step-by-step derivation or explanation proving the correct answer",
      "options": [
        { "answer": "Option A text", "is_correct": false, "reason": null },
        { "answer": "Option B text", "is_correct": true, "reason": "Why B is right" },
        { "answer": "Option C text", "is_correct": false, "reason": null },
        { "answer": "Option D text", "is_correct": false, "reason": null }
      ]
    }
  ]
}
PROMPT;
    }

    private function callProviderJson(
        string $provider,
        string $systemPrompt,
        string $userPrompt,
        ?string $pdfBase64 = null
    ): array {
        $cfg = config("ai.providers.{$provider}");
        $apiKey = trim((string) ($cfg['api_key'] ?? ''));

        if ($apiKey === '') {
            $envVar = $provider === 'openai' ? 'OPENAI_API_KEY' : 'GEMINI_API_KEY';
            throw new RuntimeException(
                "No API key configured for {$cfg['label']}. Please set {$envVar} in your .env file and try again."
            );
        }

        $rawContent = $provider === 'openai'
            ? $this->callOpenAi($cfg, $systemPrompt, $userPrompt)
            : $this->callGemini($cfg, $systemPrompt, $userPrompt, $pdfBase64);

        $decoded = $this->decodeJsonPayload($rawContent);
        if (!is_array($decoded) || !isset($decoded['questions']) || !is_array($decoded['questions'])) {
            throw new RuntimeException('AI returned an unexpected format (missing "questions" array). Please retry.');
        }

        return $decoded;
    }

    private function callGemini(array $cfg, string $systemPrompt, string $userPrompt, ?string $pdfBase64): string
    {
        $baseUrl = rtrim((string) $cfg['base_url'], '/');
        $model = $cfg['model'] ?: 'gemini-2.5-flash';
        $url = "{$baseUrl}/models/{$model}:generateContent?key={$cfg['api_key']}";

        $parts = [['text' => $userPrompt]];
        if ($pdfBase64 !== null) {
            $parts[] = [
                'inline_data' => [
                    'mime_type' => 'application/pdf',
                    'data'      => $pdfBase64,
                ],
            ];
        }

        $response = Http::timeout((int) config('ai.timeout', 120))
            ->acceptJson()
            ->post($url, [
                'system_instruction' => [
                    'parts' => [['text' => $systemPrompt]],
                ],
                'contents' => [
                    ['role' => 'user', 'parts' => $parts],
                ],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'temperature'      => 0.4,
                ],
            ]);

        if ($response->failed()) {
            $msg = $response->json('error.message') ?? $response->body();
            throw new RuntimeException("Gemini API error ({$response->status()}): {$msg}");
        }

        return (string) ($response->json('candidates.0.content.parts.0.text') ?? '');
    }

    private function callOpenAi(array $cfg, string $systemPrompt, string $userPrompt): string
    {
        $baseUrl = rtrim((string) $cfg['base_url'], '/');
        $model = $cfg['model'] ?: 'gpt-4o-mini';

        $response = Http::timeout((int) config('ai.timeout', 120))
            ->withToken($cfg['api_key'])
            ->acceptJson()
            ->post("{$baseUrl}/chat/completions", [
                'model'           => $model,
                'temperature'     => 0.4,
                'response_format' => ['type' => 'json_object'],
                'messages'        => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
            ]);

        if ($response->failed()) {
            $msg = $response->json('error.message') ?? $response->body();
            throw new RuntimeException("OpenAI API error ({$response->status()}): {$msg}");
        }

        return (string) ($response->json('choices.0.message.content') ?? '');
    }

    private function decodeJsonPayload(string $text): ?array
    {
        $trimmed = trim($text);
        if (str_starts_with($trimmed, '```')) {
            $trimmed = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $trimmed) ?? $trimmed;
        }

        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $firstBrace = strpos($trimmed, '{');
        $lastBrace = strrpos($trimmed, '}');
        if ($firstBrace !== false && $lastBrace !== false && $lastBrace > $firstBrace) {
            $slice = substr($trimmed, $firstBrace, $lastBrace - $firstBrace + 1);
            $decoded = json_decode($slice, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private function normalizeQuestions(array $rawQuestions, string $fallbackSubject, int $defaultMarks): array
    {
        $normalized = [];

        foreach ($rawQuestions as $item) {
            if (!is_array($item)) {
                continue;
            }

            $qText = trim((string) ($item['question'] ?? ''));
            if ($qText === '') {
                continue;
            }

            $type = strtolower(trim((string) ($item['question_type'] ?? 'mcq')));
            if (!in_array($type, ['mcq', 'msq', 'descripted'], true)) {
                $type = 'mcq';
            }

            $rawOptions = $item['options'] ?? [];
            $options = [];
            $hasCorrect = false;

            foreach ($rawOptions as $opt) {
                if (!is_array($opt)) {
                    continue;
                }
                $ansText = trim((string) ($opt['answer'] ?? ''));
                if ($ansText === '') {
                    continue;
                }
                $isCorrect = !empty($opt['is_correct']);
                if ($isCorrect) {
                    $hasCorrect = true;
                }
                $options[] = [
                    'answer'     => $ansText,
                    'is_correct' => $isCorrect,
                    'reason'     => isset($opt['reason']) ? trim((string) $opt['reason']) : null,
                ];
            }

            if (empty($options) || ($type !== 'descripted' && count($options) < 2)) {
                continue;
            }

            if (!$hasCorrect) {
                $options[0]['is_correct'] = true;
            }

            $marks = (int) ($item['marks'] ?? $defaultMarks);
            if ($marks < 1 || $marks > 10) {
                $marks = $defaultMarks;
            }

            $normalized[] = [
                'subject'       => trim((string) ($item['subject'] ?? $fallbackSubject)) ?: $fallbackSubject,
                'chapter'       => isset($item['chapter']) ? trim((string) $item['chapter']) : null,
                'question_type' => $type,
                'marks'         => $marks,
                'question'      => $qText,
                'reason'        => isset($item['reason']) ? trim((string) $item['reason']) : null,
                'options'       => $options,
            ];
        }

        return $normalized;
    }

    private function tryLocalPdfToText(string $filePath): string
    {
        if (!function_exists('exec')) {
            return '';
        }
        $escaped = escapeshellarg($filePath);
        $output = [];
        $code = 1;
        @exec("pdftotext {$escaped} - 2>NUL", $output, $code);

        return $code === 0 ? trim(implode("\n", $output)) : '';
    }

    private function formatAnswerKeyBlock(string $answerKeyText): string
    {
        return $answerKeyText !== ''
            ? "\nOfficial Answer Key Reference:\n{$answerKeyText}\n"
            : '';
    }

    private function formatRawTextBlock(string $rawText): string
    {
        return $rawText !== ''
            ? "\nPaper Content:\n{$rawText}\n"
            : '';
    }
}

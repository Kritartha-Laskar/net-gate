<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\Chapter;
use App\Models\Question;
use App\Models\QuestionPaper;
use App\Models\Subject;
use App\Services\AiQuestionPaperService;
use Database\Seeders\GateNetCseSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class AiQuestionPaperController extends Controller
{
    public function __construct(
        private readonly AiQuestionPaperService $aiService
    ) {
    }

    /**
     * Step 1 of AJAX AI generation: validate inputs, ensure course/subject structure,
     * create the QuestionPaper container, and return generation batches.
     */
    public function init(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'mode'            => 'required|in:mocktest_ai,pyq_ai',
                'exam_key'        => 'required|in:gate_cse,net_cse',
                'paper_type'      => 'required|in:combined,mocktest',
                'subject_name'    => 'nullable|string|max:191',
                'chapter_name'    => 'nullable|string|max:191',
                'title'           => 'nullable|string|max:191',
                'exam_year'       => 'nullable|integer|min:1995|max:2100',
                'difficulty'      => 'nullable|in:foundation,exam_level,hard',
                'total_questions' => 'required|integer|min:1|max:200',
                'custom_prompt'   => 'nullable|string|max:2000',
                'provider'        => 'nullable|in:gemini,openai',
            ]);

            $provider = $this->aiService->activeProvider($validated['provider'] ?? null);
            $status = $this->aiService->providerStatus($provider);

            if (!$status['configured']) {
                $envKey = $provider === 'openai' ? 'OPENAI_API_KEY' : 'GEMINI_API_KEY';
                return response()->json([
                    'success' => false,
                    'message' => "AI provider \"{$status['providers'][$provider]['label']}\" is not configured yet. Add {$envKey} to your .env file.",
                ], 422);
            }

            $examKey = $validated['exam_key'];
            $preset = config("ai.exams.{$examKey}");
            $course = $this->aiService->ensureExamStructure($examKey);

            $paperType = $validated['paper_type'];
            $subjectId = null;
            $chapterId = null;
            $subjectName = $validated['subject_name'] ?? null;

            if ($paperType === 'mocktest') {
                $subjectName = $subjectName ?: array_key_first($preset['subjects']);
                $subject = Subject::firstOrCreate([
                    'course_id' => $course->id,
                    'name'      => $subjectName,
                ]);
                $subjectId = $subject->id;

                if (!empty($validated['chapter_name'])) {
                    $chapter = Chapter::firstOrCreate(
                        ['subject_id' => $subject->id, 'name' => trim($validated['chapter_name'])],
                        ['course_id' => $course->id]
                    );
                    $chapterId = $chapter->id;
                }
            }

            $examYear = (int) ($validated['exam_year'] ?? date('Y'));
            $baseTitle = trim((string) ($validated['title'] ?? ''));
            if ($baseTitle === '') {
                if ($validated['mode'] === 'pyq_ai') {
                    $baseTitle = "{$preset['exam_name']} {$examYear} PYQ Paper";
                } elseif ($paperType === 'mocktest') {
                    $baseTitle = "{$preset['exam_name']} {$subjectName} Mock Test";
                } else {
                    $baseTitle = "{$preset['exam_name']} AI Exam-Level Mock Test";
                }
            }

            $title = $this->makeUniqueTitle($baseTitle);
            $batches = $this->aiService->buildGenerationPlan(
                $examKey,
                $paperType,
                $subjectName,
                (int) $validated['total_questions']
            );

            $description = $validated['mode'] === 'pyq_ai'
                ? "AI-generated {$preset['exam_name']} {$examYear} previous-year style paper."
                : "AI-generated {$preset['exam_name']} exam-level mock test ({$validated['difficulty']}).";

            $paper = QuestionPaper::create([
                'title'            => $title,
                'description'      => $description,
                'exam_name'        => $preset['exam_name'],
                'course_id'        => $course->id,
                'paper_type'       => $paperType,
                'subject_id'       => $subjectId,
                'chapter_id'       => $chapterId,
                'subject_quotas'   => [],
                'exam_year'        => $examYear,
                'duration_minutes' => $preset['duration_minutes'],
                'total_marks'      => 0,
                'total_questions'  => 0,
            ]);

            return response()->json([
                'success'   => true,
                'paper_id'  => $paper->id,
                'paper_url' => route('admin.question-papers.show', $paper->id, false),
                'batch_url' => route('admin.question-papers.ai.batch', ['questionPaper' => $paper->id], false),
                'title'     => $paper->title,
                'provider'  => $provider,
                'batches'   => $batches,
            ]);
        } catch (\Throwable $e) {
            \Log::error('AI Paper Init Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to initialize paper: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Step 2 of AJAX AI generation: generate & persist one subject batch for an existing paper.
     */
    public function generateBatch(Request $request, QuestionPaper $questionPaper): JsonResponse
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(180);
        }

        $validated = $request->validate([
            'exam_key'      => 'required|in:gate_cse,net_cse',
            'subject_name'  => 'required|string|max:191',
            'chapter_name'  => 'nullable|string|max:191',
            'count'         => 'required|integer|min:1|max:15',
            'difficulty'    => 'nullable|in:foundation,exam_level,hard',
            'mode'          => 'nullable|in:mocktest_ai,pyq_ai',
            'exam_year'     => 'nullable|integer|min:1995|max:2100',
            'custom_prompt' => 'nullable|string|max:2000',
            'provider'      => 'nullable|in:gemini,openai',
        ]);

        try {
            $course = $this->aiService->ensureExamStructure($validated['exam_key']);
            $subject = Subject::firstOrCreate([
                'course_id' => $course->id,
                'name'      => $validated['subject_name'],
            ]);

            $recentQuestions = Question::where('subject_id', $subject->id)
                ->latest()
                ->limit(15)
                ->pluck('question')
                ->all();

            $generated = $this->aiService->generateQuestionBatch([
                'exam_key'           => $validated['exam_key'],
                'subject_name'       => $validated['subject_name'],
                'chapter_name'       => $validated['chapter_name'] ?? null,
                'count'              => (int) $validated['count'],
                'difficulty'         => $validated['difficulty'] ?? 'exam_level',
                'mode'               => $validated['mode'] ?? 'mocktest_ai',
                'exam_year'          => $validated['exam_year'] ?? date('Y'),
                'custom_prompt'      => $validated['custom_prompt'] ?? '',
                'provider'           => $validated['provider'] ?? null,
                'existing_questions' => $recentQuestions,
            ]);

            $chapter = null;
            if (!empty($validated['chapter_name'])) {
                $chapter = Chapter::firstOrCreate(
                    ['subject_id' => $subject->id, 'name' => trim($validated['chapter_name'])],
                    ['course_id' => $course->id]
                );
            }

            $added = $this->aiService->persistGeneratedQuestions(
                $questionPaper,
                $course,
                $generated,
                $subject,
                $chapter
            );

            $questionPaper->refresh();

            return response()->json([
                'success'         => true,
                'added'           => $added,
                'total_questions' => $questionPaper->total_questions,
                'total_marks'     => $questionPaper->total_marks,
            ]);
        } catch (Throwable $e) {
            \Log::error('AI Paper Batch Generation Error: ' . $e->getMessage(), [
                'paper_id' => $questionPaper->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Extract an uploaded old GATE CSE / NET CSE PDF (or pasted paper text) using AI.
     */
    public function importPdf(Request $request): RedirectResponse
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(240);
        }

        $validated = $request->validate([
            'exam_key'        => 'required|in:gate_cse,net_cse',
            'title'           => 'nullable|string|max:191',
            'exam_year'       => 'nullable|integer|min:1995|max:2100',
            'provider'        => 'nullable|in:gemini,openai',
            'pdf_file'        => 'nullable|file|mimes:pdf|max:20480',
            'raw_text'        => 'nullable|string|max:120000',
            'answer_key_text' => 'nullable|string|max:30000',
        ]);

        try {
            $questions = $this->aiService->extractFromPdfOrText([
                'exam_key'        => $validated['exam_key'],
                'provider'        => $validated['provider'] ?? null,
                'pdf_file'        => $request->file('pdf_file'),
                'raw_text'        => $validated['raw_text'] ?? '',
                'answer_key_text' => $validated['answer_key_text'] ?? '',
            ]);

            if (empty($questions)) {
                return back()->withErrors([
                    'ai_import' => 'AI could not extract any valid questions from the provided PDF/text. Please check the file or try pasting plain text.',
                ]);
            }

            $examKey = $validated['exam_key'];
            $preset = config("ai.exams.{$examKey}");
            $course = $this->aiService->ensureExamStructure($examKey);
            $examYear = (int) ($validated['exam_year'] ?? date('Y'));

            $baseTitle = trim((string) ($validated['title'] ?? ''));
            if ($baseTitle === '') {
                $baseTitle = "{$preset['exam_name']} {$examYear} Imported PYQ Paper";
            }

            $paper = QuestionPaper::create([
                'title'            => $this->makeUniqueTitle($baseTitle),
                'description'      => "Imported via AI from {$preset['exam_name']} ({$examYear}) paper.",
                'exam_name'        => $preset['exam_name'],
                'course_id'        => $course->id,
                'paper_type'       => 'combined',
                'subject_id'       => null,
                'chapter_id'       => null,
                'subject_quotas'   => [],
                'exam_year'        => $examYear,
                'duration_minutes' => $preset['duration_minutes'],
                'total_marks'      => 0,
                'total_questions'  => 0,
            ]);

            $added = $this->aiService->persistGeneratedQuestions($paper, $course, $questions);

            return redirect()
                ->route('admin.question-papers.show', $paper->id)
                ->with('success', "AI imported {$added} question(s) into \"{$paper->title}\" successfully!");
        } catch (Throwable $e) {
            return back()->withErrors(['ai_import' => $e->getMessage()])->withInput();
        }
    }

    /**
     * One-click loader for the built-in verified GATE CSE & UGC NET CSE previous-year paper bank.
     */
    public function loadCsePyqs(): RedirectResponse
    {
        $summary = (new GateNetCseSeeder())->runAndReport();

        $msg = $summary['papers'] > 0
            ? "Loaded {$summary['papers']} GATE CSE & UGC NET CSE previous-year paper(s) with {$summary['questions']} new question(s)."
            : 'All built-in GATE CSE & UGC NET CSE previous-year papers are already loaded.';

        return redirect()
            ->route('admin.question-papers.index', ['paper_type' => 'combined'])
            ->with('success', $msg);
    }

    private function makeUniqueTitle(string $baseTitle): string
    {
        if (!QuestionPaper::where('title', $baseTitle)->exists()) {
            return $baseTitle;
        }

        $counter = 2;
        while (QuestionPaper::where('title', "{$baseTitle} #{$counter}")->exists()) {
            $counter++;
        }

        return "{$baseTitle} #{$counter}";
    }
}


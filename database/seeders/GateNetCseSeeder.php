<?php

namespace Database\Seeders;

use App\Models\Answer;
use App\Models\Chapter;
use App\Models\CourseName;
use App\Models\Question;
use App\Models\QuestionPaper;
use App\Models\Subject;
use App\Services\AiQuestionPaperService;
use App\Services\GateNetPyqBank;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GateNetCseSeeder extends Seeder
{
    /**
     * Seed GATE CSE & UGC NET CSE courses, syllabus subjects/chapters, and verified PYQ papers.
     * Returns summary counts so both CLI seeding and the Admin button can report results.
     */
    public function runAndReport(): array
    {
        $aiService = app(AiQuestionPaperService::class);
        $courses = [
            'gate_cse' => $aiService->ensureExamStructure('gate_cse'),
            'net_cse'  => $aiService->ensureExamStructure('net_cse'),
        ];

        $papersCreated = 0;
        $questionsAdded = 0;

        foreach (GateNetPyqBank::papers() as $paperDef) {
            $examKey = $paperDef['exam_key'];
            $course = $courses[$examKey];
            $preset = config("ai.exams.{$examKey}");

            $paperSubjectId = null;
            if ($paperDef['paper_type'] === 'mocktest' && !empty($paperDef['subject_name'])) {
                $paperSubject = Subject::firstOrCreate([
                    'course_id' => $course->id,
                    'name'      => $paperDef['subject_name'],
                ]);
                $paperSubjectId = $paperSubject->id;
            }

            $existingPaper = QuestionPaper::where('title', $paperDef['title'])->first();
            if ($existingPaper && $existingPaper->questions()->count() > 0) {
                continue;
            }

            $paper = $existingPaper ?: QuestionPaper::create([
                'title'            => $paperDef['title'],
                'description'      => $paperDef['description'],
                'exam_name'        => $preset['exam_name'],
                'course_id'        => $course->id,
                'paper_type'       => $paperDef['paper_type'],
                'subject_id'       => $paperSubjectId,
                'chapter_id'       => null,
                'subject_quotas'   => [],
                'exam_year'        => $paperDef['exam_year'],
                'duration_minutes' => $paperDef['duration_minutes'],
                'total_marks'      => 0,
                'total_questions'  => 0,
            ]);

            if (!$existingPaper) {
                $papersCreated++;
            }

            $pivotRows = [];
            $order = 1;

            foreach ($paperDef['questions'] as $qData) {
                $subject = Subject::firstOrCreate([
                    'course_id' => $course->id,
                    'name'      => $qData['subject'],
                ]);

                $chapter = null;
                if (!empty($qData['chapter'])) {
                    $chapter = Chapter::firstOrCreate(
                        ['subject_id' => $subject->id, 'name' => $qData['chapter']],
                        ['course_id' => $course->id]
                    );
                }

                $question = Question::where('subject_id', $subject->id)
                    ->where('question', $qData['question'])
                    ->first();

                if (!$question) {
                    $question = Question::create([
                        'subject_id'    => $subject->id,
                        'chapter_id'    => $chapter?->id,
                        'question'      => $qData['question'],
                        'question_type' => $qData['question_type'] ?? 'mcq',
                        'reason'        => $qData['reason'] ?? null,
                    ]);

                    foreach ($qData['options'] as $opt) {
                        Answer::create([
                            'question_id' => $question->id,
                            'answer'      => $opt['answer'],
                            'is_correct'  => (bool) $opt['is_correct'],
                            'reason'      => $opt['reason'] ?? null,
                        ]);
                    }
                    $questionsAdded++;
                }

                $pivotRows[] = [
                    'question_paper_id' => $paper->id,
                    'question_id'       => $question->id,
                    'order'             => $order++,
                    'marks'             => (int) ($qData['marks'] ?? 2),
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ];
            }

            if (!empty($pivotRows)) {
                DB::table('question_paper_question')->where('question_paper_id', $paper->id)->delete();
                DB::table('question_paper_question')->insert($pivotRows);
                $aiService->refreshPaperTotals($paper);
            }
        }

        return [
            'courses'   => count($courses),
            'papers'    => $papersCreated,
            'questions' => $questionsAdded,
        ];
    }

    public function run(): void
    {
        $summary = $this->runAndReport();

        if ($this->command) {
            $this->command->info(
                "GATE CSE & UGC NET CSE seeded: {$summary['papers']} new papers, {$summary['questions']} new questions."
            );
        }
    }
}


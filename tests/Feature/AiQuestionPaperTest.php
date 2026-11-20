<?php

namespace Tests\Feature;

use App\Models\CourseName;
use App\Models\QuestionPaper;
use App\Models\User;
use Database\Seeders\GateNetCseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AiQuestionPaperTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'http://localhost']);
        URL::forceRootUrl('http://localhost');
    }

    private function adminUser(): User
    {
        return User::create([
            'name'      => 'Admin Tester',
            'phone_no'  => '9000000001',
            'gmail'     => 'admin.tester@example.com',
            'user_name' => 'admin_tester',
            'password'  => bcrypt('secret123'),
            'user_type' => 'admin',
            'role'      => 7,
            'status'    => 'active',
            'api_token' => 'test-api-token-12345',
        ]);
    }

    public function test_gate_net_cse_seeder_populates_old_papers_idempotently(): void
    {
        $seeder = new GateNetCseSeeder();
        $first = $seeder->runAndReport();

        $this->assertEquals(2, $first['courses']);
        $this->assertGreaterThanOrEqual(6, $first['papers']);
        $this->assertGreaterThanOrEqual(20, $first['questions']);

        $gateCourse = CourseName::where('name', 'GATE CSE')->first();
        $netCourse = CourseName::where('name', 'UGC NET CSE')->first();
        $this->assertNotNull($gateCourse);
        $this->assertNotNull($netCourse);

        $gateCount = QuestionPaper::where('course_id', $gateCourse->id)->count();
        $netCount = QuestionPaper::where('course_id', $netCourse->id)->count();
        $this->assertGreaterThanOrEqual(3, $gateCount);
        $this->assertGreaterThanOrEqual(2, $netCount);

        // Running again must not duplicate papers or questions
        $second = $seeder->runAndReport();
        $this->assertEquals(0, $second['papers']);
        $this->assertEquals(0, $second['questions']);
    }

    public function test_admin_can_load_old_cse_pyqs_and_filter_by_course(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->post(route('admin.question-papers.load-cse-pyqs'))
            ->assertRedirect(route('admin.question-papers.index', ['paper_type' => 'combined']));

        $gateCourse = CourseName::where('name', 'GATE CSE')->firstOrFail();
        $netCourse = CourseName::where('name', 'UGC NET CSE')->firstOrFail();

        $response = $this->actingAs($admin)
            ->get(route('admin.question-papers.index', ['paper_type' => 'combined', 'course_id' => $gateCourse->id]));

        $response->assertOk();
        $response->assertSee('AI Create Question Paper');
        $response->assertSee('GATE CSE 2024 Previous Year Paper');
        $response->assertDontSee('UGC NET CSE Dec 2023 Previous Year Paper');

        $netResponse = $this->actingAs($admin)
            ->get(route('admin.question-papers.index', ['paper_type' => 'combined', 'course_id' => $netCourse->id]));

        $netResponse->assertOk();
        $netResponse->assertSee('UGC NET CSE Dec 2023 Previous Year Paper');
        $netResponse->assertDontSee('GATE CSE 2024 Previous Year Paper');
    }

    public function test_ai_init_returns_422_when_api_key_is_missing(): void
    {
        config(['ai.providers.gemini.api_key' => null]);
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->postJson(route('admin.question-papers.ai.init'), [
            'mode'            => 'mocktest_ai',
            'exam_key'        => 'gate_cse',
            'paper_type'      => 'combined',
            'total_questions' => 10,
            'provider'        => 'gemini',
        ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);

        $this->assertEquals(0, QuestionPaper::count());
    }

    public function test_ai_generates_gate_cse_mock_test_with_gemini_provider(): void
    {
        config([
            'ai.provider'                  => 'gemini',
            'ai.providers.gemini.api_key'  => 'fake-gemini-key',
            'ai.providers.gemini.model'    => 'gemini-2.5-flash',
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => json_encode([
                                'questions' => [
                                    [
                                        'subject'       => 'Algorithms',
                                        'chapter'       => 'Dynamic Programming',
                                        'question_type' => 'mcq',
                                        'marks'         => 2,
                                        'question'      => 'What is the time complexity of 0/1 Knapsack using DP for \( n \) items and capacity \( W \)?',
                                        'reason'        => 'The DP table has \( n \times W \) states, each computed in \( O(1) \) time.',
                                        'options'       => [
                                            ['answer' => '\( O(nW) \)', 'is_correct' => true, 'reason' => 'Correct DP table dimensions'],
                                            ['answer' => '\( O(2^n) \)', 'is_correct' => false, 'reason' => null],
                                            ['answer' => '\( O(n \log W) \)', 'is_correct' => false, 'reason' => null],
                                            ['answer' => '\( O(W^2) \)', 'is_correct' => false, 'reason' => null],
                                        ],
                                    ],
                                ],
                            ]),
                        ]],
                    ],
                ]],
            ], 200),
        ]);

        $admin = $this->adminUser();

        $init = $this->actingAs($admin)->postJson(route('admin.question-papers.ai.init'), [
            'mode'            => 'mocktest_ai',
            'exam_key'        => 'gate_cse',
            'paper_type'      => 'mocktest',
            'subject_name'    => 'Algorithms',
            'difficulty'      => 'exam_level',
            'total_questions' => 1,
            'title'           => 'GATE CSE Algorithms AI Mock #1',
            'provider'        => 'gemini',
        ]);

        $init->assertOk()->assertJson(['success' => true, 'provider' => 'gemini']);
        $paperId = $init->json('paper_id');

        $batch = $this->actingAs($admin)->postJson(
            route('admin.question-papers.ai.batch', $paperId),
            [
                'exam_key'     => 'gate_cse',
                'subject_name' => 'Algorithms',
                'count'        => 1,
                'difficulty'   => 'exam_level',
                'mode'         => 'mocktest_ai',
                'provider'     => 'gemini',
            ]
        );

        $batch->assertOk()->assertJson([
            'success'         => true,
            'added'           => 1,
            'total_questions' => 1,
            'total_marks'     => 2,
        ]);

        $paper = QuestionPaper::with('questions.answers')->findOrFail($paperId);
        $this->assertEquals('GATE CSE', $paper->exam_name);
        $this->assertCount(1, $paper->questions);
        $this->assertCount(4, $paper->questions->first()->answers);
    }

    public function test_ai_generates_net_cse_mock_test_with_openai_provider(): void
    {
        config([
            'ai.provider'                 => 'openai',
            'ai.providers.openai.api_key' => 'fake-openai-key',
            'ai.providers.openai.model'   => 'gpt-4o-mini',
        ]);

        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'questions' => [
                                [
                                    'subject'       => 'Software Engineering',
                                    'chapter'       => 'SDLC Models',
                                    'question_type' => 'mcq',
                                    'marks'         => 2,
                                    'question'      => 'Which SDLC model explicitly integrates risk analysis in every iteration?',
                                    'reason'        => 'Boehm’s Spiral model evaluates risks at every phase loop.',
                                    'options'       => [
                                        ['answer' => 'Spiral Model', 'is_correct' => true, 'reason' => null],
                                        ['answer' => 'Waterfall Model', 'is_correct' => false, 'reason' => null],
                                        ['answer' => 'V-Model', 'is_correct' => false, 'reason' => null],
                                        ['answer' => 'Big Bang Model', 'is_correct' => false, 'reason' => null],
                                    ],
                                ],
                            ],
                        ]),
                    ],
                ]],
            ], 200),
        ]);

        $admin = $this->adminUser();

        $init = $this->actingAs($admin)->postJson(route('admin.question-papers.ai.init'), [
            'mode'            => 'mocktest_ai',
            'exam_key'        => 'net_cse',
            'paper_type'      => 'mocktest',
            'subject_name'    => 'Software Engineering',
            'difficulty'      => 'exam_level',
            'total_questions' => 1,
            'provider'        => 'openai',
        ]);

        $init->assertOk()->assertJson(['success' => true, 'provider' => 'openai']);
        $paperId = $init->json('paper_id');

        $batch = $this->actingAs($admin)->postJson(
            route('admin.question-papers.ai.batch', $paperId),
            [
                'exam_key'     => 'net_cse',
                'subject_name' => 'Software Engineering',
                'count'        => 1,
                'provider'     => 'openai',
            ]
        );

        $batch->assertOk()->assertJson([
            'success'         => true,
            'added'           => 1,
            'total_questions' => 1,
            'total_marks'     => 2,
        ]);
    }

    public function test_ai_imports_old_pyq_pdf_via_gemini(): void
    {
        config([
            'ai.provider'                 => 'gemini',
            'ai.providers.gemini.api_key' => 'fake-gemini-key',
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => function ($request) {
                $body = $request->data();
                $parts = $body['contents'][0]['parts'] ?? [];
                $hasPdf = collect($parts)->contains(fn ($p) => ($p['inline_data']['mime_type'] ?? '') === 'application/pdf');
                $this->assertTrue($hasPdf, 'Expected PDF inline_data in Gemini request');

                return Http::response([
                    'candidates' => [[
                        'content' => [
                            'parts' => [[
                                'text' => json_encode([
                                    'questions' => [
                                        [
                                            'subject'       => 'Computer Networks',
                                            'chapter'       => 'IPv4 Addressing',
                                            'question_type' => 'mcq',
                                            'marks'         => 1,
                                            'question'      => 'What is the loopback IPv4 address block?',
                                            'reason'        => '127.0.0.0/8 is reserved for loopback in IPv4.',
                                            'options'       => [
                                                ['answer' => '127.0.0.0/8', 'is_correct' => true],
                                                ['answer' => '192.168.0.0/16', 'is_correct' => false],
                                                ['answer' => '10.0.0.0/8', 'is_correct' => false],
                                                ['answer' => '169.254.0.0/16', 'is_correct' => false],
                                            ],
                                        ],
                                    ],
                                ]),
                            ]],
                        ],
                    ]],
                ], 200);
            },
        ]);

        $admin = $this->adminUser();
        $fakePdf = UploadedFile::fake()->createWithContent('gate_cse_2020.pdf', '%PDF-1.4 fake pdf content');

        $response = $this->actingAs($admin)->post(route('admin.question-papers.ai.import-pdf'), [
            'exam_key'  => 'gate_cse',
            'exam_year' => 2020,
            'title'     => 'GATE CSE 2020 Official Imported',
            'provider'  => 'gemini',
            'pdf_file'  => $fakePdf,
        ]);

        $paper = QuestionPaper::where('title', 'GATE CSE 2020 Official Imported')->firstOrFail();
        $response->assertRedirect(route('admin.question-papers.show', $paper->id));
        $this->assertEquals(1, $paper->total_questions);
        $this->assertEquals(1, $paper->total_marks);
    }

    public function test_api_filters_question_papers_by_course_id(): void
    {
        (new GateNetCseSeeder())->runAndReport();
        $user = $this->adminUser();
        $gateCourse = CourseName::where('name', 'GATE CSE')->firstOrFail();

        $res = $this->withHeaders([
            'Authorization' => 'Bearer ' . $user->api_token,
        ])->getJson('/api/question-papers?course_id=' . $gateCourse->id);

        $res->assertOk()->assertJson(['success' => true]);
        $data = $res->json('data');
        $this->assertNotEmpty($data);
        foreach ($data as $row) {
            $this->assertEquals($gateCourse->id, $row['course_id']);
            $this->assertEquals('GATE CSE', $row['course_name']);
        }
    }

    public function test_ai_service_resiliently_decodes_latex_and_variant_json_payloads(): void
    {
        $svc = app(\App\Services\AiQuestionPaperService::class);
        $reflector = new \ReflectionClass($svc);
        $decode = $reflector->getMethod('decodeJsonPayload');
        $decode->setAccessible(true);

        // Case 1: unescaped LaTeX math formulas in question text
        $latexJson = '{"questions": [{"question": "Find \( \alpha + \beta \) and \frac{1}{2} where \( \Theta(n^2) \)", "options": [{"answer": "A", "is_correct": true}]}]}';
        $res = $decode->invoke($svc, $latexJson);
        $this->assertIsArray($res);
        $this->assertArrayHasKey('questions', $res);
        $this->assertCount(1, $res['questions']);

        // Case 2: raw list of question objects
        $listJson = '[{"question": "What is Dijkstra algorithm?", "options": [{"answer": "Greedy", "is_correct": true}]}]';
        $res2 = $decode->invoke($svc, $listJson);
        $this->assertIsArray($res2);
        $this->assertArrayHasKey('questions', $res2);
        $this->assertCount(1, $res2['questions']);

        // Case 3: nested under "paper" or "data"
        $nestedJson = '{"paper": [{"question": "Cache hit ratio formula?", "options": [{"answer": "h * Tc", "is_correct": true}]}]}';
        $res3 = $decode->invoke($svc, $nestedJson);
        $this->assertIsArray($res3);
        $this->assertArrayHasKey('questions', $res3);
        $this->assertCount(1, $res3['questions']);
    }
}

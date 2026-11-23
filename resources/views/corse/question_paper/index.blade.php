@extends('layouts.admin')
@section('title', 'Question Papers')

@section('content')
<div class="row">
    <div class="col-md-12 grid-margin stretch-card">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                
                {{-- Error & Success Alert Banner --}}
                @if($errors->any())
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <ul class="mb-0">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                    <div>
                        <h4 class="card-title mb-1 fw-bold text-dark"><i class="icon-doc me-2 text-primary"></i>Question Papers Management</h4>
                        <p class="text-muted small mb-0">Generate exam-level GATE CSE &amp; UGC NET CSE mock tests with AI, import old PYQ PDFs, or load the built-in CSE archive.</p>
                    </div>
                    <div class="d-flex gap-2 align-items-center flex-wrap">
                        <button type="button" class="btn btn-sm fw-bold text-white shadow-sm"
                                style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;"
                                data-toggle="modal" data-target="#aiCreatePaperModal" data-bs-toggle="modal" data-bs-target="#aiCreatePaperModal">
                            <i class="icon-magic-wand me-1"></i> AI Create Question Paper
                        </button>
                        <form method="POST" action="{{ route('admin.question-papers.load-cse-pyqs') }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-outline-success btn-sm fw-semibold" title="Load built-in GATE CSE & UGC NET CSE previous-year papers">
                                <i class="icon-cloud-download me-1"></i> Load Old GATE &amp; NET CSE Papers
                            </button>
                        </form>
                        <button type="button" class="btn btn-outline-primary btn-sm fw-semibold" data-toggle="modal" data-target="#createPaperModal" data-bs-toggle="modal" data-bs-target="#createPaperModal">
                            <i class="icon-plus me-1"></i> Manual Create
                        </button>
                        <span class="badge badge-primary p-2">{{ $papers->total() }} Total Papers</span>
                    </div>
                </div>

                {{-- Course / Exam Track Filter Pills (All Exams | GATE CSE | UGC NET CSE) --}}
                @php
                    $currentType = request('paper_type', 'mocktest');
                @endphp
                <div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
                    <span class="text-muted small fw-semibold me-1"><i class="icon-graduation me-1"></i>Exam Track:</span>
                    <a href="{{ route('admin.question-papers.index', ['paper_type' => $currentType]) }}"
                       class="btn btn-sm rounded-pill px-3 fw-semibold {{ !request('course_id') ? 'btn-dark text-white shadow-sm' : 'btn-outline-secondary' }}">
                        All Exams
                    </a>
                    @foreach($courses as $crs)
                        <a href="{{ route('admin.question-papers.index', ['paper_type' => $currentType, 'course_id' => $crs->id]) }}"
                           class="btn btn-sm rounded-pill px-3 fw-semibold {{ (string) request('course_id') === (string) $crs->id ? 'btn-primary text-white shadow-sm' : 'btn-outline-primary' }}">
                            {{ $crs->name }}
                            <span class="badge {{ (string) request('course_id') === (string) $crs->id ? 'bg-white text-dark' : 'bg-primary text-white' }} ms-1 rounded-pill">{{ $crs->question_papers_count }}</span>
                        </a>
                    @endforeach
                </div>

                {{-- Two Main Sections: Mock Test vs Test Paper --}}
                <div class="card mb-4 border-0 shadow-sm" style="border-radius: 12px; background: #f8f9fa;">
                    <div class="card-body p-2 d-flex gap-2">
                        <a href="{{ route('admin.question-papers.index', array_filter(['paper_type' => 'mocktest', 'course_id' => request('course_id')])) }}" 
                           class="btn flex-fill py-2 fw-bold rounded-3 {{ request('paper_type') === 'mocktest' || !request('paper_type') ? 'btn-info text-white shadow-sm' : 'btn-light text-muted' }}">
                           <i class="icon-layers me-2"></i> 1. Mock Test (Subject-Wise)
                        </a>
                        <a href="{{ route('admin.question-papers.index', array_filter(['paper_type' => 'combined', 'course_id' => request('course_id')])) }}" 
                           class="btn flex-fill py-2 fw-bold rounded-3 {{ request('paper_type') === 'combined' ? 'btn-purple text-white shadow-sm' : 'btn-light text-muted' }}" style="{{ request('paper_type') === 'combined' ? 'background-color: #6C63FF; border-color: #6C63FF; color: white;' : '' }}">
                           <i class="icon-doc me-2"></i> 2. Test Paper (Combined / PYQ Papers)
                        </a>
                    </div>
                </div>

                @if(request('paper_type') !== 'combined')
                    {{-- Subject Filter Cards for Mock Test --}}
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="fw-bold text-dark mb-0"><i class="icon-grid me-2 text-primary"></i>Click Subject to View Papers:</h6>
                            @if(request('subject_id'))
                                <a href="{{ route('admin.question-papers.index', array_filter(['paper_type' => 'mocktest', 'course_id' => request('course_id')])) }}" class="badge bg-secondary text-dark text-decoration-none px-2 py-1">
                                    <i class="icon-refresh me-1"></i> Clear Filter (Show All Subjects)
                                </a>
                            @endif
                        </div>
                        <div class="row g-2">
                            <div class="col-md-3 col-6 mb-2">
                                <a href="{{ route('admin.question-papers.index', array_filter(['paper_type' => 'mocktest', 'course_id' => request('course_id')])) }}" 
                                   class="card text-decoration-none border-0 shadow-sm h-100 subject-card {{ !request('subject_id') ? 'border-primary border-2 shadow' : '' }}" 
                                   style="border-radius: 10px; background: {{ !request('subject_id') ? 'linear-gradient(135deg, #4b6cb7 0%, #182848 100%)' : '#ffffff' }}; color: {{ !request('subject_id') ? '#ffffff' : '#333333' }}; transition: all 0.2s ease;">
                                    <div class="card-body p-3 d-flex align-items-center justify-content-between">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="rounded-circle p-2 d-flex align-items-center justify-content-center" style="width:36px; height:36px; background: {{ !request('subject_id') ? 'rgba(255,255,255,0.2)' : '#eef2ff' }};">
                                                <i class="icon-layers {{ !request('subject_id') ? 'text-white' : 'text-primary' }}"></i>
                                            </div>
                                            <div>
                                                <div class="fw-bold fs-6">All Subjects</div>
                                                <small style="opacity: 0.8; font-size:11px;">Mock Tests</small>
                                            </div>
                                        </div>
                                        <span class="badge {{ !request('subject_id') ? 'bg-white text-dark' : 'bg-primary text-white' }} rounded-pill" style="font-size:11px;">
                                            {{ $papers->total() }}
                                        </span>
                                    </div>
                                </a>
                            </div>

                            @foreach($subjects as $sub)
                                @php
                                    $isSelected = request('subject_id') == $sub->id;
                                    $subLower = strtolower($sub->name);
                                    $gradient = 'linear-gradient(135deg, #00b09b 0%, #96c93d 100%)';
                                    $icon = 'icon-book-open';
                                    if (str_contains($subLower, 'algorithm') || str_contains($subLower, 'data struct')) {
                                        $gradient = 'linear-gradient(135deg, #1e3c72 0%, #2a5298 100%)';
                                        $icon = 'icon-graph';
                                    } elseif (str_contains($subLower, 'operating')) {
                                        $gradient = 'linear-gradient(135deg, #0f766e 0%, #14b8a6 100%)';
                                        $icon = 'icon-settings';
                                    } elseif (str_contains($subLower, 'database') || str_contains($subLower, 'dbms')) {
                                        $gradient = 'linear-gradient(135deg, #6a11cb 0%, #2575fc 100%)';
                                        $icon = 'icon-layers';
                                    } elseif (str_contains($subLower, 'network')) {
                                        $gradient = 'linear-gradient(135deg, #2193b0 0%, #6dd5ed 100%)';
                                        $icon = 'icon-globe';
                                    } elseif (str_contains($subLower, 'theory') || str_contains($subLower, 'compiler')) {
                                        $gradient = 'linear-gradient(135deg, #8e2de2 0%, #4a00e0 100%)';
                                        $icon = 'icon-puzzle';
                                    } elseif (str_contains($subLower, 'math') || str_contains($subLower, 'aptitude')) {
                                        $gradient = 'linear-gradient(135deg, #ea580c 0%, #f97316 100%)';
                                        $icon = 'icon-calculator';
                                    } elseif (str_contains($subLower, 'physic')) {
                                        $gradient = 'linear-gradient(135deg, #2193b0 0%, #6dd5ed 100%)';
                                        $icon = 'icon-bulb';
                                    } elseif (str_contains($subLower, 'chemis')) {
                                        $gradient = 'linear-gradient(135deg, #8e2de2 0%, #4a00e0 100%)';
                                        $icon = 'icon-chemistry';
                                    } elseif (str_contains($subLower, 'bio')) {
                                        $gradient = 'linear-gradient(135deg, #11998e 0%, #38ef7d 100%)';
                                        $icon = 'icon-heart';
                                    }
                                @endphp
                                <div class="col-md-3 col-6 mb-2">
                                    <a href="{{ route('admin.question-papers.index', array_filter(['paper_type' => 'mocktest', 'course_id' => request('course_id'), 'subject_id' => $sub->id])) }}" 
                                       class="card text-decoration-none border-0 shadow-sm h-100 subject-card {{ $isSelected ? 'shadow border-2' : '' }}"
                                       style="border-radius: 10px; background: {{ $isSelected ? $gradient : '#ffffff' }}; color: {{ $isSelected ? '#ffffff' : '#333333' }}; transition: all 0.2s ease;">
                                        <div class="card-body p-3 d-flex align-items-center justify-content-between">
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="rounded-circle p-2 d-flex align-items-center justify-content-center" style="width:36px; height:36px; background: {{ $isSelected ? 'rgba(255,255,255,0.2)' : '#f0fdf4' }};">
                                                    <i class="{{ $icon }} {{ $isSelected ? 'text-white' : 'text-success' }}"></i>
                                                </div>
                                                <div>
                                                    <div class="fw-bold fs-6">{{ $sub->name }}</div>
                                                    <small style="opacity: 0.8; font-size:11px;">{{ optional($sub->course)->name ?? 'Subject Papers' }}</small>
                                                </div>
                                            </div>
                                            <span class="badge {{ $isSelected ? 'bg-white text-dark' : 'bg-success text-white' }} rounded-pill" style="font-size:11px;">
                                                {{ $sub->question_papers_count }}
                                            </span>
                                        </div>
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if($papers->isEmpty())
                    <div class="text-center py-5 text-muted border rounded bg-light">
                        <i class="icon-doc d-block mb-3" style="font-size: 3rem; opacity: 0.3;"></i>
                        <h5>No Question Papers Found</h5>
                        <p class="mb-3">Use AI to generate a new exam-level mock test or import old GATE CSE &amp; UGC NET CSE papers automatically.</p>
                        <div class="d-flex justify-content-center gap-2 flex-wrap">
                            <button type="button" class="btn btn-sm text-white fw-semibold" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;" data-toggle="modal" data-target="#aiCreatePaperModal" data-bs-toggle="modal" data-bs-target="#aiCreatePaperModal">
                                <i class="icon-magic-wand me-1"></i> AI Create Paper
                            </button>
                            <form method="POST" action="{{ route('admin.question-papers.load-cse-pyqs') }}" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-success btn-sm fw-semibold">
                                    <i class="icon-cloud-download me-1"></i> Load Old GATE &amp; NET CSE Papers
                                </button>
                            </form>
                            <button type="button" class="btn btn-outline-primary btn-sm" data-toggle="modal" data-target="#createPaperModal" data-bs-toggle="modal" data-bs-target="#createPaperModal">
                                <i class="icon-plus me-1"></i> Manual Create
                            </button>
                        </div>
                    </div>
                @else

                @if(request('paper_type') === 'combined')
                    {{-- COMBINED PAPERS GRID --}}
                    <div class="row g-4">
                        @foreach($papers as $paper)
                        <div class="col-md-4 mb-4">
                            <div class="card border shadow-sm h-100" style="border-radius:12px; overflow:hidden;">
                                <div style="background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); padding:20px 24px 16px;">
                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <span class="badge bg-warning text-dark fw-bold" style="font-size: 11px;">
                                            <i class="icon-grid me-1"></i> COMBINED PAPER
                                        </span>
                                        <small class="text-white opacity-75">{{ $paper->exam_year ?? date('Y') }}</small>
                                    </div>
                                    <h5 class="text-white fw-bold mb-0 mt-2">{{ $paper->title }}</h5>
                                    @if($paper->course)
                                        <small class="badge bg-light text-dark mt-1"><i class="icon-book-open me-1"></i> {{ $paper->course->name }}</small>
                                    @endif
                                </div>

                                <div class="card-body d-flex flex-column justify-content-between p-4">
                                    <div>
                                        <p class="text-muted small mb-3">{{ Str::limit($paper->description ?? 'No description provided.', 100) }}</p>

                                        <div class="row text-center mb-3 g-0 border rounded py-2 bg-light">
                                            <div class="col-4 border-end">
                                                <div class="fw-bold fs-5 text-primary">{{ $paper->questions_count }}</div>
                                                <small class="text-muted" style="font-size: 10px;">Questions</small>
                                            </div>
                                            <div class="col-4 border-end">
                                                <div class="fw-bold fs-5 text-success">{{ $paper->total_marks }}</div>
                                                <small class="text-muted" style="font-size: 10px;">Marks</small>
                                            </div>
                                            <div class="col-4">
                                                <div class="fw-bold fs-5 text-warning">{{ $paper->duration_minutes }}</div>
                                                <small class="text-muted" style="font-size: 10px;">Minutes</small>
                                            </div>
                                        </div>

                                        <div class="d-flex flex-wrap gap-1 mb-4">
                                            @if($paper->subject_quotas && is_array($paper->subject_quotas))
                                                @foreach($paper->subject_quotas as $subName => $qCount)
                                                    <span class="badge bg-secondary text-dark px-2 py-1" style="font-size: 10px;">
                                                        {{ $subName }}: {{ $qCount }}
                                                    </span>
                                                @endforeach
                                            @endif
                                        </div>
                                    </div>

                                    <div class="d-flex gap-2">
                                        <a href="{{ route('admin.question-papers.show', $paper->id) }}"
                                           class="btn btn-primary btn-sm w-100 py-2 fw-semibold">
                                            <i class="icon-eye me-1"></i> View
                                        </a>
                                        <form action="{{ route('admin.question-papers.destroy', $paper->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this Question Paper?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger btn-sm py-2 px-3" title="Delete Paper">
                                                <i class="icon-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                @else
                    {{-- MOCK TEST SUBJECT & CHAPTER WISE GROUPED PAPERS --}}
                    @php
                        $groupedPapers = $papers->groupBy(fn($p) => optional($p->subject)->name ?? 'Other Subjects');
                    @endphp

                    @foreach($groupedPapers as $subjectName => $subjectPapers)
                        @php
                            $subSlug = Str::slug($subjectName);
                            $subLower = strtolower($subjectName);
                            $headerBg = 'linear-gradient(135deg, #11998e 0%, #38ef7d 100%)';
                            $badgeBg = 'bg-dark';
                            $iconName = 'icon-book-open';
                            if (str_contains($subLower, 'algorithm') || str_contains($subLower, 'data struct')) {
                                $headerBg = 'linear-gradient(135deg, #1e3c72 0%, #2a5298 100%)';
                                $iconName = 'icon-graph';
                            } elseif (str_contains($subLower, 'operating')) {
                                $headerBg = 'linear-gradient(135deg, #0f766e 0%, #14b8a6 100%)';
                                $iconName = 'icon-settings';
                            } elseif (str_contains($subLower, 'database') || str_contains($subLower, 'dbms')) {
                                $headerBg = 'linear-gradient(135deg, #6a11cb 0%, #2575fc 100%)';
                                $iconName = 'icon-layers';
                            } elseif (str_contains($subLower, 'network')) {
                                $headerBg = 'linear-gradient(135deg, #2193b0 0%, #6dd5ed 100%)';
                                $iconName = 'icon-globe';
                            } elseif (str_contains($subLower, 'theory') || str_contains($subLower, 'compiler')) {
                                $headerBg = 'linear-gradient(135deg, #8e2de2 0%, #4a00e0 100%)';
                                $iconName = 'icon-puzzle';
                            } elseif (str_contains($subLower, 'math') || str_contains($subLower, 'aptitude')) {
                                $headerBg = 'linear-gradient(135deg, #ea580c 0%, #f97316 100%)';
                                $iconName = 'icon-calculator';
                            } elseif (str_contains($subLower, 'physic')) {
                                $headerBg = 'linear-gradient(135deg, #1e3c72 0%, #2a5298 100%)';
                                $iconName = 'icon-bulb';
                            } elseif (str_contains($subLower, 'chemis')) {
                                $headerBg = 'linear-gradient(135deg, #6a11cb 0%, #2575fc 100%)';
                                $iconName = 'icon-chemistry';
                            } elseif (str_contains($subLower, 'bio')) {
                                $headerBg = 'linear-gradient(135deg, #00b09b 0%, #96c93d 100%)';
                                $iconName = 'icon-heart';
                            }

                            $groupedByChapter = $subjectPapers->groupBy(fn($p) => optional($p->chapter)->full_title ?? 'Full Subject / All Chapters');
                        @endphp
                        <div class="subject-section mb-4" id="section-{{ $subSlug }}">
                            <div class="card border-0 shadow-sm mb-3" style="border-radius: 12px; background: {{ $headerBg }};">
                                <div class="card-body py-3 px-4 d-flex justify-content-between align-items-center text-white" 
                                     onclick="toggleSubjectSection('group-{{ $subSlug }}', 'icon-{{ $subSlug }}')" 
                                     style="cursor: pointer;">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="bg-white text-dark rounded-circle d-flex align-items-center justify-content-center fw-bold shadow-sm" style="width:40px; height:40px;">
                                            <i class="{{ $iconName }} text-primary fs-5"></i>
                                        </div>
                                        <div>
                                            <h5 class="fw-bold mb-0 text-white">{{ strtoupper($subjectName) }} MOCK TEST PAPERS</h5>
                                            <small class="text-white opacity-75">{{ count($subjectPapers) }} Question Paper(s) across {{ count($groupedByChapter) }} Chapter Group(s)</small>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge bg-white text-dark fw-bold px-3 py-2" style="border-radius: 20px;">
                                            {{ count($subjectPapers) }} Papers
                                        </span>
                                        <button type="button" class="btn btn-sm btn-light rounded-circle shadow-sm px-2 py-1 ms-2" title="Toggle Papers">
                                            <i class="icon-arrow-down fw-bold text-dark" id="icon-{{ $subSlug }}"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="subject-paper-group" id="group-{{ $subSlug }}">
                                @foreach($groupedByChapter as $chapterTitle => $chapterPapers)
                                    <div class="card p-3 mb-3 border-0 shadow-sm bg-light" style="border-radius: 10px;">
                                        <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2">
                                            <h6 class="fw-bold text-dark mb-0">
                                                <i class="icon-notebook me-2 text-primary"></i>{{ $chapterTitle }}
                                            </h6>
                                            <span class="badge bg-primary text-white px-2 py-1" style="font-size: 11px;">
                                                {{ count($chapterPapers) }} {{ Str::plural('Paper', count($chapterPapers)) }}
                                            </span>
                                        </div>
                                        <div class="row g-4">
                                            @foreach($chapterPapers as $paper)
                                            <div class="col-md-4 mb-3">
                                                <div class="card border shadow-sm h-100" style="border-radius:12px; overflow:hidden;">
                                                    <div style="background: {{ $headerBg }}; padding:20px 24px 16px;">
                                                        <div class="d-flex justify-content-between align-items-start mb-1">
                                                            <span class="badge bg-dark text-white fw-bold" style="font-size: 11px;">
                                                                <i class="icon-layers me-1"></i> {{ strtoupper(optional($paper->subject)->name ?? 'MOCK TEST') }}
                                                            </span>
                                                            <small class="text-white opacity-75">{{ $paper->exam_year ?? date('Y') }}</small>
                                                        </div>
                                                        <h5 class="text-white fw-bold mb-0 mt-2">{{ $paper->title }}</h5>
                                                        @if($paper->course)
                                                            <small class="badge bg-light text-dark mt-1"><i class="icon-book-open me-1"></i> {{ $paper->course->name }}</small>
                                                        @endif
                                                    </div>

                                                    <div class="card-body d-flex flex-column justify-content-between p-4">
                                                        <div>
                                                            <p class="text-muted small mb-3">{{ Str::limit($paper->description ?? 'No description provided.', 100) }}</p>

                                                            <div class="row text-center mb-3 g-0 border rounded py-2 bg-light">
                                                                <div class="col-4 border-end">
                                                                    <div class="fw-bold fs-5 text-primary">{{ $paper->questions_count }}</div>
                                                                    <small class="text-muted" style="font-size: 10px;">Questions</small>
                                                                </div>
                                                                <div class="col-4 border-end">
                                                                    <div class="fw-bold fs-5 text-success">{{ $paper->total_marks }}</div>
                                                                    <small class="text-muted" style="font-size: 10px;">Marks</small>
                                                                </div>
                                                                <div class="col-4">
                                                                    <div class="fw-bold fs-5 text-warning">{{ $paper->duration_minutes }}</div>
                                                                    <small class="text-muted" style="font-size: 10px;">Minutes</small>
                                                                </div>
                                                            </div>

                                                            <div class="d-flex flex-wrap gap-1 mb-4">
                                                                @if($paper->chapter)
                                                                    <span class="badge bg-purple text-white px-2 py-1" style="font-size: 10px; background-color: #6c5ce7;">
                                                                        <i class="icon-notebook me-1"></i> {{ $paper->chapter->full_title }}
                                                                    </span>
                                                                @elseif($paper->subject)
                                                                    <span class="badge bg-info text-white px-2 py-1" style="font-size: 10px;">
                                                                        {{ $paper->subject->name }}: {{ $paper->questions_count }} Questions
                                                                    </span>
                                                                @endif
                                                            </div>
                                                        </div>

                                                        <div class="d-flex gap-2">
                                                            <a href="{{ route('admin.question-papers.show', $paper->id) }}"
                                                               class="btn btn-primary btn-sm w-100 py-2 fw-semibold">
                                                                <i class="icon-eye me-1"></i> View Paper
                                                            </a>
                                                            <form action="{{ route('admin.question-papers.destroy', $paper->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this Question Paper?')">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="btn btn-outline-danger btn-sm py-2 px-3" title="Delete Paper">
                                                                    <i class="icon-trash"></i>
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                @endif

                <div class="mt-4">{{ $papers->links() }}</div>
                @endif

            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════
     MODAL: CREATE QUESTION PAPER (2 TABS: MOCK TEST & COMBINED)
══════════════════════════════════════════════════════ -->
<div class="modal fade" id="createPaperModal" tabindex="-1" role="dialog" aria-labelledby="createPaperModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold" id="createPaperModalLabel"><i class="icon-doc text-primary me-2"></i>Create New Question Paper</h5>
                <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <!-- Tab Navigation -->
            <div class="px-3 pt-3">
                <ul class="nav nav-pills nav-fill bg-light p-1 rounded" id="paperTabs">
                    <li class="nav-item">
                        <button type="button" class="nav-link active py-2 fw-semibold" id="btn-tab-mocktest" onclick="switchPaperTab('mocktest')">
                            <i class="icon-layers me-1"></i> 1. Subject-Wise / Chapter-Wise Mock Test
                        </button>
                    </li>
                    <li class="nav-item">
                        <button type="button" class="nav-link py-2 fw-semibold" id="btn-tab-combined" onclick="switchPaperTab('combined')">
                            <i class="icon-grid me-1"></i> 2. Combined Question Paper
                        </button>
                    </li>
                </ul>
            </div>

            <div class="modal-body pt-3">
                <!-- FORM 1: SUBJECT-WISE MOCK TEST -->
                <div id="pane-mocktest">
                    <form method="POST" action="{{ route('admin.question-papers.store') }}">
                        @csrf
                        <input type="hidden" name="paper_type" value="mocktest">

                        <div class="alert alert-info py-2 small mb-3">
                            <i class="icon-info me-1"></i> Create a subject-specific or chapter-specific mock test paper structure. You can add or type questions manually into it after creation.
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="mock_course_id" class="fw-semibold">Select Course <small class="text-muted">(Optional)</small></label>
                                <select name="course_id" id="mock_course_id" class="form-select form-control" onchange="filterMockSubjects(this.value)">
                                    <option value="">-- All Courses --</option>
                                    @foreach($courses as $crs)
                                        <option value="{{ $crs->id }}">{{ $crs->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="mock_subject_id" class="fw-semibold">Select Subject <span class="text-danger">*</span></label>
                                <select name="subject_id" id="mock_subject_id" class="form-select form-control" onchange="loadMockChapters(this.value)" required>
                                    <option value="">-- Choose Subject --</option>
                                    @foreach($subjects as $sub)
                                        <option value="{{ $sub->id }}" data-course-id="{{ $sub->course_id }}">
                                            {{ $sub->name }} ({{ $sub->questions_count }} questions)
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="mock_chapter_id" class="fw-semibold">Select Chapter <small class="text-muted">(Optional)</small></label>
                                <select name="chapter_id" id="mock_chapter_id" class="form-select form-control">
                                    <option value="">-- All Chapters / Full Subject --</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group mb-3">
                            <label for="mock_title" class="fw-semibold">Paper Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" id="mock_title" class="form-control" placeholder="e.g. GATE CSE Operating System Mock Test 1" required>
                        </div>

                        <div class="form-group mb-3">
                            <label for="mock_desc" class="fw-semibold">Description</label>
                            <textarea name="description" id="mock_desc" rows="2" class="form-control" placeholder="Optional brief details..."></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group mb-3">
                                    <label for="mock_exam_name" class="fw-semibold">Exam Name</label>
                                    <input type="text" name="exam_name" id="mock_exam_name" class="form-control" value="GATE / NET CSE Mock">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-3">
                                    <label for="mock_duration" class="fw-semibold">Duration (Minutes)</label>
                                    <input type="number" name="duration_minutes" id="mock_duration" class="form-control" value="60">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-3">
                                    <label for="mock_marks" class="fw-semibold">Total Marks</label>
                                    <input type="number" name="total_marks" id="mock_marks" class="form-control" value="100">
                                </div>
                            </div>
                        </div>

                        <div class="modal-footer px-0 pb-0">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary fw-semibold"><i class="icon-plus me-1"></i> Create Subject Mock Test</button>
                        </div>
                    </form>
                </div>

                <!-- FORM 2: COMBINED QUESTION PAPER -->
                <div id="pane-combined" style="display: none;">
                    <form method="POST" action="{{ route('admin.question-papers.store') }}">
                        @csrf
                        <input type="hidden" name="paper_type" value="combined">

                        <div class="alert alert-purple py-2 small mb-3" style="background-color: #f3ebff; color: #5a189a;">
                            <i class="icon-info me-1"></i> Create a multi-subject combined question paper. You can add or type questions manually for each subject after creation.
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="comb_course_id" class="fw-semibold">Select Course <small class="text-muted">(Optional)</small></label>
                                <select name="course_id" id="comb_course_id" class="form-select form-control">
                                    <option value="">-- All Courses --</option>
                                    @foreach($courses as $crs)
                                        <option value="{{ $crs->id }}">{{ $crs->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="comb_title" class="fw-semibold">Paper Title <span class="text-danger">*</span></label>
                                <input type="text" name="title" id="comb_title" class="form-control" placeholder="e.g. GATE CSE Grand Mock Test #1" required>
                            </div>
                        </div>

                        <div class="form-group mb-3">
                            <label for="comb_desc" class="fw-semibold">Description</label>
                            <textarea name="description" id="comb_desc" rows="2" class="form-control" placeholder="Brief description of combined test..."></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group mb-3">
                                    <label for="comb_exam_name" class="fw-semibold">Exam Name</label>
                                    <input type="text" name="exam_name" id="comb_exam_name" class="form-control" value="GATE CSE">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-3">
                                    <label for="comb_duration" class="fw-semibold">Duration (Minutes)</label>
                                    <input type="number" name="duration_minutes" id="comb_duration" class="form-control" value="180">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-3">
                                    <label for="comb_marks" class="fw-semibold">Total Marks</label>
                                    <input type="number" name="total_marks" id="comb_marks" class="form-control" value="100">
                                </div>
                            </div>
                        </div>

                        <div class="modal-footer px-0 pb-0">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-purple text-white fw-semibold" style="background-color: #6C63FF; border-color: #6C63FF;"><i class="icon-plus me-1"></i> Create Combined Paper</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════
     MODAL: AI CREATE QUESTION PAPER & PYQ PDF IMPORTER
══════════════════════════════════════════════════════ -->
<div class="modal fade" id="aiCreatePaperModal" tabindex="-1" role="dialog" aria-labelledby="aiCreatePaperModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header border-bottom-0 pb-2" style="background: linear-gradient(135deg, #1e3c72 0%, #6C63FF 100%); color: #fff;">
                <div>
                    <h5 class="modal-title fw-bold text-white mb-0" id="aiCreatePaperModalLabel">
                        <i class="icon-magic-wand me-2"></i>AI Question Paper Creator (GATE CSE &amp; UGC NET CSE)
                    </h5>
                    <small class="opacity-75">Generate exam-level mock tests on demand or auto-import old GATE/NET PDFs with zero manual typing.</small>
                </div>
                <button type="button" class="close text-white" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close" style="opacity: 0.9;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="px-3 pt-3">
                @if(!$aiStatus['configured'])
                    <div class="alert alert-warning py-2 small mb-2">
                        <strong><i class="icon-exclamation me-1"></i>API Key Not Configured Yet:</strong>
                        Set <code>GEMINI_API_KEY</code> or <code>OPENAI_API_KEY</code> in your <code>.env</code> file (switchable via <code>AI_PROVIDER=gemini|openai</code>).
                        You can still load the built-in old papers immediately using <strong>Load Old GATE &amp; NET CSE Papers</strong>.
                    </div>
                @else
                    <div class="alert alert-success py-2 small mb-2 d-flex justify-content-between align-items-center">
                        <span><i class="icon-check me-1"></i>Active Provider: <strong>{{ $aiStatus['providers'][$aiStatus['active']]['label'] }}</strong> ({{ $aiStatus['providers'][$aiStatus['active']]['model'] }})</span>
                        <span class="badge bg-success text-white">Ready</span>
                    </div>
                @endif

                <ul class="nav nav-pills nav-fill bg-light p-1 rounded" id="aiPaperTabs">
                    <li class="nav-item">
                        <button type="button" class="nav-link active py-2 fw-semibold" id="btn-ai-tab-generate" onclick="switchAiModalTab('generate')">
                            <i class="icon-magic-wand me-1"></i> 1. AI Exam-Level Mock / PYQ Generator
                        </button>
                    </li>
                    <li class="nav-item">
                        <button type="button" class="nav-link py-2 fw-semibold" id="btn-ai-tab-pdf" onclick="switchAiModalTab('pdf')">
                            <i class="icon-cloud-upload me-1"></i> 2. AI Import Old Paper PDF / Text
                        </button>
                    </li>
                </ul>
            </div>

            <div class="modal-body pt-3">
                {{-- PANE 1: AI MOCK TEST / PYQ GENERATOR (AJAX BATCHES) --}}
                <div id="ai-pane-generate">
                    <form id="aiGeneratePaperForm" data-ajax-form="true" onsubmit="return false;">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="fw-semibold" for="ai_provider">AI Provider</label>
                                <select id="ai_provider" class="form-select form-control">
                                    @foreach($aiStatus['providers'] as $pKey => $pInfo)
                                        <option value="{{ $pKey }}" {{ $aiStatus['active'] === $pKey ? 'selected' : '' }}>
                                            {{ $pInfo['label'] }} {{ $pInfo['configured'] ? '✓' : '(Add Key in .env)' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="fw-semibold" for="ai_exam_key">Exam Track <span class="text-danger">*</span></label>
                                <select id="ai_exam_key" class="form-select form-control" onchange="syncAiExamSubjects()">
                                    <option value="gate_cse">GATE CSE</option>
                                    <option value="net_cse">UGC NET CSE</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="fw-semibold" for="ai_mode">Generation Mode</label>
                                <select id="ai_mode" class="form-select form-control" onchange="toggleAiModeFields()">
                                    <option value="mocktest_ai">New Exam-Level Mock Test</option>
                                    <option value="pyq_ai">Old Year PYQ-Style Paper</option>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="fw-semibold" for="ai_paper_type">Paper Structure</label>
                                <select id="ai_paper_type" class="form-select form-control" onchange="toggleAiPaperTypeFields()">
                                    <option value="combined">Full Exam-Level Combined (All CS Subjects)</option>
                                    <option value="mocktest">Single Subject / Chapter Mock Test</option>
                                </select>
                            </div>
                            <div class="col-md-4" id="ai_subject_wrap" style="display: none;">
                                <label class="fw-semibold" for="ai_subject_name">Subject</label>
                                <select id="ai_subject_name" class="form-select form-control"></select>
                            </div>
                            <div class="col-md-4" id="ai_chapter_wrap" style="display: none;">
                                <label class="fw-semibold" for="ai_chapter_name">Chapter / Topic <small class="text-muted">(Optional)</small></label>
                                <input type="text" id="ai_chapter_name" class="form-control" placeholder="e.g. Deadlocks, Dynamic Programming">
                            </div>
                            <div class="col-md-4" id="ai_difficulty_wrap">
                                <label class="fw-semibold" for="ai_difficulty">Difficulty Level</label>
                                <select id="ai_difficulty" class="form-select form-control">
                                    <option value="exam_level">Official Exam-Level Standard</option>
                                    <option value="hard">Hard / Rank-Booster</option>
                                    <option value="foundation">Foundation / Concept Practice</option>
                                </select>
                            </div>
                            <div class="col-md-4" id="ai_year_wrap" style="display: none;">
                                <label class="fw-semibold" for="ai_exam_year">Previous Exam Year</label>
                                <input type="number" id="ai_exam_year" class="form-control" min="1995" max="2030" value="2024">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-5">
                                <label class="fw-semibold d-flex justify-content-between align-items-center mb-1" for="ai_total_questions">
                                    <span>Number of Questions <span class="text-danger">*</span></span>
                                    <span id="ai_q_pattern_badge" class="badge bg-primary text-white" style="font-size: 11px;">GATE: 65 Qs (100 Marks)</span>
                                </label>
                                <input type="number" id="ai_total_questions" class="form-control" min="1" max="200" value="65" oninput="updateAiQuestionBadge()">
                                <div class="d-flex flex-wrap gap-1 mt-1" id="ai_preset_pills">
                                    <!-- Dynamic 1-click pills inserted by JS -->
                                </div>
                            </div>
                            <div class="col-md-7">
                                <label class="fw-semibold" for="ai_title">Paper Title <small class="text-muted">(Optional - auto-named if empty)</small></label>
                                <input type="text" id="ai_title" class="form-control" placeholder="e.g. GATE CSE 2026 Exam-Level Mock Test #1">
                            </div>
                        </div>

                        <div class="form-group mb-3">
                            <label class="fw-semibold" for="ai_custom_prompt">Custom Instructions / Focus Topics <small class="text-muted">(Optional)</small></label>
                            <textarea id="ai_custom_prompt" rows="2" class="form-control" placeholder="e.g. Include tricky C pointer recursion, B+ tree order numericals, TCP congestion window, and pipelining hazards."></textarea>
                        </div>

                        {{-- Live Progress & Error Feedback --}}
                        <div id="ai-progress-box" class="card border-primary bg-light mb-3" style="display: none;">
                            <div class="card-body py-2 px-3">
                                <div class="d-flex justify-content-between small fw-semibold mb-1">
                                    <span id="ai-progress-status">Preparing AI generation...</span>
                                    <span id="ai-progress-percent">0%</span>
                                </div>
                                <div class="progress" style="height: 10px;">
                                    <div id="ai-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated bg-primary" style="width: 0%;"></div>
                                </div>
                            </div>
                        </div>

                        <div id="ai-error-box" class="alert alert-danger py-2 small mb-3" style="display: none;"></div>

                        <div class="modal-footer px-0 pb-0">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" id="btn-run-ai-generate" onclick="startAiGeneration()" class="btn text-white fw-bold" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
                                <i class="icon-magic-wand me-1"></i> Generate Question Paper with AI
                            </button>
                        </div>
                    </form>
                </div>

                {{-- PANE 2: AI IMPORT OLD PDF / TEXT --}}
                <div id="ai-pane-pdf" style="display: none;">
                    <form method="POST" action="{{ route('admin.question-papers.ai.import-pdf') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="alert alert-info py-2 small mb-3">
                            <i class="icon-info me-1"></i> Upload an official GATE CSE or UGC NET CSE previous-year PDF (up to 20 MB) or paste raw text. AI extracts all questions, options, correct answers, and explanations automatically.
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="fw-semibold" for="pdf_provider">AI Provider</label>
                                <select name="provider" id="pdf_provider" class="form-select form-control">
                                    @foreach($aiStatus['providers'] as $pKey => $pInfo)
                                        <option value="{{ $pKey }}" {{ $aiStatus['active'] === $pKey ? 'selected' : '' }}>
                                            {{ $pInfo['label'] }} {{ $pKey === 'gemini' ? '(Native PDF + Text)' : '(Text / pdftotext)' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="fw-semibold" for="pdf_exam_key">Exam Track</label>
                                <select name="exam_key" id="pdf_exam_key" class="form-select form-control">
                                    <option value="gate_cse">GATE CSE</option>
                                    <option value="net_cse">UGC NET CSE</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="fw-semibold" for="pdf_exam_year">Exam Year</label>
                                <input type="number" name="exam_year" id="pdf_exam_year" class="form-control" min="1995" max="2030" value="2024">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="fw-semibold" for="pdf_title">Paper Title <small class="text-muted">(Optional)</small></label>
                                <input type="text" name="title" id="pdf_title" class="form-control" placeholder="e.g. GATE CSE 2024 Shift 1 Official Paper">
                            </div>
                            <div class="col-md-6">
                                <label class="fw-semibold" for="pdf_file">Upload PYQ PDF <small class="text-muted">(Max 20 MB)</small></label>
                                <input type="file" name="pdf_file" id="pdf_file" class="form-control" accept="application/pdf,.pdf">
                            </div>
                        </div>

                        <div class="form-group mb-3">
                            <label class="fw-semibold" for="pdf_raw_text">Or Paste Paper Text <small class="text-muted">(Optional if PDF uploaded)</small></label>
                            <textarea name="raw_text" id="pdf_raw_text" rows="3" class="form-control" placeholder="Paste questions from an old paper here..."></textarea>
                        </div>

                        <div class="form-group mb-3">
                            <label class="fw-semibold" for="pdf_answer_key_text">Official Answer Key <small class="text-muted">(Optional, e.g. Q1: B, Q2: A...)</small></label>
                            <textarea name="answer_key_text" id="pdf_answer_key_text" rows="2" class="form-control" placeholder="Paste answer key if separate from the PDF..."></textarea>
                        </div>

                        <div class="modal-footer px-0 pb-0">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary fw-bold">
                                <i class="icon-cloud-upload me-1"></i> Extract &amp; Import Paper with AI
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const AI_EXAM_PRESETS = @json($examPresets);

function setAiQuestionCount(n) {
    const input = document.getElementById('ai_total_questions');
    if (input) {
        input.value = n;
        updateAiQuestionBadge();
    }
}

function updateAiQuestionBadge() {
    const examKey = document.getElementById('ai_exam_key').value;
    const paperType = document.getElementById('ai_paper_type').value;
    const qCount = parseInt(document.getElementById('ai_total_questions').value || '0', 10);
    const badge = document.getElementById('ai_q_pattern_badge');
    if (!badge) return;

    if (paperType === 'mocktest') {
        const marks = qCount * 2;
        badge.className = 'badge bg-success text-white';
        badge.textContent = `Subject Mock: ${qCount} Qs (~${marks} Marks)`;
    } else if (examKey === 'net_cse') {
        badge.className = qCount === 150 ? 'badge bg-success text-white' : 'badge bg-purple text-white';
        badge.textContent = qCount === 150 ? 'Official NET Pattern: 150 Qs (300 Marks)' : `UGC NET: ${qCount} Qs (${qCount * 2} Marks)`;
    } else {
        badge.className = qCount === 65 ? 'badge bg-success text-white' : 'badge bg-primary text-white';
        badge.textContent = qCount === 65 ? 'Official GATE Pattern: 65 Qs (100 Marks)' : `GATE CSE: ${qCount} Qs`;
    }
}

function syncAiExamSubjects() {
    const examKey = document.getElementById('ai_exam_key').value;
    const paperType = document.getElementById('ai_paper_type').value;
    const preset = AI_EXAM_PRESETS[examKey] || {};
    const subjectSelect = document.getElementById('ai_subject_name');
    const pillsWrap = document.getElementById('ai_preset_pills');
    const qInput = document.getElementById('ai_total_questions');

    if (subjectSelect) {
        subjectSelect.innerHTML = '';
        const subjects = Object.keys(preset.subjects || {});
        subjects.forEach(name => {
            const opt = document.createElement('option');
            opt.value = name;
            opt.textContent = name;
            subjectSelect.appendChild(opt);
        });
    }

    if (paperType === 'combined') {
        if (examKey === 'net_cse') {
            qInput.value = 150;
            if (pillsWrap) {
                pillsWrap.innerHTML = `
                    <button type="button" class="btn btn-xs btn-outline-primary py-0 px-2" style="font-size:11px;" onclick="setAiQuestionCount(150)">Full Exam (150 Qs)</button>
                    <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2" style="font-size:11px;" onclick="setAiQuestionCount(100)">Paper 2 CS (100 Qs)</button>
                    <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2" style="font-size:11px;" onclick="setAiQuestionCount(50)">Paper 1 (50 Qs)</button>
                    <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2" style="font-size:11px;" onclick="setAiQuestionCount(30)">30 Qs</button>
                `;
            }
        } else {
            qInput.value = 65;
            if (pillsWrap) {
                pillsWrap.innerHTML = `
                    <button type="button" class="btn btn-xs btn-outline-primary py-0 px-2" style="font-size:11px;" onclick="setAiQuestionCount(65)">Full Exam (65 Qs)</button>
                    <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2" style="font-size:11px;" onclick="setAiQuestionCount(30)">30 Qs</button>
                    <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2" style="font-size:11px;" onclick="setAiQuestionCount(15)">15 Qs</button>
                `;
            }
        }
    } else {
        qInput.value = 25;
        if (pillsWrap) {
            pillsWrap.innerHTML = `
                <button type="button" class="btn btn-xs btn-outline-primary py-0 px-2" style="font-size:11px;" onclick="setAiQuestionCount(25)">25 Qs</button>
                <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2" style="font-size:11px;" onclick="setAiQuestionCount(15)">15 Qs</button>
                <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2" style="font-size:11px;" onclick="setAiQuestionCount(10)">10 Qs</button>
            `;
        }
    }

    updateAiQuestionBadge();
}

function toggleAiPaperTypeFields() {
    const type = document.getElementById('ai_paper_type').value;
    const showSubject = type === 'mocktest';
    document.getElementById('ai_subject_wrap').style.display = showSubject ? 'block' : 'none';
    document.getElementById('ai_chapter_wrap').style.display = showSubject ? 'block' : 'none';
    syncAiExamSubjects();
}

document.addEventListener('DOMContentLoaded', function() {
    syncAiExamSubjects();
});

function toggleAiModeFields() {
    const mode = document.getElementById('ai_mode').value;
    const isPyq = mode === 'pyq_ai';
    document.getElementById('ai_year_wrap').style.display = isPyq ? 'block' : 'none';
    document.getElementById('ai_difficulty_wrap').style.display = isPyq ? 'none' : 'block';
}

function switchAiModalTab(tab) {
    const btnGen = document.getElementById('btn-ai-tab-generate');
    const btnPdf = document.getElementById('btn-ai-tab-pdf');
    const paneGen = document.getElementById('ai-pane-generate');
    const panePdf = document.getElementById('ai-pane-pdf');

    if (tab === 'generate') {
        btnGen.classList.add('active');
        btnPdf.classList.remove('active');
        paneGen.style.display = 'block';
        panePdf.style.display = 'none';
    } else {
        btnPdf.classList.add('active');
        btnGen.classList.remove('active');
        panePdf.style.display = 'block';
        paneGen.style.display = 'none';
    }
}

async function startAiGeneration() {
    const btn = document.getElementById('btn-run-ai-generate');
    const errBox = document.getElementById('ai-error-box');
    const progBox = document.getElementById('ai-progress-box');
    const progBar = document.getElementById('ai-progress-bar');
    const progStatus = document.getElementById('ai-progress-status');
    const progPct = document.getElementById('ai-progress-percent');

    errBox.style.display = 'none';
    errBox.textContent = '';

    const payload = {
        provider: document.getElementById('ai_provider').value,
        exam_key: document.getElementById('ai_exam_key').value,
        mode: document.getElementById('ai_mode').value,
        paper_type: document.getElementById('ai_paper_type').value,
        subject_name: document.getElementById('ai_subject_name').value,
        chapter_name: document.getElementById('ai_chapter_name').value,
        difficulty: document.getElementById('ai_difficulty').value,
        exam_year: document.getElementById('ai_exam_year').value,
        total_questions: parseInt(document.getElementById('ai_total_questions').value || '10', 10),
        title: document.getElementById('ai_title').value,
        custom_prompt: document.getElementById('ai_custom_prompt').value,
    };

    btn.disabled = true;
    const origBtnHtml = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Generating with AI...';
    progBox.style.display = 'block';
    progBar.style.width = '5%';
    progPct.textContent = '5%';
    progStatus.textContent = 'Creating question paper blueprint...';

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                   || document.querySelector('input[name="_token"]')?.value
                   || '{{ csrf_token() }}';

    try {
        const initUrl = '{{ route("admin.question-papers.ai.init", [], false) }}';
        const initRes = await fetch(initUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify(Object.assign({}, payload, { _token: csrfToken })),
        });

        const initText = await initRes.text();
        let initData;
        try {
            initData = JSON.parse(initText);
        } catch (pe) {
            console.error('Non-JSON server response on init:', initText);
            if (initRes.status === 419) {
                throw new Error('Your session expired (CSRF error). Please refresh the page (F5) and try again.');
            }
            if (initRes.status === 401 || initRes.status === 403 || initText.includes('login')) {
                throw new Error('Your session timed out. Please refresh the page and log in again.');
            }
            if (initRes.status === 503) {
                throw new Error('Server returned 503 (Service Temporarily Unavailable). Your Laravel app may be in maintenance mode (run "php artisan up" on the server) or the server process is temporarily busy. Please wait a moment and refresh.');
            }
            throw new Error(`Server returned HTML error (${initRes.status}). Please refresh the page (F5) and try again.`);
        }

        if (!initRes.ok || !initData.success) {
            throw new Error(initData.message || 'Failed to initialize AI paper.');
        }

        const batches = initData.batches || [];
        const totalBatches = Math.max(1, batches.length);
        let generatedTotal = 0;

        for (let i = 0; i < batches.length; i++) {
            const batch = batches[i];
            const pct = Math.round(((i + 0.2) / totalBatches) * 90) + 5;
            progBar.style.width = pct + '%';
            progPct.textContent = pct + '%';
            progStatus.textContent = `Generating ${batch.subject_name} (${batch.count} questions) — batch ${i + 1} of ${totalBatches}...`;

            const batchUrl = initData.batch_url || ('{{ route("admin.question-papers.index", [], false) }}/' + initData.paper_id + '/ai/batch');
            let batchSuccess = false;
            let lastBatchError = '';

            // Retry individual batch up to 2 times automatically
            for (let retry = 0; retry < 2; retry++) {
                try {
                    const batchRes = await fetch(batchUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify({
                            _token: csrfToken,
                            exam_key: payload.exam_key,
                            subject_name: batch.subject_name,
                            chapter_name: payload.chapter_name,
                            count: batch.count,
                            difficulty: payload.difficulty,
                            mode: payload.mode,
                            exam_year: payload.exam_year,
                            custom_prompt: payload.custom_prompt,
                            provider: payload.provider,
                        }),
                    });

                    const batchText = await batchRes.text();
                    let batchData = null;
                    try {
                        batchData = JSON.parse(batchText);
                    } catch (pe) {
                        console.error('Non-JSON batch response:', batchText);
                        if (batchRes.status === 419) {
                            throw new Error('Session expired (CSRF). Please refresh the page (F5).');
                        }
                    }

                    if (batchRes.ok && batchData && batchData.success) {
                        generatedTotal = batchData.total_questions || (generatedTotal + batch.count);
                        batchSuccess = true;
                        break;
                    } else {
                        lastBatchError = (batchData && batchData.message) ? batchData.message : `HTTP ${batchRes.status}`;
                    }
                } catch (e) {
                    lastBatchError = e.message;
                }

                if (retry === 0) {
                    progStatus.textContent = `Retrying ${batch.subject_name}... (attempt 2)`;
                    await new Promise(r => setTimeout(r, 1200));
                }
            }

            if (!batchSuccess) {
                // If previous batches already saved questions, don't crash the entire paper
                if (generatedTotal > 0) {
                    progBar.style.width = '100%';
                    progPct.textContent = '100%';
                    progStatus.textContent = `Generated ${generatedTotal} questions! Opening paper...`;
                    window.location.href = initData.paper_url;
                    return;
                }
                throw new Error(lastBatchError || `Failed generating ${batch.subject_name} batch.`);
            }
        }

        progBar.style.width = '100%';
        progBar.classList.remove('progress-bar-animated');
        progBar.classList.replace('bg-primary', 'bg-success');
        progPct.textContent = '100%';
        progStatus.textContent = `Completed! ${generatedTotal} questions saved. Opening paper...`;

        window.location.href = initData.paper_url;
    } catch (err) {
        progBox.style.display = 'none';
        errBox.textContent = err.message || 'An unexpected error occurred during AI generation.';
        errBox.style.display = 'block';
        btn.disabled = false;
        btn.innerHTML = origBtnHtml;
    }
}

function openModalById(id) {
    const el = document.getElementById(id);
    if (!el) return;
    if (window.bootstrap && window.bootstrap.Modal) {
        new window.bootstrap.Modal(el).show();
    } else if (window.jQuery && typeof window.jQuery(el).modal === 'function') {
        window.jQuery(el).modal('show');
    }
}

document.addEventListener('DOMContentLoaded', function () {
    syncAiExamSubjects();
    toggleAiPaperTypeFields();
    toggleAiModeFields();

    if (window.location.hash === '#ai-create') {
        openModalById('aiCreatePaperModal');
    } else if (window.location.hash === '#create') {
        openModalById('createPaperModal');
    }
});
</script>

<script>
function toggleSubjectSection(groupId, iconId) {
    const group = document.getElementById(groupId);
    const icon = document.getElementById(iconId);
    if (!group) return;
    if (group.style.display === 'none') {
        group.style.display = 'flex';
        if (icon) {
            icon.className = 'icon-arrow-down fw-bold text-dark';
        }
    } else {
        group.style.display = 'none';
        if (icon) {
            icon.className = 'icon-arrow-right fw-bold text-dark';
        }
    }
}

function switchPaperTab(tab) {
    const btnMock = document.getElementById('btn-tab-mocktest');
    const btnComb = document.getElementById('btn-tab-combined');
    const paneMock = document.getElementById('pane-mocktest');
    const paneComb = document.getElementById('pane-combined');

    if (tab === 'mocktest') {
        btnMock.classList.add('active');
        btnComb.classList.remove('active');
        paneMock.style.display = 'block';
        paneComb.style.display = 'none';
    } else {
        btnComb.classList.add('active');
        btnMock.classList.remove('active');
        paneComb.style.display = 'block';
        paneMock.style.display = 'none';
    }
}

function filterMockSubjects(courseId) {
    const select = document.getElementById('mock_subject_id');
    const options = select.querySelectorAll('option');
    options.forEach(opt => {
        if (!opt.value) return;
        const optCourseId = opt.getAttribute('data-course-id');
        if (!courseId || optCourseId == courseId) {
            opt.style.display = '';
        } else {
            opt.style.display = 'none';
        }
    });
}

function loadMockChapters(subjectId) {
    const select = document.getElementById('mock_chapter_id');
    if (!select) return;
    select.innerHTML = '<option value="">-- All Chapters / Full Subject --</option>';
    if (!subjectId) return;

    fetch('{{ url("admin/chapters/by-subject") }}/' + subjectId)
        .then(response => response.json())
        .then(data => {
            data.forEach(ch => {
                const opt = document.createElement('option');
                opt.value = ch.id;
                opt.textContent = ch.title;
                select.appendChild(opt);
            });
        })
        .catch(err => console.error('Error loading chapters:', err));
}
</script>
@endsection

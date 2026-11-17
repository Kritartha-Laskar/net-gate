<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default AI Provider
    |--------------------------------------------------------------------------
    | Supported providers: "gemini", "openai"
    */
    'provider' => env('AI_PROVIDER', 'gemini'),

    'timeout' => (int) env('AI_TIMEOUT', 120),

    'providers' => [
        'gemini' => [
            'label'    => 'Google Gemini',
            'api_key'  => env('GEMINI_API_KEY'),
            'model'    => env('GEMINI_MODEL', 'gemini-2.5-flash'),
            'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        ],
        'openai' => [
            'label'    => 'OpenAI',
            'api_key'  => env('OPENAI_API_KEY'),
            'model'    => env('OPENAI_MODEL', 'gpt-4o-mini'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | CSE Exam Presets (GATE CSE & UGC NET CSE)
    |--------------------------------------------------------------------------
    */
    'exams' => [
        'gate_cse' => [
            'course_name'        => 'GATE CSE',
            'exam_name'          => 'GATE CSE',
            'duration_minutes'   => 180,
            'default_questions'  => 65,
            'default_marks'      => 100,
            'default_q_marks'    => 2,
            'marking_note'       => '1-mark & 2-mark MCQ/MSQ/NAT (negative marking -1/3 or -2/3 for MCQs)',
            'subjects'           => [
                'General Aptitude' => [
                    'weight'   => 15,
                    'chapters' => ['Verbal Aptitude', 'Quantitative Aptitude', 'Analytical & Spatial Aptitude'],
                ],
                'Engineering Mathematics' => [
                    'weight'   => 13,
                    'chapters' => ['Linear Algebra', 'Calculus', 'Probability and Statistics'],
                ],
                'Discrete Mathematics' => [
                    'weight'   => 10,
                    'chapters' => ['Propositional & First-Order Logic', 'Sets, Relations & Functions', 'Graph Theory & Combinatorics'],
                ],
                'Digital Logic' => [
                    'weight'   => 5,
                    'chapters' => ['Boolean Algebra & Minimization', 'Combinational & Sequential Circuits', 'Number Representations'],
                ],
                'Computer Organization and Architecture' => [
                    'weight'   => 8,
                    'chapters' => ['Instructions, Addressing & ALU', 'Instruction Pipelining', 'Memory Hierarchy & Cache'],
                ],
                'Programming and Data Structures' => [
                    'weight'   => 10,
                    'chapters' => ['C Programming & Recursion', 'Stacks, Queues & Linked Lists', 'Trees, BST & Heaps'],
                ],
                'Algorithms' => [
                    'weight'   => 10,
                    'chapters' => ['Asymptotic Analysis & Recurrences', 'Greedy & Dynamic Programming', 'Graph Algorithms & Shortest Paths'],
                ],
                'Theory of Computation' => [
                    'weight'   => 8,
                    'chapters' => ['Regular Languages & Finite Automata', 'Context-Free Grammars & PDA', 'Turing Machines & Undecidability'],
                ],
                'Compiler Design' => [
                    'weight'   => 4,
                    'chapters' => ['Lexical Analysis & Parsing (LL/LR)', 'Syntax-Directed Translation', 'Code Generation & Optimization'],
                ],
                'Operating System' => [
                    'weight'   => 9,
                    'chapters' => ['Processes, Threads & CPU Scheduling', 'Synchronization & Deadlocks', 'Virtual Memory & File Systems'],
                ],
                'Databases' => [
                    'weight'   => 9,
                    'chapters' => ['ER Model, Relational Algebra & SQL', 'Normalization & Functional Dependencies', 'Transactions, Concurrency & B+ Trees'],
                ],
                'Computer Networks' => [
                    'weight'   => 9,
                    'chapters' => ['Data Link Layer & MAC', 'IPv4 Addressing, Subnetting & Routing', 'TCP/UDP, Congestion Control & Application Layer'],
                ],
            ],
        ],

        'net_cse' => [
            'course_name'        => 'UGC NET CSE',
            'exam_name'          => 'UGC NET CSE',
            'duration_minutes'   => 180,
            'default_questions'  => 100,
            'default_marks'      => 200,
            'default_q_marks'    => 2,
            'marking_note'       => '2 marks per question, no negative marking (Paper 1 & Paper 2 CSE)',
            'subjects'           => [
                'Teaching & Research Aptitude (Paper 1)' => [
                    'weight'   => 15,
                    'chapters' => ['Teaching & Research Aptitude', 'Logical & Mathematical Reasoning', 'ICT & Higher Education System'],
                ],
                'Discrete Structures & Optimization' => [
                    'weight'   => 9,
                    'chapters' => ['Mathematical Logic & Sets', 'Graph Theory & Boolean Algebra', 'Linear Programming & Optimization'],
                ],
                'Computer System Architecture' => [
                    'weight'   => 8,
                    'chapters' => ['Digital Logic & Microoperations', 'Pipelining & Vector Processing', 'Memory & I/O Organization'],
                ],
                'Programming Languages & Computer Graphics' => [
                    'weight'   => 8,
                    'chapters' => ['C/C++/OOP & Web Technologies', '2D/3D Transformations & Viewing', 'Clipping & Shading Algorithms'],
                ],
                'Database Management Systems' => [
                    'weight'   => 10,
                    'chapters' => ['Relational Model, SQL & PL/SQL', 'Normalization & Query Optimization', 'Transactions, NoSQL & Data Warehousing'],
                ],
                'System Software & Operating System' => [
                    'weight'   => 10,
                    'chapters' => ['CPU Scheduling & Deadlocks', 'Memory & Disk Management', 'Assemblers, Linkers & Compilers'],
                ],
                'Software Engineering' => [
                    'weight'   => 9,
                    'chapters' => ['SDLC Models & Agile', 'Software Testing & Metrics', 'COCOMO, Risk & Quality Management'],
                ],
                'Data Structures & Algorithms' => [
                    'weight'   => 10,
                    'chapters' => ['Trees, Graphs & Hashing', 'Sorting, Searching & Greedy', 'Dynamic Programming & NP-Completeness'],
                ],
                'Theory of Computation & Compilers' => [
                    'weight'   => 7,
                    'chapters' => ['Automata & Regular Languages', 'CFG, Chomsky Hierarchy & Decidability', 'Parsing & Syntax-Directed Translation'],
                ],
                'Data Communication & Computer Networks' => [
                    'weight'   => 8,
                    'chapters' => ['OSI/TCP-IP & Modulation', 'MAC, IP Subnetting & Routing', 'Transport Protocols & Network Security'],
                ],
                'Artificial Intelligence' => [
                    'weight'   => 6,
                    'chapters' => ['Search Strategies (A*, Minimax)', 'Knowledge Representation & Fuzzy Logic', 'Machine Learning, NLP & Neural Networks'],
                ],
            ],
        ],
    ],
];


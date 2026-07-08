@php
    $page = $page ?? 'dashboard';

    $navItems = [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'DB', 'route' => 'dashboard'],
        ['key' => 'students', 'label' => 'Students', 'icon' => 'ST', 'route' => 'students'],
        ['key' => 'scanner', 'label' => 'QR Scanner', 'icon' => 'QR', 'route' => 'scanner'],
        ['key' => 'records', 'label' => 'Attendance Records', 'icon' => 'AR', 'route' => 'records'],
        ['key' => 'schedule', 'label' => 'Event Schedule', 'icon' => 'EV', 'route' => 'schedule'],
        ['key' => 'booths', 'label' => 'Booths', 'icon' => 'BH', 'route' => 'booths'],
    ];

    $stats = [
        ['label' => 'Total Students', 'value' => '1,248', 'change' => '+36 this month', 'tone' => 'bg-emerald-50 text-emerald-700'],
        ['label' => 'Present Today', 'value' => '1,084', 'change' => '86.9% attendance', 'tone' => 'bg-sky-50 text-sky-700'],
        ['label' => 'Late Entries', 'value' => '42', 'change' => '-8 vs yesterday', 'tone' => 'bg-amber-50 text-amber-700'],
        ['label' => 'Active Booths', 'value' => '8', 'change' => '2 need review', 'tone' => 'bg-rose-50 text-rose-700'],
    ];

    $students = $students ?? collect();
    $editStudent = $editStudent ?? null;
    $yearLevels = ['1st Year', '2nd Year', '3rd Year', '4th Year', '5th Year'];
    $filters = array_merge(['course' => null, 'block' => null, 'year_level' => null, 'search' => null], $filters ?? []);
    $filterOptions = $filterOptions ?? ['courses' => collect(), 'blocks' => collect(), 'year_levels' => collect($yearLevels)];
    $activeFilters = collect($filters)->filter(fn ($value) => filled($value))->all();
    $studentsFilteredUrl = route('students', $activeFilters);
    $studentsQrPrintUrl = route('students.qr-print', $activeFilters);

    $records = [
        ['date' => 'July 8, 2026', 'booth' => 'Main Gate', 'checked' => '682', 'rate' => '91%'],
        ['date' => 'July 8, 2026', 'booth' => 'Library Entrance', 'checked' => '214', 'rate' => '84%'],
        ['date' => 'July 8, 2026', 'booth' => 'Science Hall', 'checked' => '188', 'rate' => '79%'],
    ];

    $events = $events ?? collect();
    $editEvent = $editEvent ?? null;

    $booths = [
        ['name' => 'Main Gate', 'status' => 'Online', 'scans' => '682', 'operator' => 'Admin Cruz'],
        ['name' => 'Library Entrance', 'status' => 'Online', 'scans' => '214', 'operator' => 'Admin Ford'],
        ['name' => 'Science Hall', 'status' => 'Review', 'scans' => '188', 'operator' => 'Admin Lim'],
        ['name' => 'Gym Lobby', 'status' => 'Offline', 'scans' => '0', 'operator' => 'Unassigned'],
    ];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SAMSqr Admin</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
    <style>
        .modal-overlay {
            display: none;
        }

        .modal-overlay:target,
        .modal-overlay.is-open {
            display: grid;
        }
    </style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-zinc-100 font-sans text-zinc-950">
    <div class="min-h-screen lg:grid lg:grid-cols-[280px_1fr]">
        <aside class="border-b border-zinc-200 bg-white lg:min-h-screen lg:border-b-0 lg:border-r">
            <div class="flex h-full flex-col gap-6 p-5">
                <div class="flex items-center gap-3">
                    <div class="grid h-11 w-11 place-items-center rounded-lg bg-zinc-950 text-lg text-white">QR</div>
                    <div>
                        <p class="text-base font-bold leading-tight">SAMSqr</p>
                        <p class="text-sm text-zinc-500">Attendance Admin</p>
                    </div>
                </div>

                <nav class="grid gap-1">
                    @foreach ($navItems as $item)
                        <a
                            href="{{ route($item['route']) }}"
                            class="flex items-center gap-3 rounded-lg px-3 py-3 text-sm font-semibold transition {{ $page === $item['key'] ? 'bg-zinc-950 text-white shadow-sm' : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-950' }}"
                        >
                            <span class="grid h-7 w-7 place-items-center rounded-md bg-zinc-100 text-[11px] font-bold text-zinc-700 {{ $page === $item['key'] ? 'bg-white/15 text-white' : '' }}">{{ $item['icon'] }}</span>
                            <span>{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                </nav>

                <div class="mt-auto rounded-lg border border-zinc-200 bg-zinc-50 p-4">
                    <p class="text-sm font-semibold">Today</p>
                    <p class="mt-1 text-2xl font-bold">86.9%</p>
                    <p class="mt-1 text-sm text-zinc-500">Campus attendance rate</p>
                </div>
            </div>
        </aside>

        <main class="p-4 sm:p-6 lg:p-8">
            <header class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-zinc-500">Student Attendance Monitoring System</p>
                    <h1 class="mt-1 text-2xl font-bold sm:text-3xl">
                        {{ collect($navItems)->firstWhere('key', $page)['label'] ?? 'Dashboard' }}
                    </h1>
                </div>
                <div class="flex items-center gap-3">
                    <button class="rounded-lg border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-700 shadow-sm hover:bg-zinc-50">Export</button>
                    @if ($page === 'students')
                        <a href="#import-students-modal" class="rounded-lg border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-700 shadow-sm hover:bg-zinc-50">Import</a>
                        <a href="#add-student-modal" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">Add Record</a>
                    @else
                        <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">Add Record</button>
                    @endif
                </div>
            </header>

            @if ($page === 'dashboard')
                <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach ($stats as $stat)
                        <article class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm">
                            <div class="flex items-start justify-between gap-4">
                                <p class="text-sm font-semibold text-zinc-500">{{ $stat['label'] }}</p>
                                <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $stat['tone'] }}">{{ $stat['change'] }}</span>
                            </div>
                            <p class="mt-4 text-3xl font-bold">{{ $stat['value'] }}</p>
                        </article>
                    @endforeach
                </section>

                <section class="mt-6 grid gap-6 xl:grid-cols-[1.35fr_.65fr]">
                    <article class="rounded-lg border border-zinc-200 bg-white shadow-sm">
                        <div class="border-b border-zinc-200 p-5">
                            <h2 class="font-bold">Live Attendance Flow</h2>
                        </div>
                        <div class="grid h-72 content-end gap-3 p-5">
                            @foreach ([82, 58, 91, 76, 88, 67, 94] as $height)
                                <div class="flex items-end gap-3">
                                    <span class="w-16 text-xs font-semibold text-zinc-500">{{ 6 + $loop->index }} AM</span>
                                    <div class="h-7 flex-1 rounded bg-zinc-100">
                                        <div class="h-7 rounded bg-emerald-500" style="width: {{ $height }}%"></div>
                                    </div>
                                    <span class="w-10 text-right text-xs font-semibold text-zinc-500">{{ $height }}%</span>
                                </div>
                            @endforeach
                        </div>
                    </article>

                    <article class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm">
                        <h2 class="font-bold">QR Scanner Status</h2>
                        <div class="mt-5 grid aspect-square place-items-center rounded-lg border-2 border-dashed border-zinc-300 bg-zinc-50">
                            <div class="text-center">
                                <div class="mx-auto grid h-20 w-20 place-items-center rounded-lg bg-zinc-950 text-2xl font-bold text-white">QR</div>
                                <p class="mt-4 font-semibold">Ready to scan</p>
                                <p class="text-sm text-zinc-500">8 booths connected</p>
                            </div>
                        </div>
                    </article>
                </section>
            @endif

            @if ($page === 'students')
                <section class="rounded-lg border border-zinc-200 bg-white shadow-sm">
                    <div class="flex flex-col gap-3 border-b border-zinc-200 p-5 sm:flex-row sm:items-center sm:justify-between">
                        <h2 class="font-bold">Student List</h2>
                        <input form="student-filter-form" name="search" value="{{ $filters['search'] }}" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-emerald-500" placeholder="Search students">
                    </div>

                    @if (session('student_status'))
                        <div class="border-b border-emerald-100 bg-emerald-50 px-5 py-3 text-sm font-semibold text-emerald-700">
                            {{ session('student_status') }}
                        </div>
                    @endif

                    @if (session('student_import_error'))
                        <div class="border-b border-rose-100 bg-rose-50 px-5 py-3 text-sm font-semibold text-rose-700">
                            {{ session('student_import_error') }}
                        </div>
                    @endif

                    @if ($errors->any() && ! $editStudent)
                        <div class="border-b border-rose-100 bg-rose-50 px-5 py-3 text-sm font-semibold text-rose-700">
                            Please check the student details and try again.
                        </div>
                    @endif

                    <form id="student-filter-form" method="GET" action="{{ route('students') }}" class="grid gap-3 border-b border-zinc-200 p-5 md:grid-cols-4">
                        <label class="grid gap-1 text-sm font-semibold text-zinc-700">
                            Course
                            <select name="course" class="rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500">
                                <option value="">All courses</option>
                                @foreach ($filterOptions['courses'] as $course)
                                    <option value="{{ $course }}" @selected(($filters['course'] ?? null) === $course)>{{ $course }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="grid gap-1 text-sm font-semibold text-zinc-700">
                            Block
                            <select name="block" class="rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500">
                                <option value="">All blocks</option>
                                @foreach ($filterOptions['blocks'] as $block)
                                    <option value="{{ $block }}" @selected(($filters['block'] ?? null) === $block)>{{ $block }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="grid gap-1 text-sm font-semibold text-zinc-700">
                            Year Level
                            <select name="year_level" class="rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500">
                                <option value="">All year levels</option>
                                @foreach ($filterOptions['year_levels'] as $yearLevel)
                                    <option value="{{ $yearLevel }}" @selected(($filters['year_level'] ?? null) === $yearLevel)>{{ $yearLevel }}</option>
                                @endforeach
                            </select>
                        </label>

                        <div class="flex items-end gap-2">
                            <button class="rounded-lg bg-zinc-950 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-zinc-800">Apply</button>
                            <a href="{{ route('students') }}" class="rounded-lg border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-700 shadow-sm hover:bg-zinc-50">Reset</a>
                            @if (count($activeFilters) > 0 && $students->isNotEmpty())
                                <a href="{{ $studentsQrPrintUrl }}" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700 shadow-sm hover:bg-emerald-100">Print QR</a>
                            @endif
                        </div>
                    </form>

                    @if ($editStudent)
                        <form
                            id="student-form"
                            method="POST"
                            action="{{ route('students.update', $editStudent) }}"
                            class="scroll-mt-6 grid gap-4 border-b border-zinc-200 bg-emerald-50/50 p-5 lg:grid-cols-3"
                        >
                            @csrf
                            @method('PATCH')

                            <div class="lg:col-span-3">
                                <h3 class="font-bold">Editing Student</h3>
                                <p class="mt-1 text-sm text-emerald-700">Update the details below, then click Update Student.</p>
                            </div>

                            <label class="grid gap-1 text-sm font-semibold text-zinc-700">
                                Student ID
                                <input name="student_id" value="{{ old('student_id', $editStudent->student_id) }}" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500" required>
                                @error('student_id') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                            </label>

                            <label class="grid gap-1 text-sm font-semibold text-zinc-700">
                                Last Name
                                <input name="last_name" value="{{ old('last_name', $editStudent->last_name) }}" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500" required>
                                @error('last_name') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                            </label>

                            <label class="grid gap-1 text-sm font-semibold text-zinc-700">
                                First Name
                                <input name="first_name" value="{{ old('first_name', $editStudent->first_name) }}" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500" required>
                                @error('first_name') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                            </label>

                            <label class="grid gap-1 text-sm font-semibold text-zinc-700">
                                Course
                                <input name="course" value="{{ old('course', $editStudent->course) }}" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500" required>
                                @error('course') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                            </label>

                            <label class="grid gap-1 text-sm font-semibold text-zinc-700">
                                Block
                                <input name="block" value="{{ old('block', $editStudent->block) }}" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500" required>
                                @error('block') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                            </label>

                            <label class="grid gap-1 text-sm font-semibold text-zinc-700">
                                Year Level
                                <select name="year_level" class="rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500" required>
                                    @foreach ($yearLevels as $yearLevel)
                                        <option value="{{ $yearLevel }}" @selected(old('year_level', $editStudent->year_level) === $yearLevel)>{{ $yearLevel }}</option>
                                    @endforeach
                                </select>
                                @error('year_level') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                            </label>

                            <div class="flex flex-wrap items-center gap-3 lg:col-span-3">
                                <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">Update Student</button>
                                <a href="{{ route('students') }}" class="rounded-lg border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-700 shadow-sm hover:bg-zinc-50">Cancel Edit</a>
                            </div>
                        </form>
                    @endif

                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[920px] text-left text-sm">
                            <thead class="bg-zinc-50 text-xs uppercase text-zinc-500">
                                <tr>
                                    <th class="px-5 py-3">Student ID</th>
                                    <th class="px-5 py-3">Last Name</th>
                                    <th class="px-5 py-3">First Name</th>
                                    <th class="px-5 py-3">Course</th>
                                    <th class="px-5 py-3">Block</th>
                                    <th class="px-5 py-3">Year Level</th>
                                    <th class="px-5 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100">
                                @forelse ($students as $student)
                                    <tr>
                                        <td class="px-5 py-4 font-semibold">{{ $student['student_id'] }}</td>
                                        <td class="px-5 py-4 text-zinc-600">{{ $student['last_name'] }}</td>
                                        <td class="px-5 py-4 text-zinc-600">{{ $student['first_name'] }}</td>
                                        <td class="px-5 py-4 text-zinc-600">{{ $student['course'] }}</td>
                                        <td class="px-5 py-4 text-zinc-600">{{ $student['block'] }}</td>
                                        <td class="px-5 py-4 text-zinc-600">{{ $student['year_level'] }}</td>
                                        <td class="px-5 py-4">
                                            <div class="flex justify-end gap-2">
                                                <a href="#student-qr-modal-{{ $student['id'] }}" class="rounded-lg border border-zinc-300 px-3 py-2 text-xs font-semibold text-zinc-700 hover:bg-zinc-50">QR</a>
                                                <a href="{{ route('students', ['edit' => $student['id']]) }}#student-form" class="rounded-lg border border-zinc-300 px-3 py-2 text-xs font-semibold text-zinc-700 hover:bg-zinc-50">Edit</a>
                                                <form method="POST" action="{{ route('students.destroy', $student['id']) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="rounded-lg border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-50">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-5 py-4 text-sm text-zinc-500">No students yet. Use Add Record to add the first student.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="flex flex-col gap-3 border-t border-zinc-200 p-5 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-sm text-zinc-500"><span class="font-semibold text-zinc-800">{{ $students->count() }}</span> student record(s) match the current filters.</p>
                        @if (count($activeFilters) > 0 && $students->isNotEmpty())
                            <a href="{{ $studentsQrPrintUrl }}" class="rounded-lg bg-zinc-950 px-4 py-2 text-center text-sm font-semibold text-white shadow-sm hover:bg-zinc-800">Print QR</a>
                        @endif
                    </div>
                </section>
            @endif

            @if ($page === 'scanner')
                <section class="grid gap-6 lg:grid-cols-[1fr_360px]">
                    <div class="grid min-h-[520px] place-items-center rounded-lg border border-zinc-200 bg-white p-6 shadow-sm">
                        <div class="w-full max-w-sm rounded-lg border-2 border-dashed border-zinc-300 bg-zinc-50 p-8 text-center">
                            <div class="mx-auto grid h-40 w-40 place-items-center rounded-lg bg-white text-5xl font-bold shadow-inner">QR</div>
                            <h2 class="mt-6 text-xl font-bold">Scan Student QR</h2>
                            <p class="mt-2 text-sm text-zinc-500">Point the scanner at a student QR code to mark attendance.</p>
                        </div>
                    </div>
                    <aside class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm">
                        <h2 class="font-bold">Latest Scan</h2>
                        <dl class="mt-5 grid gap-4 text-sm">
                            <div><dt class="text-zinc-500">Student</dt><dd class="font-semibold">Alyssa Rivera</dd></div>
                            <div><dt class="text-zinc-500">Student ID</dt><dd class="font-semibold">STU-24017</dd></div>
                            <div><dt class="text-zinc-500">Booth</dt><dd class="font-semibold">Main Gate</dd></div>
                            <div><dt class="text-zinc-500">Time</dt><dd class="font-semibold">7:42 AM</dd></div>
                        </dl>
                    </aside>
                </section>
            @endif

            @if ($page === 'records')
                <section class="grid gap-4">
                    @foreach ($records as $record)
                        <article class="grid gap-3 rounded-lg border border-zinc-200 bg-white p-5 shadow-sm sm:grid-cols-4 sm:items-center">
                            <p class="font-bold">{{ $record['date'] }}</p>
                            <p class="text-zinc-600">{{ $record['booth'] }}</p>
                            <p class="text-zinc-600">{{ $record['checked'] }} checked in</p>
                            <p class="text-left font-bold text-emerald-700 sm:text-right">{{ $record['rate'] }}</p>
                        </article>
                    @endforeach
                </section>
            @endif

            @if ($page === 'schedule')
                <section class="grid gap-6 xl:grid-cols-[1fr_360px]">
                    <div class="rounded-lg border border-zinc-200 bg-white shadow-sm">
                        <div class="flex flex-col gap-3 border-b border-zinc-200 p-5 sm:flex-row sm:items-center sm:justify-between">
                            <h2 class="font-bold">Scheduled Events</h2>
                            <select class="rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm font-semibold text-zinc-700 outline-none focus:border-emerald-500">
                                <option>All events</option>
                                <option>Today</option>
                                <option>This week</option>
                            </select>
                        </div>

                        @if (session('status'))
                            <div class="border-b border-emerald-100 bg-emerald-50 px-5 py-3 text-sm font-semibold text-emerald-700">
                                {{ session('status') }}
                            </div>
                        @endif

                        @if ($errors->any())
                            <div class="border-b border-rose-100 bg-rose-50 px-5 py-3 text-sm font-semibold text-rose-700">
                                Please check the event details and try again.
                            </div>
                        @endif

                        <form
                            id="event-form"
                            method="POST"
                            action="{{ $editEvent ? route('schedule.update', $editEvent) : route('schedule.store') }}"
                            class="scroll-mt-6 grid gap-4 border-b border-zinc-200 p-5 lg:grid-cols-2 {{ $editEvent ? 'bg-emerald-50/50' : '' }}"
                        >
                            @csrf
                            @if ($editEvent)
                                @method('PATCH')
                            @endif

                            <div class="lg:col-span-2">
                                <h3 class="font-bold">{{ $editEvent ? 'Editing Event' : 'Add Event' }}</h3>
                                @if ($editEvent)
                                    <p class="mt-1 text-sm text-emerald-700">Update the details below, then click Update Event.</p>
                                @endif
                            </div>

                            <label class="grid gap-1 text-sm font-semibold text-zinc-700 lg:col-span-2">
                                Event Title
                                <input name="title" value="{{ old('title', $editEvent?->title) }}" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500" required>
                                @error('title') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                            </label>

                            <label class="grid gap-1 text-sm font-semibold text-zinc-700">
                                Date
                                <input type="date" name="event_date" value="{{ old('event_date', $editEvent?->event_date?->format('Y-m-d')) }}" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500" required>
                                @error('event_date') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                            </label>

                            <label class="grid gap-1 text-sm font-semibold text-zinc-700">
                                Status
                                <select name="status" class="rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500" required>
                                    @foreach (['Scheduled', 'Upcoming', 'In Progress', 'Completed', 'Cancelled'] as $status)
                                        <option value="{{ $status }}" @selected(old('status', $editEvent?->status ?? 'Scheduled') === $status)>{{ $status }}</option>
                                    @endforeach
                                </select>
                                @error('status') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                            </label>

                            <label class="grid gap-1 text-sm font-semibold text-zinc-700">
                                Start Time
                                <input type="time" name="start_time" value="{{ old('start_time', $editEvent?->start_time) }}" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500" required>
                                @error('start_time') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                            </label>

                            <label class="grid gap-1 text-sm font-semibold text-zinc-700">
                                End Time
                                <input type="time" name="end_time" value="{{ old('end_time', $editEvent?->end_time) }}" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500" required>
                                @error('end_time') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                            </label>

                            <label class="grid gap-1 text-sm font-semibold text-zinc-700">
                                Venue
                                <input name="venue" value="{{ old('venue', $editEvent?->venue) }}" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500" required>
                                @error('venue') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                            </label>

                            <div class="flex flex-wrap items-center gap-3 lg:col-span-2">
                                <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">
                                    {{ $editEvent ? 'Update Event' : 'Save Event' }}
                                </button>
                                @if ($editEvent)
                                    <a href="{{ route('schedule') }}" class="rounded-lg border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-700 shadow-sm hover:bg-zinc-50">Cancel Edit</a>
                                @endif
                            </div>
                        </form>

                        <div class="grid divide-y divide-zinc-100">
                            @forelse ($events as $event)
                                <article class="grid gap-4 p-5 md:grid-cols-[160px_1fr_150px] md:items-center">
                                    <div>
                                        <p class="text-sm font-bold text-zinc-950">{{ $event['date'] }}</p>
                                        <p class="mt-1 text-sm text-zinc-500">{{ $event['time'] }}</p>
                                    </div>
                                    <div>
                                        <h3 class="font-bold">{{ $event['title'] }}</h3>
                                        <p class="mt-1 text-sm text-zinc-500">{{ $event['venue'] }}</p>
                                    </div>
                                    <div class="grid gap-3 md:text-right">
                                        <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $event['status'] === 'In Progress' ? 'bg-emerald-50 text-emerald-700' : ($event['status'] === 'Upcoming' ? 'bg-sky-50 text-sky-700' : 'bg-zinc-100 text-zinc-600') }}">{{ $event['status'] }}</span>
                                        <div class="flex gap-2 md:justify-end">
                                            <a href="{{ route('schedule', ['edit' => $event['id']]) }}#event-form" class="rounded-lg border border-zinc-300 px-3 py-2 text-xs font-semibold text-zinc-700 hover:bg-zinc-50">Edit</a>
                                            <form method="POST" action="{{ route('schedule.destroy', $event['id']) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button class="rounded-lg border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-50">Delete</button>
                                            </form>
                                        </div>
                                    </div>
                                </article>
                            @empty
                                <div class="p-5 text-sm text-zinc-500">No event schedules yet. Add the first event above.</div>
                            @endforelse
                        </div>
                    </div>

                    <aside class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm">
                        <h2 class="font-bold">Today Timeline</h2>
                        <div class="mt-5 grid gap-4">
                            @forelse ($events->take(3) as $event)
                                <div class="grid grid-cols-[72px_1fr] gap-3">
                                    <p class="text-sm font-semibold text-zinc-500">{{ explode(' - ', $event['time'])[0] }}</p>
                                    <div class="border-l-2 border-emerald-500 pl-4">
                                        <p class="text-sm font-bold">{{ $event['title'] }}</p>
                                        <p class="text-xs text-zinc-500">{{ $event['venue'] }}</p>
                                        <div class="mt-3 flex gap-2">
                                            <a href="{{ route('schedule', ['edit' => $event['id']]) }}#event-form" class="rounded-lg border border-zinc-300 px-3 py-1.5 text-xs font-semibold text-zinc-700 hover:bg-zinc-50">Edit</a>
                                            <form method="POST" action="{{ route('schedule.destroy', $event['id']) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button class="rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50">Delete</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <p class="text-sm text-zinc-500">Saved events will appear here.</p>
                            @endforelse
                        </div>
                    </aside>
                </section>
            @endif

            @if ($page === 'booths')
                <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    @foreach ($booths as $booth)
                        <article class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm">
                            <div class="flex items-center justify-between gap-3">
                                <h2 class="font-bold">{{ $booth['name'] }}</h2>
                                <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $booth['status'] === 'Online' ? 'bg-emerald-50 text-emerald-700' : ($booth['status'] === 'Review' ? 'bg-amber-50 text-amber-700' : 'bg-zinc-100 text-zinc-600') }}">{{ $booth['status'] }}</span>
                            </div>
                            <p class="mt-5 text-3xl font-bold">{{ $booth['scans'] }}</p>
                            <p class="text-sm text-zinc-500">Scans today</p>
                            <p class="mt-4 text-sm font-semibold text-zinc-700">{{ $booth['operator'] }}</p>
                        </article>
                    @endforeach
                </section>
            @endif
        </main>
    </div>

    @if ($page === 'students')
        @foreach ($students as $student)
            @php
                $studentName = trim($student['first_name'].' '.$student['last_name']);
                $studentQrPayload = json_encode([
                    'type' => 'student',
                    'student_id' => $student['student_id'],
                    'name' => $studentName,
                    'course' => $student['course'],
                    'block' => $student['block'],
                    'year_level' => $student['year_level'],
                ], JSON_UNESCAPED_SLASHES);
                $studentQrImageUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=260x260&data='.urlencode($studentQrPayload);
            @endphp

            <div id="student-qr-modal-{{ $student['id'] }}" class="modal-overlay fixed inset-0 z-50 place-items-center bg-zinc-950/55 p-4">
                <div class="w-full max-w-xl rounded-lg bg-white shadow-xl">
                    <div class="flex items-center justify-between border-b border-zinc-200 p-5">
                        <div>
                            <h2 class="font-bold">Student QR</h2>
                            <p class="mt-1 text-sm text-zinc-500">{{ $studentName }} - {{ $student['student_id'] }}</p>
                        </div>
                        <a href="{{ $studentsFilteredUrl }}" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-semibold text-zinc-700 hover:bg-zinc-50">Close</a>
                    </div>

                    <div class="grid gap-5 p-5 sm:grid-cols-[280px_1fr]">
                        <div class="grid place-items-center rounded-lg border border-zinc-200 bg-zinc-50 p-4">
                            <img src="{{ $studentQrImageUrl }}" alt="QR code for {{ $studentName }}" class="h-64 w-64 rounded-lg bg-white p-2">
                        </div>
                        <dl class="grid content-start gap-3 text-sm">
                            <div><dt class="font-semibold text-zinc-500">Student ID</dt><dd class="mt-1 font-bold text-zinc-950">{{ $student['student_id'] }}</dd></div>
                            <div><dt class="font-semibold text-zinc-500">Name</dt><dd class="mt-1 text-zinc-700">{{ $studentName }}</dd></div>
                            <div><dt class="font-semibold text-zinc-500">Course</dt><dd class="mt-1 text-zinc-700">{{ $student['course'] }}</dd></div>
                            <div><dt class="font-semibold text-zinc-500">Block / Year</dt><dd class="mt-1 text-zinc-700">{{ $student['block'] }} - {{ $student['year_level'] }}</dd></div>
                        </dl>
                    </div>
                </div>
            </div>
        @endforeach

        <div id="import-students-modal" class="modal-overlay fixed inset-0 z-50 place-items-center bg-zinc-950/55 p-4">
            <div class="w-full max-w-2xl rounded-lg bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-zinc-200 p-5">
                    <div>
                        <h2 class="font-bold">Import Students</h2>
                        <p class="mt-1 text-sm text-zinc-500">Upload a CSV or XLSX file.</p>
                    </div>
                    <a href="{{ route('students') }}" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-semibold text-zinc-700 hover:bg-zinc-50">Close</a>
                </div>

                <form method="POST" action="{{ route('students.import') }}" enctype="multipart/form-data" class="grid gap-4 p-5">
                    @csrf

                    <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-4 text-sm text-zinc-600">
                        Required headers: <span class="font-semibold text-zinc-800">student_id, last_name, first_name, course, block, year_level</span>
                    </div>

                    <label class="grid gap-1 text-sm font-semibold text-zinc-700">
                        Import File
                        <input type="file" name="student_file" accept=".csv,.txt,.xlsx" class="rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500" required>
                        @error('student_file') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                    </label>

                    <div class="flex flex-wrap items-center gap-3">
                        <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">Import Records</button>
                        <a href="{{ route('students') }}" class="rounded-lg border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-700 shadow-sm hover:bg-zinc-50">Cancel</a>
                    </div>
                </form>
            </div>
        </div>

        <div id="add-student-modal" class="modal-overlay fixed inset-0 z-50 place-items-center bg-zinc-950/55 p-4 {{ $errors->any() && ! $editStudent ? 'is-open' : '' }}">
            <div class="w-full max-w-4xl rounded-lg bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-zinc-200 p-5">
                    <div>
                        <h2 class="font-bold">Add Student</h2>
                        <p class="mt-1 text-sm text-zinc-500">Create a student profile record.</p>
                    </div>
                    <a href="{{ route('students') }}" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-semibold text-zinc-700 hover:bg-zinc-50">Close</a>
                </div>

                <form method="POST" action="{{ route('students.store') }}" class="grid gap-4 p-5 lg:grid-cols-3">
                    @csrf

                    <label class="grid gap-1 text-sm font-semibold text-zinc-700">
                        Student ID
                        <input name="student_id" value="{{ old('student_id') }}" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500" required>
                        @error('student_id') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                    </label>

                    <label class="grid gap-1 text-sm font-semibold text-zinc-700">
                        Last Name
                        <input name="last_name" value="{{ old('last_name') }}" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500" required>
                        @error('last_name') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                    </label>

                    <label class="grid gap-1 text-sm font-semibold text-zinc-700">
                        First Name
                        <input name="first_name" value="{{ old('first_name') }}" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500" required>
                        @error('first_name') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                    </label>

                    <label class="grid gap-1 text-sm font-semibold text-zinc-700">
                        Course
                        <input name="course" value="{{ old('course') }}" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500" required>
                        @error('course') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                    </label>

                    <label class="grid gap-1 text-sm font-semibold text-zinc-700">
                        Block
                        <input name="block" value="{{ old('block') }}" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500" required>
                        @error('block') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                    </label>

                    <label class="grid gap-1 text-sm font-semibold text-zinc-700">
                        Year Level
                        <select name="year_level" class="rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500" required>
                            <option value="" @selected(old('year_level') === null)>Select year level</option>
                            @foreach ($yearLevels as $yearLevel)
                                <option value="{{ $yearLevel }}" @selected(old('year_level') === $yearLevel)>{{ $yearLevel }}</option>
                            @endforeach
                        </select>
                        @error('year_level') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                    </label>

                    <div class="flex flex-wrap items-center gap-3 lg:col-span-3">
                        <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">Save Student</button>
                        <a href="{{ route('students') }}" class="rounded-lg border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-700 shadow-sm hover:bg-zinc-50">Cancel</a>
                    </div>
                </form>
            </div>
        </div>

    @endif
</body>
</html>

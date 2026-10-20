@php
    $page = $page ?? 'dashboard';

    $navItems = [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'DB', 'route' => 'dashboard'],
        ['key' => 'users', 'label' => 'Users', 'icon' => 'US', 'route' => 'users'],
        ['key' => 'students', 'label' => 'Students', 'icon' => 'ST', 'route' => 'students'],
        ['key' => 'scanner', 'label' => 'QR Scanner', 'icon' => 'QR', 'route' => 'scanner'],
        ['key' => 'records', 'label' => 'Attendance Records', 'icon' => 'AR', 'route' => 'records'],
        ['key' => 'schedule', 'label' => 'Event Schedule', 'icon' => 'EV', 'route' => 'schedule'],
        ['key' => 'booths', 'label' => 'Scanner Operators', 'icon' => 'SO', 'route' => 'booths'],
    ];

    $isBoothUser = auth()->user()?->role === 'booth';

    if ($isBoothUser) {
        $navItems = [
            ['key' => 'scanner', 'label' => 'QR Scanner', 'icon' => 'QR', 'route' => 'scanner'],
        ];
    }

    $stats = $stats ?? [];
    $attendanceFlow = $attendanceFlow ?? collect();
    $activeBooths = $activeBooths ?? 0;

    $students = $students ?? collect();
    $showingInitialStudents = $showingInitialStudents ?? false;
    $editStudent = $editStudent ?? null;
    $yearLevels = ['1st Year', '2nd Year', '3rd Year', '4th Year', '5th Year'];
    $filters = array_merge(['course' => null, 'block' => null, 'year_level' => null, 'search' => null], $filters ?? []);
    $filterOptions = $filterOptions ?? ['courses' => collect(), 'blocks' => collect(), 'year_levels' => collect($yearLevels)];
    $activeFilters = collect($filters)->filter(fn ($value) => filled($value))->all();
    $studentsFilteredUrl = route('students', $activeFilters);

    $records = $records ?? collect();
    $recordFilters = array_merge(['date' => null, 'event_schedule_id' => null], $recordFilters ?? []);
    $recordFilterOptions = $recordFilterOptions ?? ['events' => collect(), 'booths' => collect(), 'students' => collect()];
    $editAttendanceRecord = $editAttendanceRecord ?? null;

    $events = $events ?? collect();
    $editEvent = $editEvent ?? null;

    $booths = $booths ?? collect();
    $editBooth = $editBooth ?? null;
    $scannerBooths = $scannerBooths ?? collect();

    $users = $users ?? collect();
    $editUser = $editUser ?? null;
    $userCounts = array_merge([
        'admin' => $users->where('role', 'admin')->count(),
    ], $userCounts ?? []);
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>OLSHCO Admin</title>
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
                        <p class="text-base font-bold leading-tight">OLSHCO</p>
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

                @auth
                    <div class="mt-auto rounded-lg border border-zinc-200 bg-zinc-50 p-4">
                        <p class="truncate text-sm font-semibold text-zinc-900">{{ auth()->user()->name }}</p>
                        <p class="truncate text-xs text-zinc-500">{{ auth()->user()->email }}</p>
                        <form method="POST" action="{{ route('logout') }}" class="mt-3">
                            @csrf
                            <button type="submit" class="w-full rounded-lg border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-700 shadow-sm hover:bg-zinc-100">Logout</button>
                        </form>
                    </div>
                @endauth
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
                    @if (! $isBoothUser && $page !== 'students')
                        <button class="rounded-lg border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-700 shadow-sm hover:bg-zinc-50">Export</button>
                    @endif
                    @if (! $isBoothUser && $page === 'records')
                        <a href="#add-attendance-modal" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">Add Record</a>
                    @elseif (! $isBoothUser && $page === 'schedule')
                        <a href="#add-event-modal" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">Add Record</a>
                    @elseif (! $isBoothUser && $page === 'booths')
                        <a href="#add-booth-modal" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">Add Record</a>
                    @elseif (! $isBoothUser && $page === 'users')
                        <a href="#add-admin-modal" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">Add Admin</a>
                    @elseif (! $isBoothUser && $page === 'students')
                        <a href="#add-student-modal" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">Add Student</a>
                        <a href="#import-students-modal" class="rounded-lg border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-700 shadow-sm hover:bg-zinc-50">Batch Upload</a>
                    @elseif (! $isBoothUser && ! in_array($page, ['users', 'students'], true))
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
                            @foreach ($attendanceFlow as $flow)
                                <div class="flex items-end gap-3">
                                    <span class="w-16 text-xs font-semibold text-zinc-500">{{ $flow['label'] }}</span>
                                    <div class="h-7 flex-1 rounded bg-zinc-100">
                                        <div class="h-7 rounded bg-emerald-500" style="width: {{ $flow['width'] }}%"></div>
                                    </div>
                                    <span class="w-10 text-right text-xs font-semibold text-zinc-500">{{ $flow['count'] }}</span>
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
                                <p class="text-sm text-zinc-500">{{ $activeBooths }} {{ Str::plural('booth', $activeBooths) }} connected</p>
                            </div>
                        </div>
                    </article>
                </section>
            @endif

            @if ($page === 'users')
                <section class="grid gap-6">
                    <div>
                        <article class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm">
                            <p class="text-sm font-semibold text-zinc-500">Admin Accounts</p>
                            <p class="mt-3 text-3xl font-bold">{{ $userCounts['admin'] }}</p>
                        </article>
                    </div>

                    <section class="rounded-lg border border-zinc-200 bg-white shadow-sm">
                        <div class="border-b border-zinc-200 p-5">
                            <h2 class="font-bold">Admin Accounts</h2>
                        </div>

                        @if (session('user_status'))
                            <div class="border-b border-emerald-100 bg-emerald-50 px-5 py-3 text-sm font-semibold text-emerald-700">
                                {{ session('user_status') }}
                            </div>
                        @endif

                        @if ($errors->any() && $editUser)
                            <div class="border-b border-rose-100 bg-rose-50 px-5 py-3 text-sm font-semibold text-rose-700">
                                Please check the user details and try again.
                            </div>
                        @elseif ($errors->any())
                            <div class="border-b border-rose-100 bg-rose-50 px-5 py-3 text-sm font-semibold text-rose-700">
                                Please check the admin details and try again.
                            </div>
                        @endif

                        @if ($editUser)
                            <form
                                id="user-form"
                                method="POST"
                                action="{{ route('users.update', $editUser) }}"
                                class="scroll-mt-6 grid gap-4 border-b border-zinc-200 bg-emerald-50/50 p-5 lg:grid-cols-4"
                            >
                                @csrf
                                @method('PATCH')

                                <div class="lg:col-span-4">
                                    <h3 class="font-bold">Editing User</h3>
                                    <p class="mt-1 text-sm text-emerald-700">Update login details below, then click Update User.</p>
                                </div>

                                <label class="grid gap-1 text-sm font-semibold text-zinc-700">
                                    Name
                                    <input name="name" value="{{ old('name', $editUser->name) }}" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500" required>
                                    @error('name') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                                </label>

                                <label class="grid gap-1 text-sm font-semibold text-zinc-700">
                                    Email
                                    <input type="email" name="email" value="{{ old('email', $editUser->email) }}" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500" required>
                                    @error('email') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                                </label>

                                <label class="grid gap-1 text-sm font-semibold text-zinc-700">
                                    New Password
                                    <input type="password" name="password" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500">
                                    @error('password') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                                </label>

                                <label class="grid gap-1 text-sm font-semibold text-zinc-700">
                                    Account Status
                                    <select name="is_active" class="rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500" required>
                                        <option value="1" @selected((string) old('is_active', (int) $editUser->is_active) === '1')>Active</option>
                                        <option value="0" @selected((string) old('is_active', (int) $editUser->is_active) === '0')>Inactive</option>
                                    </select>
                                    @error('is_active') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                                </label>

                                <label class="grid gap-1 text-sm font-semibold text-zinc-700">
                                    Confirm Password
                                    <input type="password" name="password_confirmation" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500">
                                </label>

                                <div class="flex flex-wrap items-center gap-3 lg:col-span-4">
                                    <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">Update User</button>
                                    <a href="{{ route('users') }}" class="rounded-lg border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-700 shadow-sm hover:bg-zinc-50">Cancel Edit</a>
                                </div>
                            </form>
                        @endif

                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[960px] text-left text-sm">
                                <thead class="bg-zinc-50 text-xs uppercase text-zinc-500">
                                    <tr>
                                        <th class="px-5 py-3">Name</th>
                                        <th class="px-5 py-3">Email</th>
                                        <th class="px-5 py-3">Status</th>
                                        <th class="px-5 py-3">Created</th>
                                        <th class="px-5 py-3 text-right">Actions</th>
                                    </tr>
                                </thead>
                                    <tbody class="divide-y divide-zinc-100">
                                    @forelse ($users as $user)
                                        <tr>
                                            <td class="px-5 py-4 font-semibold">{{ $user['name'] }}</td>
                                            <td class="px-5 py-4 text-zinc-600">{{ $user['email'] }}</td>
                                            <td class="px-5 py-4">
                                                <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $user['role'] === 'admin' ? 'bg-emerald-50 text-emerald-700' : 'bg-sky-50 text-sky-700' }}">{{ $user['role_label'] }}</span>
                                            </td>
                                            <td class="px-5 py-4 text-zinc-600">{{ $user['booth_name'] ?? 'Admin console' }}</td>
                                            <td class="px-5 py-4 text-zinc-600">{{ $user['booth_status'] ?? 'Active' }}</td>
                                            <td class="px-5 py-4 text-zinc-600">{{ $user['created_at'] ?? 'Not available' }}</td>
                                            <td class="px-5 py-4">
                                                <div class="flex justify-end gap-2">
                                                    <a href="{{ route('users', ['edit' => $user['id']]) }}#user-form" class="rounded-lg border border-zinc-300 px-3 py-2 text-xs font-semibold text-zinc-700 hover:bg-zinc-50">Edit</a>
                                                    <form method="POST" action="{{ route('users.credentials.resend', $user['id']) }}">
                                                        @csrf
                                                        <button class="rounded-lg border border-emerald-200 px-3 py-2 text-xs font-semibold text-emerald-700 hover:bg-emerald-50">Resend credentials</button>
                                                    </form>
                                                    <form method="POST" action="{{ route('users.destroy', $user['id']) }}">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button class="rounded-lg border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-50">Delete</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="px-5 py-4 text-sm text-zinc-500">No user accounts yet.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </section>
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

                    @if (session('student_mail_failures'))
                        <div class="border-b border-rose-100 bg-rose-50 px-5 py-3 text-sm text-rose-800">
                            <p class="font-semibold">Credentials could not be sent to:</p>
                            <ul class="mt-2 space-y-2">
                                @foreach (session('student_mail_failures') as $failure)
                                    <li class="flex flex-wrap items-center gap-2">
                                        <span>{{ $failure['email'] }}</span>
                                        <form method="POST" action="{{ route('students.account.resend', $failure['id']) }}">
                                            @csrf
                                            <button class="rounded border border-rose-300 bg-white px-2 py-1 font-semibold hover:bg-rose-100">Resend credentials</button>
                                        </form>
                                    </li>
                                @endforeach
                            </ul>
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
                                Block <span class="font-normal text-zinc-400">(optional)</span>
                                <input name="block" value="{{ old('block', $editStudent->block) }}" placeholder="Leave blank if not assigned" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500">
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
                        <table class="w-full min-w-[680px] text-left text-sm">
                            <thead class="bg-zinc-50 text-xs uppercase text-zinc-500">
                                <tr>
                                    <th class="w-12 px-5 py-3">
                                        <input id="select-all-students" type="checkbox" class="h-4 w-4 rounded border-zinc-300 text-emerald-600 focus:ring-emerald-500" aria-label="Select all students">
                                    </th>
                                    <th class="px-5 py-3">Student ID</th>
                                    <th class="px-5 py-3">Full Name</th>
                                    <th class="px-5 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100">
                                @if ($students->isEmpty())
                                    <tr>
                                        <td colspan="4" class="px-5 py-4 text-sm text-zinc-500">
                                            {{ $showingInitialStudents ? 'No student records yet.' : 'No students match the current filters.' }}
                                        </td>
                                    </tr>
                                @else
                                    @foreach ($students->groupBy('course') as $course => $courseStudents)
                                        <tr class="bg-emerald-50/70">
                                            <th colspan="4" class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-emerald-800">
                                                Course: {{ $course ?: "Unassigned" }} <span class="font-normal text-emerald-600">({{ $courseStudents->count() }} student{{ $courseStudents->count() === 1 ? "" : "s" }})</span>
                                            </th>
                                        </tr>
                                        @foreach ($courseStudents as $student)
                                            <tr>
                                                <td class="px-5 py-4">
                                                    <input type="checkbox" name="student_ids[]" value="{{ $student["id"] }}" form="bulk-delete-students" class="student-selection h-4 w-4 rounded border-zinc-300 text-emerald-600 focus:ring-emerald-500" aria-label="Select {{ $student["first_name"] }} {{ $student["last_name"] }}">
                                                </td>
                                                <td class="px-5 py-4 font-semibold">{{ $student["student_id"] }}</td>
                                                <td class="px-5 py-4 text-zinc-700">{{ $student["last_name"] }}, {{ $student["first_name"] }}</td>
                                                <td class="px-5 py-4">
                                                    <div class="flex justify-end gap-2">
                                                        <a href="#student-details-modal-{{ $student["id"] }}" class="rounded-lg border border-emerald-200 px-3 py-2 text-xs font-semibold text-emerald-700 hover:bg-emerald-50">View</a>
                                                        <a href="{{ route("students", array_merge($activeFilters, ["edit" => $student["id"]])) }}#student-form" class="rounded-lg border border-zinc-300 px-3 py-2 text-xs font-semibold text-zinc-700 hover:bg-zinc-50">Edit</a>
                                                        <form method="POST" action="{{ route("students.destroy", $student["id"]) }}">
                                                            @csrf
                                                            @method("DELETE")
                                                            <button class="rounded-lg border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-50">Delete</button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    @endforeach
                                @endif
                            </tbody>
                        </table>
                    </div>

                    <div class="flex flex-col gap-3 border-t border-zinc-200 p-5 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-sm text-zinc-500">
                            @if ($showingInitialStudents)
                                Showing the first <span class="font-semibold text-zinc-800">{{ $students->count() }}</span> student record(s).
                            @elseif ($studentsLoaded)
                                <span class="font-semibold text-zinc-800">{{ $students->count() }}</span> student record(s) match the current filters.
                            @else
                                Student records are loaded only after applying a search or filter.
                            @endif
                        </p>
                        <div class="flex items-center gap-2">
                            @if ($students->isNotEmpty())
                                <form id="bulk-delete-students" method="POST" action="{{ route('students.destroy-selected') }}" onsubmit="return confirm('Delete the selected student records?')">
                                    @csrf
                                    @method('DELETE')
                                    <button id="delete-selected-students" disabled class="rounded-lg border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-40">Delete Selected</button>
                                </form>
                            @endif
                        </div>
                    </div>
                </section>
            @endif

            @if ($page === 'scanner')
                @php
                    $scannerBooth = auth()->user()?->role === 'booth' ? auth()->user()->booth : null;
                    $scannerCurrentEvent = $scannerCurrentEvent ?? null;
                @endphp
                <section class="grid gap-6 lg:grid-cols-[1fr_360px]">
                    <div class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm">
                        <div class="mb-4 rounded-lg border {{ $scannerCurrentEvent ? 'border-emerald-100 bg-emerald-50 text-emerald-900' : 'border-amber-100 bg-amber-50 text-amber-900' }} p-4">
                            <p class="text-sm font-semibold">{{ $scannerCurrentEvent?->title ?? 'No event currently in progress' }}</p>
                            <p class="mt-1 text-xs {{ $scannerCurrentEvent ? 'text-emerald-700' : 'text-amber-700' }}">
                                @if ($scannerCurrentEvent)
                                    {{ $scannerCurrentEvent->event_date->format('F j, Y') }} / {{ $scannerCurrentEvent->start_time }} - {{ $scannerCurrentEvent->end_time }} / {{ $scannerCurrentEvent->venue }} / Phase: {{ $scannerCurrentEvent->scanPhaseLabel() }} / Time-in: {{ $scannerCurrentEvent->time_in_open ? 'Open' : 'Closed' }}
                                @else
                                    Set an event's status to In Progress to enable booth scanning.
                                @endif
                            </p>
                        </div>

                        <div class="overflow-hidden rounded-lg bg-zinc-950">
                            <video id="qr-video" class="aspect-video w-full bg-zinc-950 object-cover" autoplay muted playsinline></video>
                        </div>

                        <div id="scanner-message" class="mt-4 rounded-lg border border-zinc-200 bg-zinc-50 px-4 py-3 text-center text-base font-semibold text-zinc-600">
                            Start the camera and scan a student QR code.
                        </div>

                        <div class="mt-4 grid gap-4 md:grid-cols-[1fr_auto] md:items-end">
                            @if (! $isBoothUser)
                                <label class="grid gap-1 text-sm font-semibold text-zinc-700">
                                    Booth
                                    <select id="scanner-booth-id" class="rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500">
                                        <option value="">Select booth</option>
                                        @foreach ($scannerBooths as $booth)
                                            <option value="{{ $booth->id }}">{{ $booth->name }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            @elseif ($scannerBooth)
                                <input type="hidden" id="scanner-booth-id" value="{{ $scannerBooth->id }}">
                            @endif

                            <div class="flex flex-wrap gap-2">
                                <button id="start-scanner" type="button" @disabled(! $scannerCurrentEvent) class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 disabled:cursor-not-allowed disabled:bg-zinc-300">Start Scanner</button>
                                <button id="stop-scanner" type="button" class="rounded-lg border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-700 shadow-sm hover:bg-zinc-50">Stop</button>
                            </div>
                        </div>

                    </div>

                    <aside class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm">
                        @if ($scannerBooth)
                            <div class="mb-5 rounded-lg border border-emerald-100 bg-emerald-50 p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="text-sm font-semibold text-emerald-900">{{ $scannerBooth->name }}</p>
                                    <span id="heartbeat-status" class="rounded-full bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-700">Connecting</span>
                                </div>
                                <p class="mt-2 text-xs text-emerald-700">This booth reports online while this scanner is open and connected.</p>
                            </div>
                        @endif
                        <h2 class="font-bold">Latest Scan</h2>
                        <dl class="mt-5 grid gap-4 text-sm" id="latest-scan">
                            <div><dt class="text-zinc-500">Event</dt><dd id="latest-event" class="font-semibold">{{ $scannerCurrentEvent?->title ?? '-' }}</dd></div>
                            <div><dt class="text-zinc-500">Scan Type</dt><dd id="latest-scan-type" class="font-semibold">-</dd></div>
                            <div><dt class="text-zinc-500">Student</dt><dd id="latest-student" class="font-semibold">No scan yet</dd></div>
                            <div><dt class="text-zinc-500">Student ID</dt><dd id="latest-student-id" class="font-semibold">-</dd></div>
                            <div class="rounded-lg bg-emerald-50 p-3"><dt class="text-xs font-semibold uppercase text-emerald-700">Verification Code</dt><dd id="latest-verification-code" class="mt-1 font-mono text-lg font-bold tracking-[0.15em] text-emerald-900">-</dd></div>
                            <div><dt class="text-zinc-500">Course / Block</dt><dd id="latest-course" class="font-semibold">-</dd></div>
                            <div><dt class="text-zinc-500">Booth</dt><dd id="latest-booth" class="font-semibold">{{ $scannerBooth?->name ?? '-' }}</dd></div>
                            <div><dt class="text-zinc-500">Time</dt><dd id="latest-time" class="font-semibold">-</dd></div>
                        </dl>
                    </aside>
                </section>
            @endif

            @if ($page === 'records')
                <section class="grid gap-6">
                    @if (session('attendance_status'))
                        <div class="rounded-lg border border-emerald-100 bg-emerald-50 px-5 py-3 text-sm font-semibold text-emerald-700">
                            {{ session('attendance_status') }}
                        </div>
                    @endif

                    @if ($errors->any() && ! $editAttendanceRecord)
                        <div class="rounded-lg border border-rose-100 bg-rose-50 px-5 py-3 text-sm font-semibold text-rose-700">
                            Please check the attendance details and try again.
                        </div>
                    @endif

                    <form method="GET" action="{{ route('records') }}" class="grid gap-3 rounded-lg border border-zinc-200 bg-white p-5 shadow-sm md:grid-cols-[260px_380px_auto] md:items-end">
                        <label class="grid gap-1 text-sm font-semibold text-zinc-700">
                            Date
                            <input type="date" name="date" value="{{ $recordFilters['date'] }}" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500">
                        </label>

                        <label class="grid gap-1 text-sm font-semibold text-zinc-700">
                            Event Schedule
                            <select name="event_schedule_id" class="rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500">
                                <option value="">Current event</option>
                                @foreach ($recordFilterOptions['events'] as $event)
                                    <option value="{{ $event->id }}" @selected((string) ($recordFilters['event_schedule_id'] ?? '') === (string) $event->id)>{{ $event->title }}</option>
                                @endforeach
                            </select>
                        </label>

                        <div class="flex items-end gap-2">
                            <button class="rounded-lg bg-zinc-950 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-zinc-800">Apply</button>
                            <a href="{{ route('records') }}" class="rounded-lg border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-700 shadow-sm hover:bg-zinc-50">Reset</a>
                        </div>
                    </form>

                    @if ($editAttendanceRecord)
                        <form
                            id="attendance-form"
                            method="POST"
                            action="{{ route('records.update', $editAttendanceRecord) }}"
                            class="scroll-mt-6 grid gap-4 rounded-lg border border-zinc-200 bg-emerald-50/50 p-5 shadow-sm lg:grid-cols-4"
                        >
                            @csrf
                            @method('PATCH')

                            <div class="lg:col-span-4">
                                <h2 class="font-bold">Editing Attendance Record</h2>
                                <p class="mt-1 text-sm text-emerald-700">Update the scan event, booth, student, or scan time.</p>
                            </div>

                            @include('partials.attendance-record-fields', ['record' => $editAttendanceRecord])

                            <div class="flex flex-wrap items-center gap-3 lg:col-span-4">
                                <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">Update Record</button>
                                <a href="{{ route('records') }}" class="rounded-lg border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-700 shadow-sm hover:bg-zinc-50">Cancel Edit</a>
                            </div>
                        </form>
                    @endif

                    <div class="grid gap-4">
                        @forelse ($records as $record)
                            <article class="rounded-lg border border-zinc-200 bg-white shadow-sm">
                                <div class="grid gap-4 p-5 lg:grid-cols-[180px_1fr_190px_160px] lg:items-center">
                                    <div>
                                        <p class="font-bold">{{ $record['date'] }}</p>
                                        <p class="mt-1 text-sm text-zinc-500">{{ $record['time'] }}</p>
                                    </div>
                                    <div>
                                        <h2 class="font-bold">{{ $record['event'] }}</h2>
                                        <p class="mt-1 text-sm text-zinc-600">{{ $record['booth'] }}</p>
                                    </div>
                                    <div>
                                        <p class="text-2xl font-bold">{{ $record['attended_count'] }}</p>
                                        <p class="text-sm text-zinc-500">of {{ $record['total_students'] }} students attended</p>
                                        <p class="mt-1 text-xs text-zinc-500">{{ $record['scan_count'] }} scan record(s)</p>
                                    </div>
                                    <div class="lg:text-right">
                                        <p class="text-2xl font-bold text-emerald-700">{{ $record['percentage_label'] }}</p>
                                        <p class="text-sm text-zinc-500">attendance</p>
                                    </div>
                                </div>

                                <form method="GET" action="{{ route('records') }}" class="grid gap-3 border-t border-zinc-100 bg-white p-5 md:grid-cols-3 xl:grid-cols-[1fr_1fr_1fr_auto] xl:items-end">
                                    @php
                                        $attendancePrintFilters = collect([
                                            'date' => $recordFilters['date'],
                                            'event_schedule_id' => $recordFilters['event_schedule_id'],
                                            'course' => $recordFilters['course'] ?? null,
                                            'block' => $recordFilters['block'] ?? null,
                                            'year_level' => $recordFilters['year_level'] ?? null,
                                        ])->filter(fn ($value) => filled($value))->all();
                                    @endphp
                                    <input type="hidden" name="date" value="{{ $recordFilters['date'] }}">
                                    <input type="hidden" name="event_schedule_id" value="{{ $recordFilters['event_schedule_id'] }}">

                                    <label class="grid gap-1 text-sm font-semibold text-zinc-700">
                                        Course
                                        <select name="course" class="rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500">
                                            <option value="">All courses</option>
                                            @foreach ($recordFilterOptions['courses'] as $course)
                                                <option value="{{ $course }}" @selected(($recordFilters['course'] ?? '') === $course)>{{ $course }}</option>
                                            @endforeach
                                        </select>
                                    </label>

                                    <label class="grid gap-1 text-sm font-semibold text-zinc-700">
                                        Block
                                        <select name="block" class="rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500">
                                            <option value="">All blocks</option>
                                            @foreach ($recordFilterOptions['blocks'] as $block)
                                                <option value="{{ $block }}" @selected(($recordFilters['block'] ?? '') === $block)>{{ $block }}</option>
                                            @endforeach
                                        </select>
                                    </label>

                                    <label class="grid gap-1 text-sm font-semibold text-zinc-700">
                                        Year Level
                                        <select name="year_level" class="rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500">
                                            <option value="">All year levels</option>
                                            @foreach ($recordFilterOptions['year_levels'] as $yearLevel)
                                                <option value="{{ $yearLevel }}" @selected(($recordFilters['year_level'] ?? '') === $yearLevel)>{{ $yearLevel }}</option>
                                            @endforeach
                                        </select>
                                    </label>

                                    <div class="flex flex-wrap items-end gap-2 md:col-span-3 xl:col-span-1">
                                        <button class="rounded-lg bg-zinc-950 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-zinc-800">Apply</button>
                                        <a href="{{ route('records', array_filter(['date' => $recordFilters['date'], 'event_schedule_id' => $recordFilters['event_schedule_id']])) }}" class="rounded-lg border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-700 shadow-sm hover:bg-zinc-50">Reset</a>
                                        <a href="{{ route('records.print', $attendancePrintFilters) }}" target="_blank" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700 shadow-sm hover:bg-emerald-100">Print Attendance</a>
                                    </div>
                                </form>

                                <div class="overflow-x-auto border-t border-zinc-100">
                                    <table class="w-full min-w-[720px] text-left text-sm">
                                        <thead class="bg-zinc-50 text-xs uppercase text-zinc-500">
                                            <tr>
                                                <th class="px-5 py-3">Student ID</th>
                                                <th class="px-5 py-3">Student</th>
                                                <th class="px-5 py-3">Time-in</th>
                                                <th class="px-5 py-3">Status</th>
                                                <th class="px-5 py-3">Time-out</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-zinc-100">
                                            @forelse ($record['attendees'] as $attendee)
                                                <tr>
                                                    <td class="px-5 py-3 font-semibold text-zinc-800">{{ $attendee['student_id'] }}</td>
                                                    <td class="px-5 py-3 text-zinc-700">{{ $attendee['name'] }}</td>
                                                    <td class="px-5 py-3">
                                                        <span class="font-semibold text-zinc-800">{{ $attendee['time_in'] ?? 'Pending' }}</span>
                                                        <span class="ml-2 rounded-full px-2 py-1 text-xs font-semibold {{ $attendee['time_in_status'] === 'Done' ? 'bg-emerald-50 text-emerald-700' : 'bg-zinc-100 text-zinc-600' }}">{{ $attendee['time_in_status'] }}</span>
                                                    </td>
                                                    <td class="px-5 py-3">
                                                        <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $attendee['attendance_status'] === 'Late' ? 'bg-amber-50 text-amber-700' : ($attendee['attendance_status'] === 'On Time' ? 'bg-emerald-50 text-emerald-700' : 'bg-zinc-100 text-zinc-600') }}">{{ $attendee['attendance_status'] }}</span>
                                                    </td>
                                                    <td class="px-5 py-3">
                                                        <span class="font-semibold text-zinc-800">{{ $attendee['time_out'] ?? 'Pending' }}</span>
                                                        <span class="ml-2 rounded-full px-2 py-1 text-xs font-semibold {{ $attendee['time_out_status'] === 'Done' ? 'bg-emerald-50 text-emerald-700' : 'bg-zinc-100 text-zinc-600' }}">{{ $attendee['time_out_status'] }}</span>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="5" class="px-5 py-4 text-sm text-zinc-500">
                                                        {{ $record['attendees_loaded'] ? 'No attended students match these filters.' : 'Choose a course, block, or year level, then apply to load attendees.' }}
                                                    </td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </article>
                        @empty
                            <div class="rounded-lg border border-zinc-200 bg-white p-5 text-sm text-zinc-500 shadow-sm">
                                No attendance summaries yet. Use Add Record to add the first scan record.
                            </div>
                        @endforelse
                    </div>
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
                                @if (session('attendance_archive_id'))
                                    <a class="ml-2 underline" href="{{ route('schedule.archives.download', session('attendance_archive_id')) }}" data-loading-skip>Download final attendance report</a>
                                @endif
                            </div>
                        @endif

                        @if ($errors->any() && ! $editEvent)
                            <div class="border-b border-rose-100 bg-rose-50 px-5 py-3 text-sm font-semibold text-rose-700">
                                Please check the event details and try again.
                            </div>
                        @endif

                        <div class="grid divide-y divide-zinc-100">
                            @forelse ($events as $event)
                                <article class="grid gap-4 p-5 md:grid-cols-[160px_1fr] xl:grid-cols-[160px_1fr_300px] xl:items-center">
                                    <div>
                                        <p class="text-sm font-bold text-zinc-950">{{ $event['date'] }}</p>
                                        <p class="mt-1 text-sm text-zinc-500">{{ $event['time'] }}</p>
                                    </div>
                                    <div>
                                        <h3 class="font-bold">{{ $event['title'] }}</h3>
                                        <p class="mt-1 text-sm text-zinc-500">{{ $event['venue'] }}</p>
                                        @if ($event['status'] === 'In Progress')
                                            <p class="mt-2 text-xs font-semibold text-zinc-500">Active scan: <span class="text-zinc-800">{{ $event['scan_phase_label'] }}</span></p>
                                            <p class="mt-1 text-xs font-semibold text-zinc-500">Time-in: <span class="{{ $event['time_in_open'] ? 'text-emerald-700' : 'text-rose-700' }}">{{ $event['time_in_open'] ? 'Open' : 'Closed' }}</span></p>
                                        @endif
                                        @if ($event['override_schedule_time'])
                                            <p class="mt-1 text-xs font-semibold text-amber-700">Schedule override active. Edit the event to end the override.</p>
                                        @endif
                                    </div>
                                    <div class="grid gap-3 md:col-span-2 xl:col-span-1 xl:text-right">
                                        <span class="justify-self-start rounded-full px-2 py-1 text-xs font-semibold xl:justify-self-end {{ $event['status'] === 'In Progress' ? 'bg-emerald-50 text-emerald-700' : ($event['status'] === 'Upcoming' ? 'bg-sky-50 text-sky-700' : 'bg-zinc-100 text-zinc-600') }}">{{ $event['status'] }}</span>
                                        <div class="flex flex-wrap gap-2 xl:justify-end">
                                            <a href="{{ route('schedule', ['edit' => $event['id']]) }}#edit-event-modal" class="rounded-lg border border-zinc-300 px-3 py-2 text-xs font-semibold text-zinc-700 hover:bg-zinc-50">Edit</a>
                                            <a href="{{ route('records.print', ['event_schedule_id' => $event['id']]) }}" target="_blank" class="rounded-lg border border-zinc-300 px-3 py-2 text-xs font-semibold text-zinc-700 hover:bg-zinc-50">View attendance</a>
                                            @if ($event['status'] === 'In Progress')
                                                <form method="POST" action="{{ route('schedule.scan-phase', $event['id']) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    @if ($event['scan_phase'] === 'time_in')
                                                        <input type="hidden" name="scan_phase" value="time_out">
                                                        <button class="whitespace-nowrap rounded-lg border border-emerald-200 px-3 py-2 text-xs font-semibold text-emerald-700 hover:bg-emerald-50">Activate Time-out</button>
                                                    @elseif ($event['scan_phase'] === 'time_out')
                                                        <input type="hidden" name="scan_phase" value="time_in">
                                                        <button class="whitespace-nowrap rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-700 hover:bg-amber-100">Reopen Time-in</button>
                                                    @else
                                                        <input type="hidden" name="scan_phase" value="time_out">
                                                        <button class="whitespace-nowrap rounded-lg border border-emerald-200 px-3 py-2 text-xs font-semibold text-emerald-700 hover:bg-emerald-50">Activate Time-out</button>
                                                    @endif
                                                </form>
                                                <form method="POST" action="{{ route('schedule.time-in', $event['id']) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    @if ($event['time_in_open'])
                                                        <input type="hidden" name="time_in_open" value="0">
                                                        <button class="whitespace-nowrap rounded-lg border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-50">Close Time-in</button>
                                                    @else
                                                        <input type="hidden" name="time_in_open" value="1">
                                                        <button class="whitespace-nowrap rounded-lg border border-emerald-200 px-3 py-2 text-xs font-semibold text-emerald-700 hover:bg-emerald-50">Open Time-in</button>
                                                    @endif
                                                </form>
                                            @endif
                                            <a href="{{ route('schedule.delete.confirm', $event['id']) }}" class="rounded-lg border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-50">Archive &amp; Delete</a>
                                        </div>
                                    </div>
                                </article>
                            @empty
                                <div class="p-5 text-sm text-zinc-500">No event schedules yet. Use Add Record to add the first event.</div>
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
                                            <a href="{{ route('schedule.delete.confirm', $event['id']) }}" class="rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50">Archive &amp; Delete</a>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <p class="text-sm text-zinc-500">Saved events will appear here.</p>
                            @endforelse
                        </div>
                    </aside>
                </section>

                <section class="mt-6 rounded-lg border border-zinc-200 bg-white p-5 shadow-sm">
                    <h2 class="font-bold">Final attendance archives</h2>
                    <p class="mt-1 text-sm text-zinc-500">A copy of each event's attendance report is saved before deletion.</p>
                    <div class="mt-4 divide-y divide-zinc-100">
                        @forelse ($attendanceArchives as $archive)
                            <div class="flex flex-wrap items-center justify-between gap-3 py-3 text-sm">
                                <div>
                                    <p class="font-semibold text-zinc-900">{{ $archive->event_title }}</p>
                                    <p class="text-zinc-500">{{ $archive->event_date->format('F j, Y') }} · Archived {{ $archive->created_at->format('F j, Y g:i A') }}</p>
                                </div>
                                <a href="{{ route('schedule.archives.download', $archive) }}" data-loading-skip class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 font-semibold text-emerald-700 hover:bg-emerald-100">Download report</a>
                            </div>
                        @empty
                            <p class="py-3 text-sm text-zinc-500">No attendance reports have been archived yet.</p>
                        @endforelse
                    </div>
                    <div class="mt-4">{{ $attendanceArchives->links() }}</div>
                </section>
            @endif

            @if ($page === 'booths')
                <section class="grid gap-6">
                    @if (session('booth_status'))
                        <div class="rounded-lg border border-emerald-100 bg-emerald-50 px-5 py-3 text-sm font-semibold text-emerald-700">
                            {{ session('booth_status') }}
                        </div>
                    @endif

                    @if ($errors->any() && ! $editBooth)
                        <div class="rounded-lg border border-rose-100 bg-rose-50 px-5 py-3 text-sm font-semibold text-rose-700">
                            Please check the booth details and try again.
                        </div>
                    @endif

                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        @forelse ($booths as $booth)
                            <article class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm" data-booth-card="{{ $booth['id'] }}">
                                <div class="flex items-center justify-between gap-3">
                                    <h2 class="font-bold">{{ $booth['name'] }}</h2>
                                    <span data-booth-status class="rounded-full px-2 py-1 text-xs font-semibold {{ $booth['status'] === 'Online' ? 'bg-emerald-50 text-emerald-700' : ($booth['status'] === 'Review' ? 'bg-amber-50 text-amber-700' : 'bg-zinc-100 text-zinc-600') }}">{{ $booth['status'] }}</span>
                                </div>
                                <p data-booth-scans class="mt-5 text-3xl font-bold">{{ $booth['scans'] }}</p>
                                <p class="text-sm text-zinc-500">Scans today</p>
                                <p class="mt-4 text-sm font-semibold text-zinc-700">{{ $booth['admin_name'] }}</p>
                                @if ($booth['admin_email'])
                                    <p class="mt-1 break-all text-xs text-zinc-500">{{ $booth['admin_email'] }}</p>
                                @endif
                                <p class="mt-3 text-xs font-medium text-zinc-500">Last seen: <span data-booth-last-seen>{{ $booth['last_seen'] }}</span></p>

                                <div class="mt-5 flex gap-2">
                                    <a href="{{ route('booths', ['edit' => $booth['id']]) }}#edit-booth-modal" class="rounded-lg border border-zinc-300 px-3 py-2 text-xs font-semibold text-zinc-700 hover:bg-zinc-50">Edit</a>
                                    <form method="POST" action="{{ route('booths.destroy', $booth['id']) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="rounded-lg border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-50">Delete</button>
                                    </form>
                                </div>
                            </article>
                        @empty
                            <div class="rounded-lg border border-zinc-200 bg-white p-5 text-sm text-zinc-500 shadow-sm md:col-span-2 xl:col-span-4">
                                No scanner operators yet. Use Add Record to add the first operator.
                            </div>
                        @endforelse
                    </div>
                </section>
            @endif
        </main>
    </div>

    @if ($page === 'students')
        @foreach ($students as $student)
            <div id="student-details-modal-{{ $student['id'] }}" class="modal-overlay fixed inset-0 z-50 place-items-center overflow-y-auto overscroll-contain bg-zinc-950/55 p-4">
                <div class="max-h-[calc(100dvh-2rem)] w-full max-w-lg overflow-y-auto rounded-lg bg-white shadow-xl">
                    <div class="flex items-center justify-between border-b border-zinc-200 p-5">
                        <div>
                            <h2 class="font-bold">Student Details</h2>
                            <p class="mt-1 text-sm text-zinc-500">{{ $student['student_id'] }}</p>
                        </div>
                        <a href="{{ $studentsFilteredUrl }}" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-semibold text-zinc-700 hover:bg-zinc-50">Close</a>
                    </div>

                    <dl class="grid gap-4 p-5 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <dt class="text-xs font-semibold uppercase text-zinc-500">Full Name</dt>
                            <dd class="mt-1 font-bold text-zinc-900">{{ $student['last_name'] }}, {{ $student['first_name'] }}</dd>
                        </div>
                        <div class="sm:col-span-2 rounded-lg border border-emerald-100 bg-emerald-50 p-4 text-center">
                            <dt class="text-xs font-semibold uppercase text-emerald-700">Attendance QR Code</dt>
                            <img data-student-qr-image src="{{ $student['qr_code']['image_url'] }}" data-qr-endpoint="{{ route('students.qr', $student['id']) }}" alt="Attendance QR code for {{ $student['student_id'] }}" class="student-qr-image mx-auto mt-3 h-56 w-56 rounded bg-white p-2">
                            <p data-student-qr-code class="mt-2 font-mono text-sm font-bold tracking-[0.15em] text-emerald-900">{{ $student['qr_code']['verification_code'] }}</p>
                            <p class="mt-1 text-xs text-emerald-700">Refreshes automatically every minute while this page is open.</p>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase text-zinc-500">Course</dt>
                            <dd class="mt-1 text-sm text-zinc-700">{{ $student['course'] }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase text-zinc-500">Year Level</dt>
                            <dd class="mt-1 text-sm text-zinc-700">{{ $student['year_level'] }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase text-zinc-500">Block</dt>
                            <dd class="mt-1 text-sm text-zinc-700">{{ $student['block'] ?: 'Not assigned' }}</dd>
                        </div>
                    </dl>
                    <form method="POST" action="{{ route('students.account', $student['id']) }}" class="grid gap-3 border-t border-zinc-200 p-5">
                        @csrf
                        <h3 class="font-bold">Student login account</h3>
                        <p class="text-sm text-zinc-600">Current status: <span class="font-semibold">{{ $student['account_email'] ?: 'Not linked' }}</span></p>
                        <p class="text-xs text-zinc-500">Set the email and password the student will use to sign in.</p>
                        <input type="email" name="email" value="{{ $student['account_email'] }}" placeholder="Student email" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm" required>
                        <input type="password" name="password" placeholder="{{ $student['account_email'] ? 'New password (leave blank to keep current)' : 'Password (at least 8 characters)' }}" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm" {{ $student['account_email'] ? '' : 'required' }}>
                        <input type="password" name="password_confirmation" placeholder="Confirm password" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm">
                        <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white">{{ $student['account_email'] ? 'Update Login' : 'Create Login' }}</button>
                    </form>
                    @if ($student['account_email'])
                        <form method="POST" action="{{ route('students.account.resend', $student['id']) }}" class="border-t border-zinc-200 p-5">
                            @csrf
                            <button class="text-sm font-semibold text-emerald-700 hover:underline">Resend temporary credentials</button>
                        </form>
                    @endif
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

                <form id="student-import-form" method="POST" action="{{ route('students.import') }}" data-prepare-url="{{ route('students.import.prepare') }}" data-progress-url="{{ route('students.import.progress') }}" enctype="multipart/form-data" class="grid gap-4 p-5">
                    @csrf

                    <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-4 text-sm text-zinc-600">
                        Required headers: <span class="font-semibold text-zinc-800">student_id, email, last_name, first_name, course, block, year_level</span>. Rows without an email are skipped.
                    </div>

                    <label class="grid gap-1 text-sm font-semibold text-zinc-700">
                        Import File
                        <input type="file" name="student_file" accept=".csv,.txt,.xlsx" class="rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500" required>
                        @error('student_file') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                    </label>

                    <div id="student-import-progress" class="hidden rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm" role="status" aria-live="polite">
                        <p id="student-import-stage" class="font-semibold text-emerald-900">Preparing import…</p>
                        <progress id="student-import-bar" class="mt-3 h-3 w-full accent-emerald-600" value="0" max="100"></progress>
                        <p id="student-import-counts" class="mt-2 text-emerald-900">0 rows processed</p>
                        <ul id="student-import-recent" class="mt-3 max-h-36 space-y-1 overflow-auto text-zinc-700"></ul>
                    </div>
                    <p id="student-import-client-error" class="hidden rounded-lg bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700" role="alert"></p>

                    <div class="flex flex-wrap items-center gap-3">
                        <button id="student-import-submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">Import Records</button>
                        <a href="{{ route('students') }}" class="rounded-lg border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-700 shadow-sm hover:bg-zinc-50">Cancel</a>
                    </div>
                </form>
            </div>
        </div>

        <div id="add-student-modal" class="modal-overlay fixed inset-0 z-50 place-items-center bg-zinc-950/55 p-4 {{ $errors->bulkStudents->any() ? 'is-open' : '' }}">
            <div class="flex max-h-[90vh] w-full max-w-7xl flex-col rounded-lg bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-zinc-200 p-5">
                    <div>
                        <h2 class="font-bold">Add Students</h2>
                        <p class="mt-1 text-sm text-zinc-500">Enter multiple student records in spreadsheet format.</p>
                    </div>
                    <a href="{{ route('students') }}" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-semibold text-zinc-700 hover:bg-zinc-50">Close</a>
                </div>

                <form method="POST" action="{{ route('students.store-bulk') }}" class="flex min-h-0 flex-1 flex-col">
                    @csrf
                    @php
                        $bulkStudentRows = old('students', array_fill(0, 5, [
                            'student_id' => '', 'email' => '', 'last_name' => '', 'first_name' => '',
                            'course' => '', 'block' => '', 'year_level' => '',
                        ]));
                    @endphp

                    @if ($errors->bulkStudents->any())
                        <div class="border-b border-rose-100 bg-rose-50 px-5 py-3 text-sm font-semibold text-rose-700">
                            Please correct the highlighted student rows and save again.
                        </div>
                    @endif

                    <div class="min-h-0 flex-1 overflow-auto p-5">
                        <table class="w-full min-w-[1100px] border-collapse text-sm">
                            <thead class="sticky top-0 z-10 bg-zinc-100 text-left text-xs uppercase text-zinc-600">
                                <tr>
                                    <th class="w-12 border border-zinc-300 px-3 py-2">#</th>
                                    <th class="border border-zinc-300 px-3 py-2">Student ID</th>
                                    <th class="border border-zinc-300 px-3 py-2">Email (required)</th>
                                    <th class="border border-zinc-300 px-3 py-2">Last Name</th>
                                    <th class="border border-zinc-300 px-3 py-2">First Name</th>
                                    <th class="border border-zinc-300 px-3 py-2">Course</th>
                                    <th class="w-32 border border-zinc-300 px-3 py-2">Block <span class="normal-case font-normal text-zinc-400">(optional)</span></th>
                                    <th class="w-44 border border-zinc-300 px-3 py-2">Year Level</th>
                                    <th class="w-12 border border-zinc-300 px-3 py-2"></th>
                                </tr>
                            </thead>
                            <tbody id="bulk-student-rows">
                                @foreach ($bulkStudentRows as $index => $row)
                                    <tr class="bulk-student-row">
                                        <td class="row-number border border-zinc-300 bg-zinc-50 px-3 py-2 text-center font-semibold text-zinc-500">{{ $index + 1 }}</td>
                                        @foreach (['student_id', 'email', 'last_name', 'first_name'] as $field)
                                            <td class="border border-zinc-300 p-0">
                                                <input type="{{ $field === 'email' ? 'email' : 'text' }}" name="students[{{ $index }}][{{ $field }}]" value="{{ $row[$field] ?? '' }}" class="w-full border-0 px-3 py-2 outline-none focus:bg-emerald-50 focus:ring-2 focus:ring-inset focus:ring-emerald-500 {{ $errors->bulkStudents->has("students.{$index}.{$field}") ? 'bg-rose-50' : '' }}">
                                            </td>
                                        @endforeach
                                        <td class="spreadsheet-fill-cell relative border border-zinc-300 p-0" data-fill-field="course">
                                            <input name="students[{{ $index }}][course]" value="{{ $row['course'] ?? '' }}" list="bulk-course-options" class="w-full border-0 px-3 py-2 outline-none focus:bg-emerald-50 focus:ring-2 focus:ring-inset focus:ring-emerald-500 {{ $errors->bulkStudents->has("students.{$index}.course") ? 'bg-rose-50' : '' }}">
                                            <button type="button" class="spreadsheet-fill-handle absolute bottom-0 right-0 h-3 w-3 cursor-crosshair border-0 border-l border-t border-emerald-700 bg-emerald-500" title="Drag to fill course" aria-label="Drag to fill course"></button>
                                        </td>
                                        <td class="spreadsheet-fill-cell relative border border-zinc-300 p-0" data-fill-field="block">
                                            <select name="students[{{ $index }}][block]" class="w-full border-0 bg-white px-3 py-2 outline-none focus:bg-emerald-50 focus:ring-2 focus:ring-inset focus:ring-emerald-500 {{ $errors->bulkStudents->has("students.{$index}.block") ? 'bg-rose-50' : '' }}">
                                                <option value="">No block</option>
                                                @foreach (range('A', 'G') as $block)
                                                    <option value="{{ $block }}" @selected(($row['block'] ?? '') === $block)>{{ $block }}</option>
                                                @endforeach
                                            </select>
                                            <button type="button" class="spreadsheet-fill-handle absolute bottom-0 right-0 h-3 w-3 cursor-crosshair border-0 border-l border-t border-emerald-700 bg-emerald-500" title="Drag to fill block" aria-label="Drag to fill block"></button>
                                        </td>
                                        <td class="spreadsheet-fill-cell relative border border-zinc-300 p-0" data-fill-field="year_level">
                                            <select name="students[{{ $index }}][year_level]" class="w-full border-0 bg-white px-3 py-2 outline-none focus:bg-emerald-50 focus:ring-2 focus:ring-inset focus:ring-emerald-500 {{ $errors->bulkStudents->has("students.{$index}.year_level") ? 'bg-rose-50' : '' }}">
                                                <option value="">Select year level</option>
                                                @foreach ($yearLevels as $yearLevel)
                                                    <option value="{{ $yearLevel }}" @selected(($row['year_level'] ?? '') === $yearLevel)>{{ $yearLevel }}</option>
                                                @endforeach
                                            </select>
                                            <button type="button" class="spreadsheet-fill-handle absolute bottom-0 right-0 h-3 w-3 cursor-crosshair border-0 border-l border-t border-emerald-700 bg-emerald-500" title="Drag to fill year level" aria-label="Drag to fill year level"></button>
                                        </td>
                                        <td class="border border-zinc-300 p-0 text-center">
                                            <button type="button" class="remove-student-row h-9 w-9 text-lg font-semibold text-zinc-400 hover:text-rose-600" title="Remove row" aria-label="Remove row">&times;</button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-zinc-200 p-5">
                        <button id="add-student-row" type="button" class="rounded-lg border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-700 shadow-sm hover:bg-zinc-50">Add Row</button>
                        <div class="flex items-center gap-3">
                            <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">Save Students</button>
                            <a href="{{ route('students') }}" class="rounded-lg border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-700 shadow-sm hover:bg-zinc-50">Cancel</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div id="bulk-student-progress" class="fixed inset-0 z-[60] hidden place-items-center bg-zinc-950/60 p-4" role="status" aria-live="polite">
            <div class="w-full max-w-md rounded-xl bg-white p-6 text-center shadow-2xl">
                <div class="mx-auto h-12 w-12 animate-spin rounded-full border-4 border-emerald-100 border-t-emerald-600"></div>
                <h2 class="mt-5 text-lg font-bold text-zinc-900">Saving students and sending credentials</h2>
                <p class="mt-2 text-sm text-zinc-600">Please wait. Email send results will appear after this request finishes.</p>
            </div>
        </div>

        <template id="bulk-student-row-template">
            <tr class="bulk-student-row">
                <td class="row-number border border-zinc-300 bg-zinc-50 px-3 py-2 text-center font-semibold text-zinc-500"></td>
                @foreach (['student_id', 'email', 'last_name', 'first_name'] as $field)
                    <td class="border border-zinc-300 p-0"><input type="{{ $field === 'email' ? 'email' : 'text' }}" data-field="{{ $field }}" class="w-full border-0 px-3 py-2 outline-none focus:bg-emerald-50 focus:ring-2 focus:ring-inset focus:ring-emerald-500"></td>
                @endforeach
                <td class="spreadsheet-fill-cell relative border border-zinc-300 p-0" data-fill-field="course">
                    <input data-field="course" list="bulk-course-options" class="w-full border-0 px-3 py-2 outline-none focus:bg-emerald-50 focus:ring-2 focus:ring-inset focus:ring-emerald-500">
                    <button type="button" class="spreadsheet-fill-handle absolute bottom-0 right-0 h-3 w-3 cursor-crosshair border-0 border-l border-t border-emerald-700 bg-emerald-500" title="Drag to fill course" aria-label="Drag to fill course"></button>
                </td>
                <td class="spreadsheet-fill-cell relative border border-zinc-300 p-0" data-fill-field="block">
                    <select data-field="block" class="w-full border-0 bg-white px-3 py-2 outline-none focus:bg-emerald-50 focus:ring-2 focus:ring-inset focus:ring-emerald-500">
                        <option value="">No block</option>
                        @foreach (range('A', 'G') as $block)<option value="{{ $block }}">{{ $block }}</option>@endforeach
                    </select>
                    <button type="button" class="spreadsheet-fill-handle absolute bottom-0 right-0 h-3 w-3 cursor-crosshair border-0 border-l border-t border-emerald-700 bg-emerald-500" title="Drag to fill block" aria-label="Drag to fill block"></button>
                </td>
                <td class="spreadsheet-fill-cell relative border border-zinc-300 p-0" data-fill-field="year_level">
                    <select data-field="year_level" class="w-full border-0 bg-white px-3 py-2 outline-none focus:bg-emerald-50 focus:ring-2 focus:ring-inset focus:ring-emerald-500">
                        <option value="">Select year level</option>
                        @foreach ($yearLevels as $yearLevel)<option value="{{ $yearLevel }}">{{ $yearLevel }}</option>@endforeach
                    </select>
                    <button type="button" class="spreadsheet-fill-handle absolute bottom-0 right-0 h-3 w-3 cursor-crosshair border-0 border-l border-t border-emerald-700 bg-emerald-500" title="Drag to fill year level" aria-label="Drag to fill year level"></button>
                </td>
                <td class="border border-zinc-300 p-0 text-center"><button type="button" class="remove-student-row h-9 w-9 text-lg font-semibold text-zinc-400 hover:text-rose-600" title="Remove row" aria-label="Remove row">&times;</button></td>
            </tr>
        </template>

        <datalist id="bulk-course-options">
            @foreach ($filterOptions['courses'] as $course)<option value="{{ $course }}"></option>@endforeach
        </datalist>

    @endif

    @if ($page === 'records')
        <div id="add-attendance-modal" class="modal-overlay fixed inset-0 z-50 place-items-center bg-zinc-950/55 p-4 {{ $errors->any() && ! $editAttendanceRecord ? 'is-open' : '' }}">
            <div class="w-full max-w-4xl rounded-lg bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-zinc-200 p-5">
                    <div>
                        <h2 class="font-bold">Add Attendance Record</h2>
                        <p class="mt-1 text-sm text-zinc-500">Create a scan record for an event schedule, booth, and student.</p>
                    </div>
                    <a href="{{ route('records') }}" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-semibold text-zinc-700 hover:bg-zinc-50">Close</a>
                </div>

                <form method="POST" action="{{ route('records.store') }}" class="grid gap-4 p-5 lg:grid-cols-4">
                    @csrf

                    @include('partials.attendance-record-fields', ['record' => null])

                    <div class="flex flex-wrap items-center gap-3 lg:col-span-4">
                        <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">Save Record</button>
                        <a href="{{ route('records') }}" class="rounded-lg border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-700 shadow-sm hover:bg-zinc-50">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($page === 'users')
        <div id="add-admin-modal" class="modal-overlay fixed inset-0 z-50 place-items-center bg-zinc-950/55 p-4 {{ $errors->any() && ! $editUser ? 'is-open' : '' }}">
            <div class="w-full max-w-2xl rounded-lg bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-zinc-200 p-5">
                    <div>
                        <h2 class="font-bold">Create Admin</h2>
                        <p class="mt-1 text-sm text-zinc-500">Add an admin login account. The temporary credentials will be emailed to the address provided.</p>
                    </div>
                    <a href="{{ route('users') }}" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-semibold text-zinc-700 hover:bg-zinc-50">Close</a>
                </div>

                <form method="POST" action="{{ route('users.store') }}" class="grid gap-4 p-5 md:grid-cols-2">
                    @csrf

                    @if ($errors->any() && ! $editUser)
                        <div class="rounded-lg border border-rose-100 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700 md:col-span-2">
                            Please check the admin details and try again.
                        </div>
                    @endif

                    <label class="grid gap-1 text-sm font-semibold text-zinc-700 md:col-span-2">
                        Name
                        <input name="name" value="{{ old('name') }}" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500" required>
                        @error('name') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                    </label>

                    <label class="grid gap-1 text-sm font-semibold text-zinc-700 md:col-span-2">
                        Email
                        <input type="email" name="email" value="{{ old('email') }}" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500" required>
                        @error('email') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                    </label>

                    <div class="flex flex-wrap items-center gap-3 md:col-span-2">
                        <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">Save Admin</button>
                        <a href="{{ route('users') }}" class="rounded-lg border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-700 shadow-sm hover:bg-zinc-50">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($page === 'schedule')
        @if ($editEvent)
            <div id="edit-event-modal" class="modal-overlay fixed inset-0 z-50 is-open place-items-center overflow-y-auto bg-zinc-950/55 p-4">
                <div class="my-auto w-full max-w-3xl rounded-lg bg-white shadow-xl">
                    <div class="flex items-center justify-between border-b border-zinc-200 p-5">
                        <div>
                            <h2 class="font-bold">Editing Event</h2>
                            <p class="mt-1 text-sm text-zinc-500">Update the event schedule details.</p>
                        </div>
                        <a href="{{ route('schedule') }}" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-semibold text-zinc-700 hover:bg-zinc-50">Close</a>
                    </div>

                    <form id="event-form" method="POST" action="{{ route('schedule.update', $editEvent) }}" class="grid gap-4 p-5 lg:grid-cols-2">
                        @csrf
                        @method('PATCH')

                        @if ($errors->any())
                            <div class="rounded-lg border border-rose-100 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700 lg:col-span-2">
                                Please check the event details and try again.
                            </div>
                        @endif

                        <label class="grid gap-1 text-sm font-semibold text-zinc-700 lg:col-span-2">
                            Event Title
                            <input name="title" value="{{ old('title', $editEvent->title) }}" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500" required>
                            @error('title') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>

                        <label class="grid gap-1 text-sm font-semibold text-zinc-700">
                            Date
                            <input type="date" name="event_date" value="{{ old('event_date', $editEvent->event_date?->format('Y-m-d')) }}" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500" required>
                            @error('event_date') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>

                        <label class="grid gap-1 text-sm font-semibold text-zinc-700">
                            Status
                            <select name="status" class="rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500" required>
                                @foreach (['Scheduled', 'Upcoming', 'In Progress', 'Completed', 'Cancelled'] as $status)
                                    <option value="{{ $status }}" @selected(old('status', $editEvent->status) === $status)>{{ $status }}</option>
                                @endforeach
                            </select>
                            @error('status') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>

                        <label class="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-950 lg:col-span-2">
                            <input type="hidden" name="override_schedule_time" value="0">
                            <input type="checkbox" name="override_schedule_time" value="1" @checked(old('override_schedule_time', $editEvent->override_schedule_time)) class="mt-1 rounded border-amber-300">
                            <span><strong>Override schedule time</strong><br><span class="font-normal">With status In Progress, keep this event open for scanning even after its scheduled end. Set the status to Completed and uncheck this when finished.</span></span>
                        </label>
                        @error('override_schedule_time') <span class="text-xs text-rose-600 lg:col-span-2">{{ $message }}</span> @enderror

                        <label class="grid gap-1 text-sm font-semibold text-zinc-700">
                            Start Time
                            <input type="time" name="start_time" value="{{ old('start_time', substr($editEvent->start_time, 0, 5)) }}" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500" required>
                            @error('start_time') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>

                        <label class="grid gap-1 text-sm font-semibold text-zinc-700">
                            End Time
                            <input type="time" name="end_time" value="{{ old('end_time', substr($editEvent->end_time, 0, 5)) }}" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500" required>
                            @error('end_time') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>

                        <label class="grid gap-1 text-sm font-semibold text-zinc-700 lg:col-span-2">
                            Venue
                            <input name="venue" value="{{ old('venue', $editEvent->venue) }}" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500" required>
                            @error('venue') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>

                        <div class="flex flex-wrap items-center gap-3 lg:col-span-2">
                            <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">Update Event</button>
                            <a href="{{ route('schedule') }}" class="rounded-lg border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-700 shadow-sm hover:bg-zinc-50">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        <div id="add-event-modal" class="modal-overlay fixed inset-0 z-50 place-items-center bg-zinc-950/55 p-4 {{ $errors->any() && ! $editEvent ? 'is-open' : '' }}">
            <div class="w-full max-w-3xl rounded-lg bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-zinc-200 p-5">
                    <div>
                        <h2 class="font-bold">Add Event</h2>
                        <p class="mt-1 text-sm text-zinc-500">Create a scheduled attendance event.</p>
                    </div>
                    <a href="{{ route('schedule') }}" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-semibold text-zinc-700 hover:bg-zinc-50">Close</a>
                </div>

                <form method="POST" action="{{ route('schedule.store') }}" class="grid gap-4 p-5 lg:grid-cols-2">
                    @csrf

                    @if ($errors->any() && ! $editEvent)
                        <div class="rounded-lg border border-rose-100 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700 lg:col-span-2">
                            Please check the event details and try again.
                        </div>
                    @endif

                    <label class="grid gap-1 text-sm font-semibold text-zinc-700 lg:col-span-2">
                        Event Title
                        <input name="title" value="{{ old('title') }}" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500" required>
                        @error('title') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                    </label>

                    <label class="grid gap-1 text-sm font-semibold text-zinc-700">
                        Date
                        <input type="date" name="event_date" value="{{ old('event_date') }}" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500" required>
                        @error('event_date') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                    </label>

                    <label class="grid gap-1 text-sm font-semibold text-zinc-700">
                        Status
                        <select name="status" class="rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500" required>
                            @foreach (['Scheduled', 'Upcoming', 'In Progress', 'Completed', 'Cancelled'] as $status)
                                <option value="{{ $status }}" @selected(old('status', 'Scheduled') === $status)>{{ $status }}</option>
                            @endforeach
                        </select>
                        @error('status') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                    </label>

                    <label class="grid gap-1 text-sm font-semibold text-zinc-700">
                        Start Time
                        <input type="time" name="start_time" value="{{ old('start_time') }}" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500" required>
                        @error('start_time') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                    </label>

                    <label class="grid gap-1 text-sm font-semibold text-zinc-700">
                        End Time
                        <input type="time" name="end_time" value="{{ old('end_time') }}" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500" required>
                        @error('end_time') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                    </label>

                    <label class="grid gap-1 text-sm font-semibold text-zinc-700">
                        Venue
                        <input name="venue" value="{{ old('venue') }}" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500" required>
                        @error('venue') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                    </label>

                    <div class="flex flex-wrap items-center gap-3 lg:col-span-2">
                        <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">Save Event</button>
                        <a href="{{ route('schedule') }}" class="rounded-lg border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-700 shadow-sm hover:bg-zinc-50">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($page === 'booths')
        <div id="add-booth-modal" class="modal-overlay fixed inset-0 z-50 place-items-center bg-zinc-950/55 p-4 {{ $errors->any() && ! $editBooth ? 'is-open' : '' }}">
            <div class="w-full max-w-2xl rounded-lg bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-zinc-200 p-5">
                    <div>
                        <h2 class="font-bold">Add Scanner Operator</h2>
                        <p class="mt-1 text-sm text-zinc-500">Create a scanner station and its operator login.</p>
                    </div>
                    <a href="{{ route('booths') }}" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-semibold text-zinc-700 hover:bg-zinc-50">Close</a>
                </div>

                <form method="POST" action="{{ route('booths.store') }}" class="grid gap-4 p-5 md:grid-cols-2">
                    @csrf

                    <label class="grid gap-1 text-sm font-semibold text-zinc-700 md:col-span-2">
                        Scanner Station
                        <input name="name" value="{{ old('name') }}" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500" required>
                        @error('name') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                    </label>

                    <label class="grid gap-1 text-sm font-semibold text-zinc-700">
                        Operator Name
                        <input name="admin_name" value="{{ old('admin_name') }}" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500" required>
                        @error('admin_name') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                    </label>

                    <label class="grid gap-1 text-sm font-semibold text-zinc-700">
                        Operator Email
                        <input type="email" name="admin_email" value="{{ old('admin_email') }}" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500" required>
                        @error('admin_email') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                    </label>

                    <label class="grid gap-1 text-sm font-semibold text-zinc-700">
                        Password
                        <input type="password" name="password" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500" required>
                        @error('password') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                    </label>

                    <label class="grid gap-1 text-sm font-semibold text-zinc-700">
                        Confirm Password
                        <input type="password" name="password_confirmation" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500" required>
                    </label>

                    <div class="flex flex-wrap items-center gap-3 md:col-span-2">
                        <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">Save Scanner Operator</button>
                        <a href="{{ route('booths') }}" class="rounded-lg border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-700 shadow-sm hover:bg-zinc-50">Cancel</a>
                    </div>
                </form>
            </div>
        </div>

        @if ($editBooth)
            <div id="edit-booth-modal" class="modal-overlay fixed inset-0 z-50 place-items-center overflow-y-auto bg-zinc-950/55 p-4 is-open">
                <div class="w-full max-w-2xl rounded-lg bg-white shadow-xl">
                    <div class="flex items-center justify-between border-b border-zinc-200 p-5">
                        <div>
                            <h2 class="font-bold">Editing Scanner Operator</h2>
                            <p class="mt-1 text-sm text-zinc-500">Update the scanner station and operator login.</p>
                        </div>
                        <a href="{{ route('booths') }}" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-semibold text-zinc-700 hover:bg-zinc-50">Close</a>
                    </div>

                    <form
                        id="booth-form"
                        method="POST"
                        action="{{ route('booths.update', $editBooth) }}"
                        class="grid gap-4 p-5 md:grid-cols-2"
                    >
                        @csrf
                        @method('PATCH')

                        <label class="grid gap-1 text-sm font-semibold text-zinc-700 md:col-span-2">
                            Scanner Station
                            <input name="name" value="{{ old('name', $editBooth->name) }}" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500" required>
                            @error('name') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>

                        <label class="grid gap-1 text-sm font-semibold text-zinc-700">
                            Operator Name
                            <input name="admin_name" value="{{ old('admin_name', $editBooth->user?->name ?? $editBooth->admin_name) }}" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500" required>
                            @error('admin_name') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>

                        <label class="grid gap-1 text-sm font-semibold text-zinc-700">
                            Operator Email
                            <input type="email" name="admin_email" value="{{ old('admin_email', $editBooth->user?->email ?? $editBooth->admin_email) }}" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500" required>
                            @error('admin_email') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>

                        <label class="grid gap-1 text-sm font-semibold text-zinc-700">
                            New Password
                            <input type="password" name="password" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500" {{ $editBooth->user ? '' : 'required' }}>
                            @error('password') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>

                        <label class="grid gap-1 text-sm font-semibold text-zinc-700">
                            Confirm Password
                            <input type="password" name="password_confirmation" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500" {{ $editBooth->user ? '' : 'required' }}>
                        </label>

                        <div class="flex flex-wrap items-center gap-3 md:col-span-2">
                            <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">Update Scanner Operator</button>
                            <a href="{{ route('booths') }}" class="rounded-lg border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-700 shadow-sm hover:bg-zinc-50">Cancel Edit</a>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    @endif

    @if ($page === 'scanner')
        <script>
            const scanEndpoint = '{{ route('scanner.scan') }}';
            const scannerHasActiveEvent = @json((bool) $scannerCurrentEvent);
            const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
            const video = document.getElementById('qr-video');
            const startButton = document.getElementById('start-scanner');
            const stopButton = document.getElementById('stop-scanner');
            const boothSelect = document.getElementById('scanner-booth-id');
            const scannerMessage = document.getElementById('scanner-message');
            const latestEvent = document.getElementById('latest-event');
            const latestScanType = document.getElementById('latest-scan-type');
            const latestStudent = document.getElementById('latest-student');
            const latestStudentId = document.getElementById('latest-student-id');
            const latestVerificationCode = document.getElementById('latest-verification-code');
            const latestCourse = document.getElementById('latest-course');
            const latestBooth = document.getElementById('latest-booth');
            const latestTime = document.getElementById('latest-time');
            let scannerStream = null;
            let barcodeDetector = null;
            let scanTimer = null;
            let lastPayload = '';
            let lastScannedAt = 0;
            let scannerMessageTimer = null;
            let scannerAudioContext = null;
            const fallbackCanvas = document.createElement('canvas');
            const fallbackContext = fallbackCanvas.getContext('2d', { willReadFrequently: true });

            function setScannerMessage(message, tone = 'neutral', autoClear = false) {
                const classes = {
                    neutral: 'mt-4 rounded-lg border border-zinc-200 bg-zinc-50 px-4 py-3 text-center text-base font-semibold text-zinc-600',
                    success: 'mt-4 rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-3 text-center text-base font-semibold text-emerald-700',
                    error: 'mt-4 rounded-lg border border-rose-100 bg-rose-50 px-4 py-3 text-center text-base font-semibold text-rose-700',
                };

                if (scannerMessageTimer) {
                    window.clearTimeout(scannerMessageTimer);
                    scannerMessageTimer = null;
                }

                scannerMessage.className = classes[tone] ?? classes.neutral;
                scannerMessage.textContent = message;

                if (autoClear) {
                    scannerMessageTimer = window.setTimeout(() => {
                        scannerMessage.className = classes.neutral;
                        scannerMessage.textContent = 'Ready for next scan.';
                        scannerMessageTimer = null;
                    }, 2000);
                }
            }

            function playScanBuzzer() {
                const AudioContext = window.AudioContext || window.webkitAudioContext;

                if (! AudioContext) {
                    return;
                }

                scannerAudioContext ??= new AudioContext();

                if (scannerAudioContext.state === 'suspended') {
                    scannerAudioContext.resume();
                }

                [0, 0.11].forEach((offset) => {
                    const oscillator = scannerAudioContext.createOscillator();
                    const gain = scannerAudioContext.createGain();
                    const startAt = scannerAudioContext.currentTime + offset;
                    const stopAt = startAt + 0.08;

                    oscillator.type = 'square';
                    oscillator.frequency.setValueAtTime(880, startAt);
                    gain.gain.setValueAtTime(0.0001, startAt);
                    gain.gain.exponentialRampToValueAtTime(0.16, startAt + 0.01);
                    gain.gain.exponentialRampToValueAtTime(0.0001, stopAt);
                    oscillator.connect(gain);
                    gain.connect(scannerAudioContext.destination);
                    oscillator.start(startAt);
                    oscillator.stop(stopAt);
                });
            }

            function selectedBoothId() {
                return boothSelect?.value ?? '';
            }

            function updateLatestScan(record) {
                latestEvent.textContent = record.event;
                latestScanType.textContent = record.scan_type_label;
                latestStudent.textContent = record.student;
                latestStudentId.textContent = record.student_id;
                latestVerificationCode.textContent = record.verification_code;
                latestCourse.textContent = `${record.course} / ${record.block} / ${record.year_level}`;
                latestBooth.textContent = record.booth;
                latestTime.textContent = record.time;
            }

            async function submitScan(payload) {
                if (! scannerHasActiveEvent) {
                    setScannerMessage("Set an event's status to In Progress before scanning.", 'error');
                    return;
                }

                const cleanPayload = payload.trim();

                if (! cleanPayload) {
                    setScannerMessage('Scan a signed student QR code.', 'error', true);
                    return;
                }

                if (! selectedBoothId()) {
                    setScannerMessage('Select a booth before scanning.', 'error', true);
                    return;
                }

                const now = Date.now();

                if (cleanPayload === lastPayload && now - lastScannedAt < 2500) {
                    return;
                }

                lastPayload = cleanPayload;
                lastScannedAt = now;

                try {
                    const response = await fetch(scanEndpoint, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            payload: cleanPayload,
                            booth_id: selectedBoothId(),
                        }),
                    });
                    const data = await response.json();

                    if (! response.ok) {
                        throw new Error(data.message ?? 'Scan could not be saved.');
                    }

                    updateLatestScan(data.record);
                    playScanBuzzer();
                    setScannerMessage(data.message, 'success', true);
                } catch (error) {
                    setScannerMessage(error.message, 'error', true);
                }
            }

            async function scanFrame() {
                if (! video || video.readyState < HTMLMediaElement.HAVE_CURRENT_DATA) {
                    return;
                }

                try {
                    if (barcodeDetector) {
                        const codes = await barcodeDetector.detect(video);

                        if (codes.length > 0) {
                            await submitScan(codes[0].rawValue);
                        }
                    } else if (window.jsQR && fallbackContext) {
                        fallbackCanvas.width = video.videoWidth;
                        fallbackCanvas.height = video.videoHeight;
                        fallbackContext.drawImage(video, 0, 0, fallbackCanvas.width, fallbackCanvas.height);
                        const frame = fallbackContext.getImageData(0, 0, fallbackCanvas.width, fallbackCanvas.height);
                        const code = window.jsQR(frame.data, frame.width, frame.height, { inversionAttempts: 'attemptBoth' });

                        if (code?.data) {
                            await submitScan(code.data);
                        }
                    }
                } catch (error) {
                    setScannerMessage('Camera scanner paused. Scan a signed student QR code to continue.', 'error');
                }
            }

            async function startScanner() {
                if (! scannerHasActiveEvent) {
                    setScannerMessage("Set an event's status to In Progress before starting the scanner.", 'error');
                    return;
                }

                try {
                    barcodeDetector = 'BarcodeDetector' in window
                        ? new BarcodeDetector({ formats: ['qr_code'] })
                        : null;

                    if (! barcodeDetector && ! window.jsQR) {
                        setScannerMessage('This browser has no QR decoder available. Load the QR fallback to scan signed QR codes.', 'error');
                        return;
                    }

                    scannerStream = await navigator.mediaDevices.getUserMedia({
                        video: {
                            facingMode: 'environment',
                        },
                    });
                    video.srcObject = scannerStream;
                    await video.play();
                    scanTimer = window.setInterval(scanFrame, 650);
                    setScannerMessage(barcodeDetector
                        ? 'Scanner is ready. Point the camera at a student QR code.'
                        : 'Fallback scanner is ready. Point the camera at a student QR code.', 'success');
                } catch (error) {
                    setScannerMessage('Camera access failed. Use HTTPS or localhost, then scan a signed student QR code.', 'error');
                }
            }

            function stopScanner() {
                if (scanTimer) {
                    window.clearInterval(scanTimer);
                    scanTimer = null;
                }

                if (scannerStream) {
                    scannerStream.getTracks().forEach((track) => track.stop());
                    scannerStream = null;
                }

                if (video) {
                    video.srcObject = null;
                }

                setScannerMessage('Scanner stopped. Start the camera and scan a student QR code.');
            }

            startButton?.addEventListener('click', startScanner);
            stopButton?.addEventListener('click', stopScanner);
            window.addEventListener('beforeunload', stopScanner);

            @if (auth()->user()?->role === 'booth' && auth()->user()->booth)
            const heartbeatStatus = document.getElementById('heartbeat-status');

            async function sendBoothHeartbeat() {
                try {
                    const response = await fetch('{{ route('booths.heartbeat') }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                        },
                    });

                    if (! response.ok) {
                        throw new Error('Heartbeat failed');
                    }

                    heartbeatStatus.textContent = 'Online';
                    heartbeatStatus.className = 'rounded-full bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-700';
                } catch (error) {
                    heartbeatStatus.textContent = 'Offline';
                    heartbeatStatus.className = 'rounded-full bg-zinc-200 px-2 py-1 text-xs font-semibold text-zinc-700';
                }
            }

            sendBoothHeartbeat();
            setInterval(sendBoothHeartbeat, 30000);
            @endif
        </script>
    @endif

    @if ($page === 'students')
        <script>
            async function refreshStudentQRCodes() {
                document.querySelectorAll('[data-student-qr-image]').forEach(async (image) => {
                    try {
                        const response = await fetch(image.dataset.qrEndpoint, {
                            headers: { 'Accept': 'application/json' },
                        });

                        if (! response.ok) {
                            return;
                        }

                        const qr = await response.json();
                        image.src = qr.image_url;
                        image.closest('[id^="student-details-modal-"]')?.querySelector('[data-student-qr-code]')?.replaceChildren(qr.verification_code);
                    } catch (error) {
                        // Keep the current code visible if a refresh fails.
                    }
                });
            }

            window.setInterval(refreshStudentQRCodes, 60000);

            const selectAllStudents = document.getElementById('select-all-students');
            const studentSelections = Array.from(document.querySelectorAll('.student-selection'));
            const deleteSelectedStudents = document.getElementById('delete-selected-students');

            function updateStudentSelection() {
                const selectedCount = studentSelections.filter((checkbox) => checkbox.checked).length;

                if (selectAllStudents) {
                    selectAllStudents.checked = selectedCount > 0 && selectedCount === studentSelections.length;
                    selectAllStudents.indeterminate = selectedCount > 0 && selectedCount < studentSelections.length;
                }

                if (deleteSelectedStudents) {
                    deleteSelectedStudents.disabled = selectedCount === 0;
                    deleteSelectedStudents.textContent = selectedCount > 0 ? `Delete Selected (${selectedCount})` : 'Delete Selected';
                }
            }

            selectAllStudents?.addEventListener('change', () => {
                studentSelections.forEach((checkbox) => {
                    checkbox.checked = selectAllStudents.checked;
                });
                updateStudentSelection();
            });
            studentSelections.forEach((checkbox) => checkbox.addEventListener('change', updateStudentSelection));

            const bulkStudentRows = document.getElementById('bulk-student-rows');
            const bulkStudentRowTemplate = document.getElementById('bulk-student-row-template');
            const addStudentRow = document.getElementById('add-student-row');
            let spreadsheetFill = null;

            function reindexBulkStudentRows() {
                const rows = Array.from(bulkStudentRows?.querySelectorAll('.bulk-student-row') ?? []);

                rows.forEach((row, index) => {
                    row.querySelector('.row-number').textContent = index + 1;
                    row.querySelectorAll('[name], [data-field]').forEach((field) => {
                        const fieldName = field.dataset.field ?? field.name.match(/\[([^\]]+)]$/)?.[1];

                        if (fieldName) {
                            field.name = `students[${index}][${fieldName}]`;
                        }
                    });
                });
            }

            function bindRemoveStudentRow(button) {
                button.addEventListener('click', () => {
                    const rows = bulkStudentRows.querySelectorAll('.bulk-student-row');

                    if (rows.length === 1) {
                        rows[0].querySelectorAll('input, select').forEach((field) => field.value = '');
                        return;
                    }

                    button.closest('.bulk-student-row').remove();
                    reindexBulkStudentRows();
                });
            }

            function fillSpreadsheetCell(cell) {
                if (! spreadsheetFill || cell?.dataset.fillField !== spreadsheetFill.field) {
                    return;
                }

                const field = cell.querySelector('input, select');

                if (field && field.value !== spreadsheetFill.value) {
                    field.value = spreadsheetFill.value;
                    field.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }

            function bindSpreadsheetFill(row) {
                row.querySelectorAll('.spreadsheet-fill-cell').forEach((cell) => {
                    const field = cell.querySelector('input, select');
                    const handle = cell.querySelector('.spreadsheet-fill-handle');

                    handle.addEventListener('pointerdown', (event) => {
                        if (! field.value) {
                            return;
                        }

                        event.preventDefault();
                        spreadsheetFill = { field: cell.dataset.fillField, value: field.value };
                        document.body.classList.add('select-none');
                    });
                    cell.addEventListener('pointerenter', () => fillSpreadsheetCell(cell));
                });
            }

            function stopSpreadsheetFill() {
                spreadsheetFill = null;
                document.body.classList.remove('select-none');
            }

            bulkStudentRows?.querySelectorAll('.remove-student-row').forEach(bindRemoveStudentRow);
            bulkStudentRows?.querySelectorAll('.bulk-student-row').forEach(bindSpreadsheetFill);
            document.addEventListener('pointermove', (event) => {
                if (! spreadsheetFill) {
                    return;
                }

                const cell = document.elementFromPoint(event.clientX, event.clientY)?.closest('.spreadsheet-fill-cell');
                fillSpreadsheetCell(cell);
            });
            document.addEventListener('pointerup', stopSpreadsheetFill);
            document.addEventListener('pointercancel', stopSpreadsheetFill);
            addStudentRow?.addEventListener('click', () => {
                if (bulkStudentRows.children.length >= 100) {
                    return;
                }

                const row = bulkStudentRowTemplate.content.firstElementChild.cloneNode(true);
                bulkStudentRows.append(row);
                bindRemoveStudentRow(row.querySelector('.remove-student-row'));
                bindSpreadsheetFill(row);
                reindexBulkStudentRows();
                row.querySelector('input')?.focus();
            });

            const bulkStudentForm = bulkStudentRows?.closest('form');
            const bulkStudentProgress = document.getElementById('bulk-student-progress');
            let bulkStudentSubmitStarted = false;

            bulkStudentForm?.addEventListener('submit', (event) => {
                if (bulkStudentSubmitStarted) {
                    event.preventDefault();
                    return;
                }

                bulkStudentSubmitStarted = true;
                bulkStudentForm.querySelector('button[type="submit"]')?.setAttribute('disabled', 'disabled');
                bulkStudentProgress?.classList.remove('hidden');
                bulkStudentProgress?.classList.add('grid');
            });
        </script>
    @endif

    @if ($page === 'booths')
        <script>
            const boothStatusEndpoint = '{{ route('booths.statuses') }}';
            const boothStatusClasses = {
                Online: 'rounded-full px-2 py-1 text-xs font-semibold bg-emerald-50 text-emerald-700',
                Review: 'rounded-full px-2 py-1 text-xs font-semibold bg-amber-50 text-amber-700',
                Offline: 'rounded-full px-2 py-1 text-xs font-semibold bg-zinc-100 text-zinc-600',
            };

            async function refreshBoothStatuses() {
                if (document.hidden) {
                    return;
                }

                try {
                    const response = await fetch(boothStatusEndpoint, {
                        headers: {
                            'Accept': 'application/json',
                        },
                    });

                    if (! response.ok) {
                        return;
                    }

                    const data = await response.json();

                    data.booths.forEach((booth) => {
                        const card = document.querySelector(`[data-booth-card="${booth.id}"]`);

                        if (! card) {
                            return;
                        }

                        const status = card.querySelector('[data-booth-status]');
                        const scans = card.querySelector('[data-booth-scans]');
                        const lastSeen = card.querySelector('[data-booth-last-seen]');

                        if (status) {
                            status.textContent = booth.status;
                            status.className = boothStatusClasses[booth.status] ?? boothStatusClasses.Offline;
                        }

                        if (scans) {
                            scans.textContent = booth.scans;
                        }

                        if (lastSeen) {
                            lastSeen.textContent = booth.last_seen;
                        }
                    });
                } catch (error) {
                    //
                }
            }

            refreshBoothStatuses();
            setInterval(refreshBoothStatuses, 2000);
        </script>
    @endif
</body>
</html>

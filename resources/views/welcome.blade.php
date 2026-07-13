@php
    $page = $page ?? 'dashboard';

    $navItems = [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'DB', 'route' => 'dashboard'],
        ['key' => 'users', 'label' => 'Users', 'icon' => 'US', 'route' => 'users'],
        ['key' => 'students', 'label' => 'Students', 'icon' => 'ST', 'route' => 'students'],
        ['key' => 'scanner', 'label' => 'QR Scanner', 'icon' => 'QR', 'route' => 'scanner'],
        ['key' => 'records', 'label' => 'Attendance Records', 'icon' => 'AR', 'route' => 'records'],
        ['key' => 'schedule', 'label' => 'Event Schedule', 'icon' => 'EV', 'route' => 'schedule'],
        ['key' => 'booths', 'label' => 'Booths', 'icon' => 'BH', 'route' => 'booths'],
    ];

    $isBoothUser = auth()->user()?->role === 'booth';

    if ($isBoothUser) {
        $navItems = [
            ['key' => 'scanner', 'label' => 'QR Scanner', 'icon' => 'QR', 'route' => 'scanner'],
        ];
    }

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
    $userFilters = array_merge(['role' => null], $userFilters ?? []);
    $userCounts = array_merge([
        'admin' => $users->where('role', 'admin')->count(),
        'booth' => $users->where('role', 'booth')->count(),
    ], $userCounts ?? []);
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
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

                @unless ($isBoothUser)
                    <div class="mt-auto rounded-lg border border-zinc-200 bg-zinc-50 p-4">
                        <p class="text-sm font-semibold">Today</p>
                        <p class="mt-1 text-2xl font-bold">86.9%</p>
                        <p class="mt-1 text-sm text-zinc-500">Campus attendance rate</p>
                    </div>
                @endunless
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
                    @unless ($isBoothUser)
                        <button class="rounded-lg border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-700 shadow-sm hover:bg-zinc-50">Export</button>
                    @endunless
                    @auth
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="rounded-lg border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-700 shadow-sm hover:bg-zinc-50">Logout</button>
                        </form>
                    @endauth
                    @if (! $isBoothUser && $page === 'students')
                        <a href="#import-students-modal" class="rounded-lg border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-700 shadow-sm hover:bg-zinc-50">Import</a>
                        <a href="#add-student-modal" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">Add Record</a>
                    @elseif (! $isBoothUser && $page === 'records')
                        <a href="#add-attendance-modal" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">Add Record</a>
                    @elseif (! $isBoothUser && $page === 'schedule')
                        <a href="#add-event-modal" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">Add Record</a>
                    @elseif (! $isBoothUser && $page === 'booths')
                        <a href="#add-booth-modal" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">Add Record</a>
                    @elseif (! $isBoothUser && $page === 'users')
                        <a href="#add-admin-modal" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">Add Admin</a>
                    @elseif (! $isBoothUser && $page !== 'users')
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

            @if ($page === 'users')
                <section class="grid gap-6">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <article class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm">
                            <p class="text-sm font-semibold text-zinc-500">Admin Accounts</p>
                            <p class="mt-3 text-3xl font-bold">{{ $userCounts['admin'] }}</p>
                        </article>
                        <article class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm">
                            <p class="text-sm font-semibold text-zinc-500">Booth Accounts</p>
                            <p class="mt-3 text-3xl font-bold">{{ $userCounts['booth'] }}</p>
                        </article>
                    </div>

                    <section class="rounded-lg border border-zinc-200 bg-white shadow-sm">
                        <div class="flex flex-col gap-3 border-b border-zinc-200 p-5 sm:flex-row sm:items-center sm:justify-between">
                            <h2 class="font-bold">User Accounts</h2>
                            <form method="GET" action="{{ route('users') }}" class="flex items-center gap-2">
                                <select name="role" class="rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm font-semibold text-zinc-700 outline-none focus:border-emerald-500">
                                    <option value="" @selected(! filled($userFilters['role']))>All users</option>
                                    <option value="admin" @selected($userFilters['role'] === 'admin')>Admins only</option>
                                    <option value="booth" @selected($userFilters['role'] === 'booth')>Booths only</option>
                                </select>
                                <button class="rounded-lg bg-zinc-950 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-zinc-800">Apply</button>
                                @if (filled($userFilters['role']))
                                    <a href="{{ route('users') }}" class="rounded-lg border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-700 shadow-sm hover:bg-zinc-50">Reset</a>
                                @endif
                            </form>
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
                                    User Type
                                    <select name="role" class="rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500" required>
                                        <option value="admin" @selected(old('role', $editUser->role) === 'admin')>Admin</option>
                                        <option value="booth" @selected(old('role', $editUser->role) === 'booth')>Booth</option>
                                    </select>
                                    @error('role') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                                </label>

                                <label class="grid gap-1 text-sm font-semibold text-zinc-700">
                                    New Password
                                    <input type="password" name="password" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500">
                                    @error('password') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
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
                                        <th class="px-5 py-3">User Type</th>
                                        <th class="px-5 py-3">Assigned Booth</th>
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
                                                <a href="{{ route('students', array_merge($activeFilters, ['edit' => $student['id']])) }}#student-form" class="rounded-lg border border-zinc-300 px-3 py-2 text-xs font-semibold text-zinc-700 hover:bg-zinc-50">Edit</a>
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
                                            <td colspan="7" class="px-5 py-4 text-sm text-zinc-500">
                                                {{ $studentsLoaded ? 'No students match the current filters.' : 'Use the search or filters above, then click Apply to load students.' }}
                                            </td>
                                        </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="flex flex-col gap-3 border-t border-zinc-200 p-5 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-sm text-zinc-500">
                            @if ($studentsLoaded)
                                <span class="font-semibold text-zinc-800">{{ $students->count() }}</span> student record(s) match the current filters.
                            @else
                                Student records are loaded only after applying a search or filter.
                            @endif
                        </p>
                        @if (count($activeFilters) > 0 && $students->isNotEmpty())
                            <a href="{{ $studentsQrPrintUrl }}" class="rounded-lg bg-zinc-950 px-4 py-2 text-center text-sm font-semibold text-white shadow-sm hover:bg-zinc-800">Print QR</a>
                        @endif
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
                            Start the camera or enter a student ID manually.
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

                        <form id="manual-scan-form" class="mt-5 grid gap-3 rounded-lg border border-zinc-200 bg-zinc-50 p-4 md:grid-cols-[1fr_auto] md:items-end">
                            <label class="grid gap-1 text-sm font-semibold text-zinc-700">
                                Manual Student ID
                                <input id="manual-student-code" @disabled(! $scannerCurrentEvent) class="rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500 disabled:cursor-not-allowed disabled:bg-zinc-100" placeholder="STU-24017">
                            </label>
                            <button @disabled(! $scannerCurrentEvent) class="rounded-lg bg-zinc-950 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-zinc-800 disabled:cursor-not-allowed disabled:bg-zinc-300">Submit Scan</button>
                        </form>
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
                                                        <span class="font-semibold text-zinc-800">{{ $attendee['time_out'] ?? 'Pending' }}</span>
                                                        <span class="ml-2 rounded-full px-2 py-1 text-xs font-semibold {{ $attendee['time_out_status'] === 'Done' ? 'bg-emerald-50 text-emerald-700' : 'bg-zinc-100 text-zinc-600' }}">{{ $attendee['time_out_status'] }}</span>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="4" class="px-5 py-4 text-sm text-zinc-500">
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
                                            <p class="mt-2 text-xs font-semibold text-zinc-500">Scan phase: <span class="text-zinc-800">{{ $event['scan_phase_label'] }}</span></p>
                                            <p class="mt-1 text-xs font-semibold text-zinc-500">Time-in: <span class="{{ $event['time_in_open'] ? 'text-emerald-700' : 'text-rose-700' }}">{{ $event['time_in_open'] ? 'Open' : 'Closed' }}</span></p>
                                        @endif
                                    </div>
                                    <div class="grid gap-3 md:col-span-2 xl:col-span-1 xl:text-right">
                                        <span class="justify-self-start rounded-full px-2 py-1 text-xs font-semibold xl:justify-self-end {{ $event['status'] === 'In Progress' ? 'bg-emerald-50 text-emerald-700' : ($event['status'] === 'Upcoming' ? 'bg-sky-50 text-sky-700' : 'bg-zinc-100 text-zinc-600') }}">{{ $event['status'] }}</span>
                                        <div class="flex flex-wrap gap-2 xl:justify-end">
                                            <a href="{{ route('schedule', ['edit' => $event['id']]) }}#edit-event-modal" class="rounded-lg border border-zinc-300 px-3 py-2 text-xs font-semibold text-zinc-700 hover:bg-zinc-50">Edit</a>
                                            @if ($event['status'] === 'In Progress')
                                                <form method="POST" action="{{ route('schedule.scan-phase', $event['id']) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    @if ($event['scan_phase'] === 'time_in')
                                                        <input type="hidden" name="scan_phase" value="verification">
                                                        <button class="whitespace-nowrap rounded-lg border border-emerald-200 px-3 py-2 text-xs font-semibold text-emerald-700 hover:bg-emerald-50">Activate Verification</button>
                                                    @else
                                                        <input type="hidden" name="scan_phase" value="time_in">
                                                        <button class="whitespace-nowrap rounded-lg border border-zinc-300 px-3 py-2 text-xs font-semibold text-zinc-700 hover:bg-zinc-50">Set Time-in</button>
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
                                            <form method="POST" action="{{ route('schedule.destroy', $event['id']) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button class="rounded-lg border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-50">Delete</button>
                                            </form>
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
                                No booths yet. Use Add Record to add the first booth.
                            </div>
                        @endforelse
                    </div>
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
                $studentIndividualQrPrintUrl = route('students.qr-print', array_merge($activeFilters, ['student' => $student['id']]));
            @endphp

            <div id="student-qr-modal-{{ $student['id'] }}" class="modal-overlay fixed inset-0 z-50 place-items-center bg-zinc-950/55 p-4">
                <div class="w-full max-w-xl rounded-lg bg-white shadow-xl">
                    <div class="flex items-center justify-between border-b border-zinc-200 p-5">
                        <div>
                            <h2 class="font-bold">Student QR</h2>
                            <p class="mt-1 text-sm text-zinc-500">{{ $studentName }} - {{ $student['student_id'] }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <a href="{{ $studentIndividualQrPrintUrl }}" class="rounded-lg bg-zinc-950 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-zinc-800">Print Individual QR</a>
                            <a href="{{ $studentsFilteredUrl }}" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-semibold text-zinc-700 hover:bg-zinc-50">Close</a>
                        </div>
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
                        <p class="mt-1 text-sm text-zinc-500">Add an admin login account.</p>
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
                        <h2 class="font-bold">Add Booth</h2>
                        <p class="mt-1 text-sm text-zinc-500">Create a booth and assign its user-admin.</p>
                    </div>
                    <a href="{{ route('booths') }}" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-semibold text-zinc-700 hover:bg-zinc-50">Close</a>
                </div>

                <form method="POST" action="{{ route('booths.store') }}" class="grid gap-4 p-5 md:grid-cols-2">
                    @csrf

                    <label class="grid gap-1 text-sm font-semibold text-zinc-700 md:col-span-2">
                        Booth Name
                        <input name="name" value="{{ old('name') }}" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500" required>
                        @error('name') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                    </label>

                    <label class="grid gap-1 text-sm font-semibold text-zinc-700">
                        User-Admin
                        <input name="admin_name" value="{{ old('admin_name') }}" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500" required>
                        @error('admin_name') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                    </label>

                    <label class="grid gap-1 text-sm font-semibold text-zinc-700">
                        Admin Email
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
                        <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">Save Booth</button>
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
                            <h2 class="font-bold">Editing Booth</h2>
                            <p class="mt-1 text-sm text-zinc-500">Update booth details and assigned user-admin.</p>
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
                            Booth Name
                            <input name="name" value="{{ old('name', $editBooth->name) }}" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500" required>
                            @error('name') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>

                        <label class="grid gap-1 text-sm font-semibold text-zinc-700">
                            User-Admin
                            <input name="admin_name" value="{{ old('admin_name', $editBooth->user?->name ?? $editBooth->admin_name) }}" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500" required>
                            @error('admin_name') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>

                        <label class="grid gap-1 text-sm font-semibold text-zinc-700">
                            Admin Email
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
                            <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">Update Booth</button>
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
            const manualForm = document.getElementById('manual-scan-form');
            const manualInput = document.getElementById('manual-student-code');
            const boothSelect = document.getElementById('scanner-booth-id');
            const scannerMessage = document.getElementById('scanner-message');
            const latestEvent = document.getElementById('latest-event');
            const latestScanType = document.getElementById('latest-scan-type');
            const latestStudent = document.getElementById('latest-student');
            const latestStudentId = document.getElementById('latest-student-id');
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
                    setScannerMessage('Scan a QR code or enter a student ID.', 'error', true);
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
                    manualInput.value = '';
                } catch (error) {
                    setScannerMessage(error.message, 'error', true);
                }
            }

            async function scanFrame() {
                if (! barcodeDetector || ! video || video.readyState < HTMLMediaElement.HAVE_CURRENT_DATA) {
                    return;
                }

                try {
                    const codes = await barcodeDetector.detect(video);

                    if (codes.length > 0) {
                        await submitScan(codes[0].rawValue);
                    }
                } catch (error) {
                    setScannerMessage('Camera scanner paused. You can still enter a student ID manually.', 'error');
                }
            }

            async function startScanner() {
                if (! scannerHasActiveEvent) {
                    setScannerMessage("Set an event's status to In Progress before starting the scanner.", 'error');
                    return;
                }

                if (! ('BarcodeDetector' in window)) {
                    setScannerMessage('This browser does not support camera QR scanning. Use the manual student ID field.', 'error');
                    return;
                }

                try {
                    barcodeDetector = new BarcodeDetector({ formats: ['qr_code'] });
                    scannerStream = await navigator.mediaDevices.getUserMedia({
                        video: {
                            facingMode: 'environment',
                        },
                    });
                    video.srcObject = scannerStream;
                    await video.play();
                    scanTimer = window.setInterval(scanFrame, 650);
                    setScannerMessage('Scanner is ready. Point the camera at a student QR code.', 'success');
                } catch (error) {
                    setScannerMessage('Camera access failed. Use HTTPS, localhost, or the manual student ID field.', 'error');
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

                setScannerMessage('Scanner stopped. Start the camera or enter a student ID manually.');
            }

            startButton?.addEventListener('click', startScanner);
            stopButton?.addEventListener('click', stopScanner);
            manualForm?.addEventListener('submit', (event) => {
                event.preventDefault();
                submitScan(manualInput.value);
            });

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

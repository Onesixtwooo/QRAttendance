<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#064e3b">
    <title>Sign in | OLSHCO Attendance</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-stone-50 font-sans text-zinc-950 antialiased">
    <main class="grid min-h-screen lg:grid-cols-[minmax(0,1.1fr)_minmax(31rem,0.9fr)]">
        <section class="relative isolate hidden min-h-screen overflow-hidden bg-emerald-950 px-12 py-10 text-white lg:flex lg:flex-col xl:px-20 xl:py-14">
            <div class="absolute inset-0 -z-10 opacity-[0.12]" aria-hidden="true" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 28px 28px;"></div>
            <div class="absolute -right-32 top-1/2 -z-10 h-96 w-96 -translate-y-1/2 rounded-full border border-white/10" aria-hidden="true"></div>
            <div class="absolute -right-12 top-1/2 -z-10 h-64 w-64 -translate-y-1/2 rounded-full border border-white/10" aria-hidden="true"></div>
            <div class="absolute -bottom-36 -left-24 -z-10 h-80 w-80 rounded-full bg-emerald-600/25 blur-3xl" aria-hidden="true"></div>

            <a href="{{ url('/') }}" class="flex w-fit items-center gap-3.5 rounded-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-300 focus-visible:ring-offset-4 focus-visible:ring-offset-emerald-950" aria-label="OLSHCO home">
                <span class="grid h-12 w-12 place-items-center rounded-xl bg-white text-emerald-950 shadow-lg shadow-black/10">
                    <svg viewBox="0 0 32 32" class="h-7 w-7" aria-hidden="true">
                        <path fill="currentColor" d="M4 4h10v10H4V4Zm3 3v4h4V7H7Zm11-3h10v10H18V4Zm3 3v4h4V7h-4ZM4 18h10v10H4V18Zm3 3v4h4v-4H7Zm11-3h4v4h-4v-4Zm6 0h4v7h-3v3h-7v-4h6v-6Zm-6 6h4v4h-4v-4Z"/>
                    </svg>
                </span>
                <span>
                    <span class="block text-lg font-bold leading-tight tracking-tight">OLSHCO</span>
                    <span class="block text-sm text-emerald-100/70">College Department</span>
                </span>
            </a>

            <div class="my-auto max-w-2xl py-16">
                <div class="mb-7 inline-flex items-center gap-2 rounded-full border border-emerald-300/20 bg-white/10 px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.16em] text-emerald-200 backdrop-blur-sm">
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-300"></span>
                    Student attendance monitoring
                </div>
                <h1 class="max-w-xl text-5xl font-bold leading-[1.08] tracking-[-0.04em] xl:text-6xl">
                    Every check-in,<br>
                    <span class="text-emerald-300">accounted for.</span>
                </h1>
                <p class="mt-7 max-w-xl text-lg leading-8 text-emerald-50/75">
                    One secure place to manage campus attendance, operate scanning booths, and access student QR records.
                </p>

                <div class="mt-11 grid max-w-xl grid-cols-3 gap-3">
                    <div class="rounded-2xl border border-white/10 bg-white/[0.07] p-4 backdrop-blur-sm">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-5 w-5 text-emerald-300" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M4 7.5A2.5 2.5 0 0 1 6.5 5h11A2.5 2.5 0 0 1 20 7.5v9a2.5 2.5 0 0 1-2.5 2.5h-11A2.5 2.5 0 0 1 4 16.5v-9ZM8 5V3m8 2V3M4 10h16"/></svg>
                        <p class="mt-3 text-sm font-semibold">Live attendance</p>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/[0.07] p-4 backdrop-blur-sm">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-5 w-5 text-emerald-300" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M5 5h4v4H5V5Zm10 0h4v4h-4V5ZM5 15h4v4H5v-4Zm10 0h2m2 0v4h-4v-2m-3-5h7"/></svg>
                        <p class="mt-3 text-sm font-semibold">Fast QR scans</p>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/[0.07] p-4 backdrop-blur-sm">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-5 w-5 text-emerald-300" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M12 3 5 6v5c0 4.6 2.9 8 7 10 4.1-2 7-5.4 7-10V6l-7-3Zm-3 9 2 2 4-4"/></svg>
                        <p class="mt-3 text-sm font-semibold">Secure access</p>
                    </div>
                </div>
            </div>

            <p class="text-xs text-emerald-100/50">Our Lady of the Sacred Heart College of Guimba, Inc.</p>
        </section>

        <section class="relative flex min-h-screen items-center justify-center overflow-hidden px-5 py-10 sm:px-8 lg:px-12">
            <div class="absolute left-0 top-0 h-56 w-56 -translate-x-1/2 -translate-y-1/2 rounded-full bg-emerald-100/60 blur-3xl lg:hidden" aria-hidden="true"></div>

            <div class="relative w-full max-w-md">
                <div class="mb-10 flex items-center gap-3 lg:hidden">
                    <span class="grid h-11 w-11 place-items-center rounded-xl bg-emerald-950 text-white shadow-lg shadow-emerald-950/15">
                        <svg viewBox="0 0 32 32" class="h-6 w-6" aria-hidden="true"><path fill="currentColor" d="M4 4h10v10H4V4Zm3 3v4h4V7H7Zm11-3h10v10H18V4Zm3 3v4h4V7h-4ZM4 18h10v10H4V18Zm3 3v4h4v-4H7Zm11-3h4v4h-4v-4Zm6 0h4v7h-3v3h-7v-4h6v-6Zm-6 6h4v4h-4v-4Z"/></svg>
                    </span>
                    <span>
                        <span class="block font-bold leading-tight tracking-tight">OLSHCO</span>
                        <span class="block text-xs text-zinc-500">Attendance System</span>
                    </span>
                </div>

                <div>
                    <p class="text-sm font-semibold text-emerald-700">Welcome back</p>
                    <h2 class="mt-2 text-3xl font-bold tracking-[-0.03em] text-zinc-900 sm:text-4xl">Sign in to your account</h2>
                    <p class="mt-3 text-base leading-7 text-zinc-500">Enter your OLSHCO credentials to continue.</p>
                </div>

                <form method="POST" action="{{ route('login.store') }}" class="mt-8 grid gap-5">
                    @csrf

                    <div class="grid gap-2">
                        <label for="email" class="text-sm font-semibold text-zinc-800">Email address</label>
                        <div class="relative">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-zinc-400" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M4 6.5h16v11H4v-11Zm1 1 7 5.5 7-5.5"/></svg>
                            <input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" placeholder="name@olshco.edu.ph" class="h-13 w-full rounded-xl border bg-white pl-12 pr-4 text-sm font-normal text-zinc-900 shadow-sm outline-none transition placeholder:text-zinc-400 {{ $errors->has('email') ? 'border-rose-400 focus:border-rose-500 focus:ring-4 focus:ring-rose-100' : 'border-zinc-300 hover:border-zinc-400 focus:border-emerald-600 focus:ring-4 focus:ring-emerald-100' }}" required autofocus aria-describedby="email-error">
                        </div>
                        @error('email') <span id="email-error" class="text-xs font-medium text-rose-600">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid gap-2">
                        <label for="password" class="text-sm font-semibold text-zinc-800">Password</label>
                        <div class="relative">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-zinc-400" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M7 10V8a5 5 0 0 1 10 0v2m-11 0h12v10H6V10Z"/></svg>
                            <input id="password" type="password" name="password" autocomplete="current-password" placeholder="Enter your password" class="h-13 w-full rounded-xl border border-zinc-300 bg-white pl-12 pr-18 text-sm font-normal text-zinc-900 shadow-sm outline-none transition placeholder:text-zinc-400 hover:border-zinc-400 focus:border-emerald-600 focus:ring-4 focus:ring-emerald-100" required>
                            <button id="toggle-password" type="button" class="absolute inset-y-1.5 right-1.5 rounded-lg px-3 text-xs font-bold text-emerald-700 transition hover:bg-emerald-50 hover:text-emerald-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600" aria-controls="password" aria-pressed="false">Show</button>
                        </div>
                        @error('password') <span class="text-xs font-medium text-rose-600">{{ $message }}</span> @enderror
                    </div>

                    <label class="flex w-fit cursor-pointer items-center gap-2.5 text-sm font-medium text-zinc-600">
                        <input type="checkbox" name="remember" value="1" class="h-4.5 w-4.5 rounded border-zinc-300 text-emerald-600 focus:ring-emerald-500">
                        Keep me signed in
                    </label>

                    <button class="group mt-1 flex h-13 items-center justify-center gap-2 rounded-xl bg-emerald-700 px-4 text-sm font-bold text-white shadow-lg shadow-emerald-900/15 transition hover:-translate-y-0.5 hover:bg-emerald-800 hover:shadow-xl focus:outline-none focus-visible:ring-4 focus-visible:ring-emerald-200 active:translate-y-0">
                        Sign in
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-4 w-4 transition-transform group-hover:translate-x-0.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7"/></svg>
                    </button>

                </form>

                <p class="mt-10 text-center text-xs leading-5 text-zinc-400">Authorized students and staff only. Your access is protected and monitored.</p>
            </div>
        </section>
    </main>

    <script>
        const passwordInput = document.getElementById('password');
        const passwordToggle = document.getElementById('toggle-password');

        passwordToggle?.addEventListener('click', () => {
            const showPassword = passwordInput.type === 'password';

            passwordInput.type = showPassword ? 'text' : 'password';
            passwordToggle.textContent = showPassword ? 'Hide' : 'Show';
            passwordToggle.setAttribute('aria-pressed', showPassword.toString());
        });
    </script>
</body>
</html>

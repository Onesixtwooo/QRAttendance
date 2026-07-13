<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - SAMSqr</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-zinc-100 font-sans text-zinc-950">
    <main class="grid min-h-screen lg:grid-cols-[1fr_460px]">
        <section class="hidden bg-zinc-950 p-10 text-white lg:grid lg:content-between">
            <div class="flex items-center gap-3">
                <div class="grid h-12 w-12 place-items-center rounded-lg bg-white text-lg font-bold text-zinc-950">QR</div>
                <div>
                    <p class="text-lg font-bold leading-tight">SAMSqr</p>
                    <p class="text-sm text-zinc-400">Attendance Access</p>
                </div>
            </div>

            <div class="max-w-xl">
                <p class="text-sm font-semibold uppercase tracking-wide text-emerald-300">Student Attendance Monitoring System</p>
                <h1 class="mt-4 text-5xl font-bold leading-tight">Secure access for admins and booth operators.</h1>
                <p class="mt-5 text-base leading-7 text-zinc-300">Manage records from the admin console or open the scanner tools with a booth account.</p>
            </div>

            <p class="text-sm text-zinc-500">Admin and booth users only</p>
        </section>

        <section class="grid content-center p-5 sm:p-8">
            <div class="mx-auto w-full max-w-md rounded-lg border border-zinc-200 bg-white shadow-sm">
                <div class="border-b border-zinc-200 p-6">
                    <div class="flex items-center gap-3 lg:hidden">
                        <div class="grid h-10 w-10 place-items-center rounded-lg bg-zinc-950 text-sm font-bold text-white">QR</div>
                        <div>
                            <p class="font-bold leading-tight">SAMSqr</p>
                            <p class="text-sm text-zinc-500">Attendance Access</p>
                        </div>
                    </div>
                    <h1 class="mt-6 text-2xl font-bold lg:mt-0">Sign in</h1>
                    <p class="mt-2 text-sm text-zinc-500">Use your SAMSqr account to continue.</p>
                </div>

                <form method="POST" action="{{ route('login.store') }}" class="grid gap-5 p-6">
                    @csrf

                    <label class="grid gap-1 text-sm font-semibold text-zinc-700">
                        Email
                        <input type="email" name="email" value="{{ old('email') }}" autocomplete="email" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500" required autofocus>
                        @error('email') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                    </label>

                    <label class="grid gap-1 text-sm font-semibold text-zinc-700">
                        Password
                        <input type="password" name="password" autocomplete="current-password" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500" required>
                        @error('password') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                    </label>

                    <label class="flex items-center gap-2 text-sm font-semibold text-zinc-700">
                        <input type="checkbox" name="remember" value="1" class="h-4 w-4 rounded border-zinc-300 text-emerald-600 focus:ring-emerald-500">
                        Remember me
                    </label>

                    <button class="rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">Login</button>
                </form>
            </div>
        </section>
    </main>
</body>
</html>

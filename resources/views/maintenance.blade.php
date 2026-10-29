<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Temporarily unavailable · OLSHCO Attendance</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-emerald-950 font-sans text-white">
    <main class="grid min-h-screen place-items-center px-6 py-12">
        <section class="w-full max-w-xl text-center">
            <div class="mx-auto grid h-16 w-16 place-items-center rounded-2xl bg-white text-lg font-bold text-emerald-950 shadow-2xl">QR</div>
            <p class="mt-8 text-sm font-semibold uppercase tracking-[0.22em] text-emerald-200">OLSHCO Attendance</p>
            <h1 class="mt-4 text-4xl font-bold tracking-tight sm:text-5xl">We’ll be right back.</h1>
            <p class="mx-auto mt-5 max-w-md text-base leading-7 text-emerald-100">The attendance system is temporarily unavailable while we make a few improvements. Please check back shortly.</p>
            <div class="mx-auto mt-10 flex max-w-sm items-center gap-3 rounded-2xl border border-emerald-800 bg-emerald-900/60 px-5 py-4 text-left">
                <span class="h-3 w-3 shrink-0 rounded-full bg-amber-300 shadow-[0_0_0_5px_rgba(253,230,138,0.12)]"></span>
                <p class="text-sm text-emerald-100">Maintenance mode is active. Your data is safe.</p>
            </div>
        </section>
    </main>
</body>
</html>

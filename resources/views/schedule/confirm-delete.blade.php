<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Delete {{ $event->title }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-zinc-100 p-5 text-zinc-900">
    <main class="mx-auto max-w-2xl rounded-xl bg-white p-6 shadow-sm">
        <h1 class="text-2xl font-bold">Archive and delete event</h1>
        <p class="mt-3 text-zinc-700"><strong>{{ $event->title }}</strong> · {{ $event->event_date->format('F j, Y') }}</p>
        <p class="mt-2 text-sm text-zinc-600">{{ $scanCount }} scan record(s) are linked to this event.</p>
        <p class="mt-5 text-sm text-zinc-700">Download or review the attendance report before deleting. When you delete the event, the system saves a final copy that remains available from the event schedule page.</p>

        <div class="mt-6 flex flex-wrap gap-3">
            <a href="{{ route('schedule.report.download', $event) }}" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">Download current report</a>
            <a href="{{ route('records.print', ['event_schedule_id' => $event->id]) }}" target="_blank" class="rounded-lg border border-zinc-300 px-4 py-2 text-sm font-semibold text-zinc-700 hover:bg-zinc-50">View print report</a>
        </div>

        <div class="mt-8 flex flex-wrap items-center gap-3 border-t border-zinc-200 pt-5">
            <form method="POST" action="{{ route('schedule.destroy', $event) }}">
                @csrf
                @method('DELETE')
                <button class="rounded-lg bg-rose-700 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-800">Save final report and delete event</button>
            </form>
            <a href="{{ route('schedule') }}" class="rounded-lg border border-zinc-300 px-4 py-2 text-sm font-semibold text-zinc-700 hover:bg-zinc-50">Cancel</a>
        </div>
    </main>
</body>
</html>

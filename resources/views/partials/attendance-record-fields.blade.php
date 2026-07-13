@php
    $record = $record ?? null;
    $selectedEvent = old('event_schedule_id', $record?->event_schedule_id);
    $selectedBooth = old('booth_id', $record?->booth_id);
    $selectedStudent = old('student_id', $record?->student_id);
    $scannedAt = old('scanned_at', $record?->scanned_at?->format('Y-m-d\TH:i'));
@endphp

<label class="grid gap-1 text-sm font-semibold text-zinc-700">
    Event Schedule
    <select name="event_schedule_id" class="rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500" required>
        <option value="">Select event schedule</option>
        @foreach ($recordFilterOptions['events'] as $event)
            <option value="{{ $event->id }}" @selected((string) $selectedEvent === (string) $event->id)>{{ $event->title }}</option>
        @endforeach
    </select>
    @error('event_schedule_id') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
</label>

<label class="grid gap-1 text-sm font-semibold text-zinc-700">
    Booth
    <select name="booth_id" class="rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500" required>
        <option value="">Select booth</option>
        @foreach ($recordFilterOptions['booths'] as $booth)
            <option value="{{ $booth->id }}" @selected((string) $selectedBooth === (string) $booth->id)>{{ $booth->name }}</option>
        @endforeach
    </select>
    @error('booth_id') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
</label>

<label class="grid gap-1 text-sm font-semibold text-zinc-700">
    Student
    <select name="student_id" class="rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500">
        <option value="">Unassigned student</option>
        @foreach ($recordFilterOptions['students'] as $student)
            <option value="{{ $student->id }}" @selected((string) $selectedStudent === (string) $student->id)>{{ $student->student_id }} - {{ $student->first_name }} {{ $student->last_name }}</option>
        @endforeach
    </select>
    @error('student_id') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
</label>

<label class="grid gap-1 text-sm font-semibold text-zinc-700">
    Scan Time
    <input type="datetime-local" name="scanned_at" value="{{ $scannedAt }}" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-normal outline-none focus:border-emerald-500" required>
    @error('scanned_at') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
</label>

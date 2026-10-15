<?php

namespace App\Http\Controllers;

use App\Models\EventAttendanceArchive;
use App\Models\EventSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class EventScheduleController extends Controller
{
    public function index(Request $request): View
    {
        $events = EventSchedule::query()
            ->orderBy('event_date')
            ->orderBy('start_time')
            ->get()
            ->map(fn (EventSchedule $event): array => $this->formatEvent($event));

        $editEvent = null;

        if ($request->query('edit')) {
            $editEvent = EventSchedule::find($request->query('edit'));
        }

        return view('welcome', [
            'page' => 'schedule',
            'events' => $events,
            'editEvent' => $editEvent,
            'attendanceArchives' => EventAttendanceArchive::query()
                ->select(['id', 'original_event_id', 'event_title', 'event_date', 'created_at'])
                ->latest()
                ->paginate(10, ['*'], 'archives_page'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        EventSchedule::create($this->validated($request));

        return redirect()
            ->route('schedule')
            ->with('status', 'Event schedule saved.');
    }

    public function update(Request $request, EventSchedule $eventSchedule): RedirectResponse
    {
        abort_unless($request->user()->role === 'admin', 403);

        $data = $this->validated($request);
        $override = $request->boolean('override_schedule_time');

        if ($override && $data['status'] !== 'In Progress') {
            throw ValidationException::withMessages([
                'override_schedule_time' => 'Select In Progress to override the schedule time.',
            ]);
        }

        $endsAt = CarbonImmutable::parse($data['event_date'].' '.$data['end_time'], config('app.timezone'));
        if ($data['status'] === 'In Progress' && ! $override && $endsAt->isPast()) {
            throw ValidationException::withMessages([
                'override_schedule_time' => 'This event has ended. Enable the schedule override to reopen it.',
            ]);
        }

        $data['override_schedule_time'] = $override;
        $eventSchedule->update($data);

        return redirect()
            ->route('schedule')
            ->with('status', 'Event schedule updated.');
    }

    public function confirmDelete(Request $request, EventSchedule $eventSchedule): View
    {
        abort_unless($request->user()->role === 'admin', 403);

        return view('schedule.confirm-delete', [
            'event' => $eventSchedule,
            'scanCount' => $eventSchedule->attendanceRecords()->count(),
        ]);
    }

    public function downloadCurrentReport(Request $request, EventSchedule $eventSchedule, AttendanceRecordController $attendanceRecords): Response
    {
        abort_unless($request->user()->role === 'admin', 403);

        $html = $this->renderFinalReport($eventSchedule, $attendanceRecords);
        $filename = 'attendance-event-'.$eventSchedule->id.'-'.$eventSchedule->event_date->format('Y-m-d').'.html';

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroy(Request $request, EventSchedule $eventSchedule, AttendanceRecordController $attendanceRecords): RedirectResponse
    {
        abort_unless($request->user()->role === 'admin', 403);

        $archive = DB::transaction(function () use ($eventSchedule, $attendanceRecords): EventAttendanceArchive {
            $html = $this->renderFinalReport($eventSchedule, $attendanceRecords);

            $archive = EventAttendanceArchive::create([
                'original_event_id' => $eventSchedule->id,
                'event_title' => $eventSchedule->title,
                'event_date' => $eventSchedule->event_date,
                'report_html' => $html,
            ]);

            $eventSchedule->delete();

            return $archive;
        });

        return redirect()
            ->route('schedule')
            ->with('status', 'Event deleted. Its final attendance report was archived.')
            ->with('attendance_archive_id', $archive->id);
    }

    public function downloadArchive(Request $request, EventAttendanceArchive $archive): Response
    {
        abort_unless($request->user()->role === 'admin', 403);

        $filename = 'attendance-event-'.$archive->original_event_id.'-'.$archive->event_date->format('Y-m-d').'.html';

        return response($archive->report_html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function renderFinalReport(EventSchedule $event, AttendanceRecordController $attendanceRecords): string
    {
        $seal = 'data:image/jpeg;base64,'.base64_encode(File::get(public_path('olshco-seal.jpg')));

        return $attendanceRecords->printForEvent($event)
            ->with('sealUrl', $seal)
            ->render();
    }

    public function updateScanPhase(Request $request, EventSchedule $eventSchedule): RedirectResponse
    {
        $data = $request->validate([
            'scan_phase' => ['required', 'in:time_in,time_out'],
        ]);

        $eventSchedule->update($data);

        return redirect()
            ->route('schedule')
            ->with('status', 'Event scan phase updated.');
    }

    public function updateTimeInOpen(Request $request, EventSchedule $eventSchedule): RedirectResponse
    {
        $data = $request->validate([
            'time_in_open' => ['required', 'boolean'],
        ]);

        $eventSchedule->update($data);

        return redirect()
            ->route('schedule')
            ->with('status', $data['time_in_open'] ? 'Time-in scanning opened.' : 'Time-in scanning closed.');
    }

    private function validated(Request $request): array
    {
        foreach (['start_time', 'end_time'] as $field) {
            $value = $request->input($field);

            if (is_string($value) && preg_match('/^\d{2}:\d{2}:\d{2}$/', $value)) {
                $request->merge([$field => substr($value, 0, 5)]);
            }
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'event_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'venue' => ['required', 'string', 'max:255'],
            'status' => ['required', 'in:Scheduled,Upcoming,In Progress,Completed,Cancelled'],
        ]);

        $data['booth'] = 'Not assigned';

        return $data;
    }

    private function formatEvent(EventSchedule $event): array
    {
        $status = $this->currentStatus($event);

        if ($event->status !== $status) {
            $event->forceFill(['status' => $status])->save();
        }

        return [
            'id' => $event->id,
            'title' => $event->title,
            'date' => $event->event_date->format('F j, Y'),
            'event_date' => $event->event_date->format('Y-m-d'),
            'time' => $event->start_time.' - '.$event->end_time,
            'start_time' => $event->start_time,
            'end_time' => $event->end_time,
            'venue' => $event->venue,
            'booth' => $event->booth,
            'status' => $status,
            'scan_phase' => in_array($event->scan_phase, [EventSchedule::PHASE_TIME_IN, EventSchedule::PHASE_TIME_OUT], true)
                ? $event->scan_phase
                : EventSchedule::PHASE_TIME_OUT,
            'scan_phase_label' => $event->scanPhaseLabel(),
            'time_in_open' => $event->time_in_open ?? true,
            'override_schedule_time' => $event->override_schedule_time,
        ];
    }

    private function currentStatus(EventSchedule $event): string
    {
        if ($event->status === 'Cancelled') {
            return $event->status;
        }

        if ($event->status === 'In Progress' && $event->override_schedule_time) {
            return 'In Progress';
        }

        $now = CarbonImmutable::now(config('app.timezone'));
        $eventDate = CarbonImmutable::parse($event->event_date, config('app.timezone'));
        $startsAt = CarbonImmutable::parse($eventDate->format('Y-m-d').' '.$event->start_time, config('app.timezone'));
        $endsAt = CarbonImmutable::parse($eventDate->format('Y-m-d').' '.$event->end_time, config('app.timezone'));

        if ($endsAt->lessThan($now)) {
            return 'Completed';
        }

        if ($startsAt->lessThanOrEqualTo($now) && $endsAt->greaterThanOrEqualTo($now)) {
            return 'In Progress';
        }

        return $event->status;
    }
}

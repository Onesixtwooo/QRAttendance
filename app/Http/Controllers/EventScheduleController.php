<?php

namespace App\Http\Controllers;

use App\Models\EventSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $eventSchedule->update($this->validated($request));

        return redirect()
            ->route('schedule')
            ->with('status', 'Event schedule updated.');
    }

    public function destroy(EventSchedule $eventSchedule): RedirectResponse
    {
        $eventSchedule->delete();

        return redirect()
            ->route('schedule')
            ->with('status', 'Event schedule deleted.');
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
        ];
    }

    private function currentStatus(EventSchedule $event): string
    {
        if ($event->status === 'Cancelled') {
            return $event->status;
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

<?php

namespace App\Http\Controllers;

use App\Models\EventSchedule;
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

    private function validated(Request $request): array
    {
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
            'status' => $event->status,
        ];
    }
}

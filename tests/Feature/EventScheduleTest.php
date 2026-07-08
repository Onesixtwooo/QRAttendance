<?php

namespace Tests\Feature;

use App\Models\EventSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_schedule_can_be_saved_to_the_database(): void
    {
        $response = $this->post('/event-schedule', [
            'title' => 'Orientation Attendance',
            'event_date' => '2026-07-08',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'venue' => 'Auditorium',
            'status' => 'Scheduled',
        ]);

        $response->assertRedirect('/event-schedule');

        $this->assertDatabaseHas('event_schedules', [
            'title' => 'Orientation Attendance',
            'event_date' => '2026-07-08 00:00:00',
            'venue' => 'Auditorium',
            'booth' => 'Not assigned',
            'status' => 'Scheduled',
        ]);
    }

    public function test_event_schedule_can_be_loaded_for_editing(): void
    {
        $event = EventSchedule::create([
            'title' => 'First Day of Class',
            'event_date' => '2026-07-08',
            'start_time' => '16:00',
            'end_time' => '17:00',
            'venue' => 'Chevaliers Gym',
            'booth' => 'Not assigned',
            'status' => 'Scheduled',
        ]);

        $response = $this->get('/event-schedule?edit='.$event->id);

        $response->assertOk();
        $response->assertSee('/event-schedule?edit='.$event->id.'#event-form', false);
        $response->assertSee('Update Event');
        $response->assertSee('Editing Event');
        $response->assertSee('First Day of Class');
        $response->assertSee('Chevaliers Gym');
    }

    public function test_event_schedule_can_be_updated(): void
    {
        $event = EventSchedule::create([
            'title' => 'First Day of Class',
            'event_date' => '2026-07-08',
            'start_time' => '16:00',
            'end_time' => '17:00',
            'venue' => 'Chevaliers Gym',
            'booth' => 'Not assigned',
            'status' => 'Scheduled',
        ]);

        $response = $this->patch('/event-schedule/'.$event->id, [
            'title' => 'First Day of Class - Orientation',
            'event_date' => '2026-07-09',
            'start_time' => '08:00',
            'end_time' => '09:30',
            'venue' => 'Main Auditorium',
            'status' => 'Upcoming',
        ]);

        $response->assertRedirect('/event-schedule');

        $this->assertDatabaseHas('event_schedules', [
            'id' => $event->id,
            'title' => 'First Day of Class - Orientation',
            'event_date' => '2026-07-09 00:00:00',
            'venue' => 'Main Auditorium',
            'status' => 'Upcoming',
        ]);
    }

    public function test_event_schedule_can_be_deleted(): void
    {
        $event = EventSchedule::create([
            'title' => 'First Day of Class - Orientation',
            'event_date' => '2026-07-08',
            'start_time' => '16:00',
            'end_time' => '17:00',
            'venue' => 'Chevaliers Gym',
            'booth' => 'Not assigned',
            'status' => 'Scheduled',
        ]);

        $response = $this->delete('/event-schedule/'.$event->id);

        $response->assertRedirect('/event-schedule');

        $this->assertDatabaseMissing('event_schedules', [
            'id' => $event->id,
        ]);
    }
}

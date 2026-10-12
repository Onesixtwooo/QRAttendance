<?php

namespace Tests\Feature;

use App\Models\EventSchedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_event_schedule_add_form_opens_from_modal_trigger(): void
    {
        $response = $this->get('/event-schedule');

        $response->assertOk();
        $response->assertSee('href="#add-event-modal"', false);
        $response->assertSee('id="add-event-modal"', false);
        $response->assertSee('Add Event');
        $response->assertSee('Save Event');
        $response->assertSee('No event schedules yet. Use Add Record to add the first event.');
    }

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
            'scan_phase' => 'time_in',
            'time_in_open' => true,
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
        $response->assertSee('/event-schedule?edit='.$event->id.'#edit-event-modal', false);
        $response->assertSee('id="edit-event-modal"', false);
        $response->assertSee('class="modal-overlay fixed inset-0 z-50 is-open', false);
        $response->assertSee('Update Event');
        $response->assertSee('Editing Event');
        $response->assertSee('First Day of Class');
        $response->assertSee('Chevaliers Gym');
    }

    public function test_event_statuses_are_adjusted_from_schedule_time(): void
    {
        Carbon::setTestNow('2026-07-09 15:45:00');

        EventSchedule::create([
            'title' => 'Yesterday Orientation',
            'event_date' => '2026-07-08',
            'start_time' => '15:30',
            'end_time' => '17:00',
            'venue' => 'Chevaliers Gym',
            'booth' => 'Not assigned',
            'status' => 'Scheduled',
        ]);

        EventSchedule::create([
            'title' => 'Current Program',
            'event_date' => '2026-07-09',
            'start_time' => '15:30',
            'end_time' => '17:00',
            'venue' => 'Main Auditorium',
            'booth' => 'Not assigned',
            'status' => 'Scheduled',
        ]);

        EventSchedule::create([
            'title' => 'Upcoming Program',
            'event_date' => '2026-07-10',
            'start_time' => '15:30',
            'end_time' => '17:00',
            'venue' => 'Library',
            'booth' => 'Not assigned',
            'status' => 'Scheduled',
        ]);

        EventSchedule::create([
            'title' => 'Cancelled Program',
            'event_date' => '2026-07-08',
            'start_time' => '15:30',
            'end_time' => '17:00',
            'venue' => 'Room 101',
            'booth' => 'Not assigned',
            'status' => 'Cancelled',
        ]);

        $response = $this->get('/event-schedule');

        $response->assertOk();
        $response->assertSee('Completed');
        $response->assertSee('In Progress');
        $response->assertSee('Scheduled');
        $response->assertSee('Cancelled');
        $response->assertSee('Scan phase');
        $response->assertSee('Activate Verification');
        $response->assertSee('Close Time-in');

        $this->assertDatabaseHas('event_schedules', [
            'title' => 'Yesterday Orientation',
            'status' => 'Completed',
        ]);

        $this->assertDatabaseHas('event_schedules', [
            'title' => 'Current Program',
            'status' => 'In Progress',
        ]);

        $this->assertDatabaseHas('event_schedules', [
            'title' => 'Upcoming Program',
            'status' => 'Scheduled',
        ]);

        $this->assertDatabaseHas('event_schedules', [
            'title' => 'Cancelled Program',
            'status' => 'Cancelled',
        ]);

        Carbon::setTestNow();
    }

    public function test_scanning_controls_are_hidden_until_event_is_in_progress(): void
    {
        Carbon::setTestNow('2026-07-09 08:00:00');

        EventSchedule::create([
            'title' => 'Future Orientation',
            'event_date' => '2026-07-10',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'venue' => 'Gym',
            'booth' => 'Not assigned',
            'status' => 'Upcoming',
        ]);

        $response = $this->get('/event-schedule');

        $response->assertOk();
        $response->assertSee('Future Orientation');
        $response->assertDontSee('Scan phase:');
        $response->assertDontSee('Activate Verification');
        $response->assertDontSee('Open Time-in');
        $response->assertDontSee('Close Time-in');

        Carbon::setTestNow();
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

    public function test_event_schedule_update_accepts_database_times_with_seconds(): void
    {
        $event = EventSchedule::create([
            'title' => 'Afternoon Orientation',
            'event_date' => '2026-07-13',
            'start_time' => '13:30',
            'end_time' => '16:30',
            'venue' => 'Gym',
            'booth' => 'Not assigned',
            'status' => 'Scheduled',
        ]);

        $response = $this->patch('/event-schedule/'.$event->id, [
            'title' => 'Afternoon Orientation',
            'event_date' => '2026-07-13',
            'start_time' => '13:30:00',
            'end_time' => '16:30:00',
            'venue' => 'Gym',
            'status' => 'Scheduled',
        ]);

        $response->assertRedirect('/event-schedule');
        $response->assertSessionHasNoErrors();
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

    public function test_event_scan_phase_can_be_updated(): void
    {
        Carbon::setTestNow('2026-07-08 16:30:00');

        $event = EventSchedule::create([
            'title' => 'First Day of Class',
            'event_date' => '2026-07-08',
            'start_time' => '16:00',
            'end_time' => '17:00',
            'venue' => 'Chevaliers Gym',
            'booth' => 'Not assigned',
            'status' => 'In Progress',
            'scan_phase' => 'time_in',
        ]);

        $response = $this->patch('/event-schedule/'.$event->id.'/scan-phase', [
            'scan_phase' => 'verification',
        ]);

        $response->assertRedirect('/event-schedule');
        $this->assertDatabaseHas('event_schedules', [
            'id' => $event->id,
            'scan_phase' => 'verification',
        ]);

        $page = $this->get('/event-schedule');

        $page->assertOk();
        $page->assertSee('Cancel Verification');

        $cancelResponse = $this->patch('/event-schedule/'.$event->id.'/scan-phase', [
            'scan_phase' => 'time_in',
        ]);

        $cancelResponse->assertRedirect('/event-schedule');
        $this->assertDatabaseHas('event_schedules', [
            'id' => $event->id,
            'scan_phase' => 'time_in',
        ]);

        Carbon::setTestNow();
    }

    public function test_event_time_in_scanning_can_be_closed_and_opened(): void
    {
        $event = EventSchedule::create([
            'title' => 'First Day of Class',
            'event_date' => '2026-07-08',
            'start_time' => '16:00',
            'end_time' => '17:00',
            'venue' => 'Chevaliers Gym',
            'booth' => 'Not assigned',
            'status' => 'Scheduled',
            'scan_phase' => 'time_in',
            'time_in_open' => true,
        ]);

        $closeResponse = $this->patch('/event-schedule/'.$event->id.'/time-in', [
            'time_in_open' => false,
        ]);

        $closeResponse->assertRedirect('/event-schedule');
        $this->assertDatabaseHas('event_schedules', [
            'id' => $event->id,
            'time_in_open' => false,
        ]);

        $openResponse = $this->patch('/event-schedule/'.$event->id.'/time-in', [
            'time_in_open' => true,
        ]);

        $openResponse->assertRedirect('/event-schedule');
        $this->assertDatabaseHas('event_schedules', [
            'id' => $event->id,
            'time_in_open' => true,
        ]);
    }
}

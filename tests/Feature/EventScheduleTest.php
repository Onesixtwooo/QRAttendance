<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\Booth;
use App\Models\EventAttendanceArchive;
use App\Models\EventSchedule;
use App\Models\Student;
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
        $response->assertSee('Active scan:');
        $response->assertSee('Activate Time-out');
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
        $response->assertDontSee('Active scan:');
        $response->assertDontSee('Activate Time-out');
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

    public function test_completed_event_can_be_reopened_with_an_explicit_schedule_override(): void
    {
        Carbon::setTestNow('2026-07-11 09:00:00');

        try {
            $event = EventSchedule::create([
                'title' => 'Past Orientation',
                'event_date' => '2026-07-08',
                'start_time' => '08:00',
                'end_time' => '12:00',
                'venue' => 'Gym',
                'booth' => 'Not assigned',
                'status' => 'Completed',
            ]);

            $details = [
                'title' => $event->title,
                'event_date' => '2026-07-08',
                'start_time' => '08:00',
                'end_time' => '12:00',
                'venue' => 'Gym',
                'status' => 'In Progress',
            ];

            $this->patch(route('schedule.update', $event), $details)
                ->assertSessionHasErrors('override_schedule_time');

            $this->patch(route('schedule.update', $event), $details + ['override_schedule_time' => '1'])
                ->assertRedirect(route('schedule'))
                ->assertSessionHasNoErrors();

            $this->get(route('schedule'))
                ->assertSee('Schedule override active');
            $this->get(route('scanner'))
                ->assertSee('Past Orientation');
            $this->assertDatabaseHas('event_schedules', [
                'id' => $event->id,
                'status' => 'In Progress',
                'override_schedule_time' => true,
            ]);

            $this->patch(route('schedule.update', $event), array_replace($details, ['status' => 'Completed']))
                ->assertSessionHasNoErrors();
            $this->get(route('scanner'))
                ->assertDontSee('Past Orientation');
            $this->assertDatabaseHas('event_schedules', [
                'id' => $event->id,
                'status' => 'Completed',
                'override_schedule_time' => false,
            ]);
        } finally {
            Carbon::setTestNow();
        }
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
        $this->assertDatabaseHas('event_attendance_archives', [
            'original_event_id' => $event->id,
            'event_title' => $event->title,
        ]);
    }

    public function test_deleting_an_event_archives_its_final_attendance_report(): void
    {
        $event = EventSchedule::create([
            'title' => 'Final Orientation', 'event_date' => '2026-07-08',
            'start_time' => '08:00', 'end_time' => '12:00',
            'venue' => 'Gym', 'booth' => 'Main Gate', 'status' => 'Completed',
        ]);
        $booth = Booth::create([
            'name' => 'Main Gate', 'status' => 'Offline',
            'admin_name' => 'Gate Admin', 'admin_email' => 'gate@example.com',
        ]);
        $student = Student::create([
            'student_id' => 'STU-ARCHIVE', 'last_name' => 'Santos', 'first_name' => 'Mika',
            'course' => 'BSIT', 'block' => 'A', 'year_level' => '1st Year',
        ]);
        $record = AttendanceRecord::create([
            'event_schedule_id' => $event->id, 'booth_id' => $booth->id,
            'student_id' => $student->id, 'scanned_at' => '2026-07-08 08:15:00',
            'scan_type' => 'time_in',
        ]);

        $this->get(route('schedule.delete.confirm', $event))
            ->assertOk()
            ->assertSee('Download current report')
            ->assertSee('1 scan record(s)');
        $this->get(route('schedule.report.download', $event))
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="attendance-event-'.$event->id.'-2026-07-08.html"')
            ->assertSee('Mika Santos');
        $this->assertDatabaseHas('event_schedules', ['id' => $event->id]);

        $this->delete(route('schedule.destroy', $event))
            ->assertRedirect(route('schedule'))
            ->assertSessionHas('status', 'Event deleted. Its final attendance report was archived.');

        $archive = EventAttendanceArchive::where('original_event_id', $event->id)->firstOrFail();
        $this->assertDatabaseMissing('event_schedules', ['id' => $event->id]);
        $this->assertNull($record->fresh()->event_schedule_id);
        $this->assertStringContainsString('Final Orientation', $archive->report_html);
        $this->assertStringContainsString('Mika Santos', $archive->report_html);
        $this->assertStringContainsString('data:image/jpeg;base64,', $archive->report_html);

        $this->get(route('schedule.archives.download', $archive))
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="attendance-event-'.$event->id.'-2026-07-08.html"')
            ->assertSee('Mika Santos');

        $this->get(route('schedule'))
            ->assertSee('Final attendance archives')
            ->assertSee(route('schedule.archives.download', $archive));
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
            'scan_phase' => 'time_out',
        ]);

        $response->assertRedirect('/event-schedule');
        $this->assertDatabaseHas('event_schedules', [
            'id' => $event->id,
            'scan_phase' => 'time_out',
        ]);

        $page = $this->get('/event-schedule');

        $page->assertOk();
        $page->assertSee('Reopen Time-in');

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

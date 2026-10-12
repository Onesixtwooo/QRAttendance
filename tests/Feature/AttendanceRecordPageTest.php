<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\Booth;
use App\Models\EventSchedule;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceRecordPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_attendance_records_page_shows_saved_records_and_add_form(): void
    {
        [$event, $booth, $student] = $this->recordDependencies();

        AttendanceRecord::create([
            'event_schedule_id' => $event->id,
            'booth_id' => $booth->id,
            'student_id' => $student->id,
            'scanned_at' => '2026-07-08 08:15:00',
            'scan_type' => 'time_in',
        ]);

        AttendanceRecord::create([
            'event_schedule_id' => $event->id,
            'booth_id' => $booth->id,
            'student_id' => $student->id,
            'scanned_at' => '2026-07-08 08:20:00',
            'scan_type' => 'verification',
        ]);

        AttendanceRecord::create([
            'event_schedule_id' => null,
            'booth_id' => $booth->id,
            'student_id' => $student->id,
            'scanned_at' => '2026-07-08 08:25:00',
            'scan_type' => 'time_in',
        ]);

        $response = $this->get('/attendance-records');

        $response->assertOk();
        $response->assertSee('Orientation Attendance');
        $response->assertSee('Main Gate');
        $response->assertSee('of 1 students attended');
        $response->assertSee('2 scan record(s)');
        $response->assertDontSee('No event assigned');
        $response->assertDontSee('<p class="text-xs font-semibold text-zinc-700">STU-31001 - Mika Santos</p>', false);
        $response->assertSee('Student ID');
        $response->assertSee('Time-in');
        $response->assertSee('Time-out');
        $response->assertSee('Course');
        $response->assertSee('Block');
        $response->assertSee('Year Level');
        $response->assertSee('Choose a course, block, or year level, then apply to load attendees.');
        $response->assertSee('100%');
        $response->assertSee('href="#add-attendance-modal"', false);
        $response->assertSee('Add Attendance Record');
        $response->assertSee('Event Schedule');
        $response->assertSee('Save Record');
    }

    public function test_attendance_record_can_be_saved_to_the_database(): void
    {
        [$event, $booth, $student] = $this->recordDependencies();

        $response = $this->post('/attendance-records', [
            'event_schedule_id' => $event->id,
            'booth_id' => $booth->id,
            'student_id' => $student->id,
            'scanned_at' => '2026-07-08T08:15',
        ]);

        $response->assertRedirect('/attendance-records');

        $this->assertDatabaseHas('attendance_records', [
            'event_schedule_id' => $event->id,
            'booth_id' => $booth->id,
            'student_id' => $student->id,
            'scanned_at' => '2026-07-08 08:15:00',
        ]);
    }

    public function test_attendees_are_sorted_by_last_name_then_first_name(): void
    {
        [$event, $booth, $student] = $this->recordDependencies();
        $student->update(['last_name' => 'Zulu', 'first_name' => 'Aaron']);

        $secondStudent = Student::create([
            'student_id' => 'STU-31002',
            'last_name' => 'Adams',
            'first_name' => 'Zoe',
            'course' => 'Information Technology',
            'block' => 'B',
            'year_level' => '3rd Year',
        ]);
        $thirdStudent = Student::create([
            'student_id' => 'STU-31003',
            'last_name' => 'Adams',
            'first_name' => 'Amy',
            'course' => 'Information Technology',
            'block' => 'B',
            'year_level' => '3rd Year',
        ]);

        foreach ([$student, $secondStudent, $thirdStudent] as $index => $attendee) {
            AttendanceRecord::create([
                'event_schedule_id' => $event->id,
                'booth_id' => $booth->id,
                'student_id' => $attendee->id,
                'scanned_at' => '2026-07-08 08:'.str_pad((string) (15 + $index), 2, '0', STR_PAD_LEFT).':00',
                'scan_type' => 'time_in',
            ]);
        }

        $response = $this->get('/attendance-records?event_schedule_id='.$event->id.'&course=Information+Technology');

        $response->assertOk();
        $response->assertSeeInOrder(['Amy Adams', 'Zoe Adams', 'Aaron Zulu']);
    }

    public function test_time_in_after_fifteen_minute_grace_period_is_marked_late(): void
    {
        [$event, $booth, $student] = $this->recordDependencies();

        AttendanceRecord::create([
            'event_schedule_id' => $event->id,
            'booth_id' => $booth->id,
            'student_id' => $student->id,
            'scanned_at' => '2026-07-08 08:16:00',
            'scan_type' => 'time_in',
        ]);

        $response = $this->get('/attendance-records?event_schedule_id='.$event->id.'&course=Information+Technology');

        $response->assertOk();
        $response->assertSee('8:16 AM');
        $response->assertSee('Late');
    }

    public function test_time_in_at_fifteen_minute_cutoff_is_still_on_time(): void
    {
        [$event, $booth, $student] = $this->recordDependencies();

        AttendanceRecord::create([
            'event_schedule_id' => $event->id,
            'booth_id' => $booth->id,
            'student_id' => $student->id,
            'scanned_at' => '2026-07-08 08:15:00',
            'scan_type' => 'time_in',
        ]);

        $response = $this->get('/attendance-records?event_schedule_id='.$event->id.'&course=Information+Technology');

        $response->assertOk();
        $response->assertSee('8:15 AM');
        $response->assertSee('On Time');
    }

    public function test_attendance_record_can_be_loaded_for_editing(): void
    {
        [$event, $booth, $student] = $this->recordDependencies();
        $record = AttendanceRecord::create([
            'event_schedule_id' => $event->id,
            'booth_id' => $booth->id,
            'student_id' => $student->id,
            'scanned_at' => '2026-07-08 08:15:00',
        ]);

        $response = $this->get('/attendance-records?edit='.$record->id);

        $response->assertOk();
        $response->assertSee('Editing Attendance Record');
        $response->assertSee('Update Record');
        $response->assertSee('Orientation Attendance');
    }

    public function test_attendance_record_can_be_updated(): void
    {
        [$event, $booth, $student] = $this->recordDependencies();
        $updatedEvent = EventSchedule::create([
            'title' => 'Department Assembly',
            'event_date' => '2026-07-09',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'venue' => 'Auditorium',
            'booth' => 'Not assigned',
            'status' => 'Scheduled',
        ]);

        $record = AttendanceRecord::create([
            'event_schedule_id' => $event->id,
            'booth_id' => $booth->id,
            'student_id' => $student->id,
            'scanned_at' => '2026-07-08 08:15:00',
        ]);

        $response = $this->patch('/attendance-records/'.$record->id, [
            'event_schedule_id' => $updatedEvent->id,
            'booth_id' => $booth->id,
            'student_id' => $student->id,
            'scanned_at' => '2026-07-09T10:30',
        ]);

        $response->assertRedirect('/attendance-records');

        $this->assertDatabaseHas('attendance_records', [
            'id' => $record->id,
            'event_schedule_id' => $updatedEvent->id,
            'scanned_at' => '2026-07-09 10:30:00',
        ]);
    }

    public function test_attendance_records_can_be_filtered_by_event_and_date(): void
    {
        [$event, $booth, $student] = $this->recordDependencies();
        [$otherEvent, $otherBooth, $otherStudent] = $this->recordDependencies('Criminology Check', 'Library Entrance', 'STU-31002');

        AttendanceRecord::create([
            'event_schedule_id' => $event->id,
            'booth_id' => $booth->id,
            'student_id' => $student->id,
            'scanned_at' => '2026-07-08 08:15:00',
        ]);

        AttendanceRecord::create([
            'event_schedule_id' => $otherEvent->id,
            'booth_id' => $otherBooth->id,
            'student_id' => $otherStudent->id,
            'scanned_at' => '2026-07-09 08:15:00',
        ]);

        $response = $this->get('/attendance-records?date=2026-07-08&event_schedule_id='.$event->id);

        $response->assertOk();
        $response->assertSee('Orientation Attendance');
        $response->assertSee('Main Gate');
        $response->assertSee('of 2 students attended');
        $response->assertSee('50%');
        $response->assertDontSee('Noah Ramos - STU-31002');
    }

    public function test_attendance_record_attendees_can_be_filtered_inside_event_summary(): void
    {
        [$event, $booth, $student] = $this->recordDependencies();
        $otherStudent = Student::create([
            'student_id' => 'STU-41002',
            'last_name' => 'Ramos',
            'first_name' => 'Noah',
            'course' => 'Criminology',
            'block' => 'A',
            'year_level' => '1st Year',
        ]);

        AttendanceRecord::create([
            'event_schedule_id' => $event->id,
            'booth_id' => $booth->id,
            'student_id' => $student->id,
            'scanned_at' => '2026-07-08 08:15:00',
            'scan_type' => 'time_in',
        ]);

        AttendanceRecord::create([
            'event_schedule_id' => $event->id,
            'booth_id' => $booth->id,
            'student_id' => $otherStudent->id,
            'scanned_at' => '2026-07-08 08:45:00',
            'scan_type' => 'time_in',
        ]);

        $response = $this->get('/attendance-records?event_schedule_id='.$event->id.'&course=Information+Technology&block=B&year_level=3rd+Year');

        $response->assertOk();
        $response->assertSee('Print Attendance');
        $response->assertSee('/attendance-records/print', false);
        $response->assertSee('Mika Santos');
        $response->assertSee('8:15 AM');
        $response->assertSee('of 1 students attended');
        $response->assertDontSee('8:45 AM');
    }

    public function test_filtered_attendance_can_be_printed_by_course_year_level_and_block(): void
    {
        [$event, $booth, $student] = $this->recordDependencies();
        $otherStudent = Student::create([
            'student_id' => 'STU-41002',
            'last_name' => 'Ramos',
            'first_name' => 'Noah',
            'course' => 'Criminology',
            'block' => 'A',
            'year_level' => '1st Year',
        ]);

        AttendanceRecord::create([
            'event_schedule_id' => $event->id,
            'booth_id' => $booth->id,
            'student_id' => $student->id,
            'scanned_at' => '2026-07-08 08:15:00',
            'scan_type' => 'time_in',
        ]);

        AttendanceRecord::create([
            'event_schedule_id' => $event->id,
            'booth_id' => $booth->id,
            'student_id' => $otherStudent->id,
            'scanned_at' => '2026-07-08 08:45:00',
            'scan_type' => 'time_in',
        ]);

        $response = $this->get('/attendance-records/print?event_schedule_id='.$event->id.'&course=Information+Technology&block=B&year_level=3rd+Year');

        $response->assertOk();
        $response->assertSee('Attendance Print Sheet');
        $response->assertSee('Information Technology 3rd Year Block B');
        $response->assertSee('Orientation Attendance');
        $response->assertSee('Mika Santos');
        $response->assertSee('8:15 AM');
        $response->assertSee('1 of 1 attended');
        $response->assertDontSee('Booth');
        $response->assertDontSee('Noah Ramos');
        $response->assertDontSee('8:45 AM');
    }

    public function test_all_attendance_prints_separate_course_year_level_block_sections(): void
    {
        [$event, $booth, $student] = $this->recordDependencies();
        $otherStudent = Student::create([
            'student_id' => 'STU-51005',
            'last_name' => 'Garcia',
            'first_name' => 'Linda',
            'course' => 'Criminology',
            'block' => 'A',
            'year_level' => '1st Year',
        ]);

        AttendanceRecord::create([
            'event_schedule_id' => $event->id,
            'booth_id' => $booth->id,
            'student_id' => $student->id,
            'scanned_at' => '2026-07-08 08:15:00',
            'scan_type' => 'time_in',
        ]);

        AttendanceRecord::create([
            'event_schedule_id' => $event->id,
            'booth_id' => $booth->id,
            'student_id' => $otherStudent->id,
            'scanned_at' => '2026-07-08 08:45:00',
            'scan_type' => 'time_in',
        ]);

        $response = $this->get('/attendance-records/print?event_schedule_id='.$event->id);

        $response->assertOk();
        $response->assertSee('All Attendance Groups');
        $response->assertSee('Total Students');
        $response->assertSee('Department');
        $response->assertSee('Attended');
        $response->assertSee('Average');
        $response->assertDontSee('<th>Groups</th>', false);
        $response->assertDontSee('<th>Scan Records</th>', false);
        $response->assertDontSee('<strong>-</strong>', false);
        $response->assertSee('Information Technology 3rd Year Block B');
        $response->assertSee('Criminology 1st Year Block A');
        $response->assertSee('Information Technology');
        $response->assertSee('Criminology');
        $response->assertSee('1 of 1');
        $response->assertSee('Mika Santos');
        $response->assertSee('Linda Garcia');
        $response->assertDontSee('Booth');
    }

    public function test_attendance_record_can_be_deleted(): void
    {
        [$event, $booth, $student] = $this->recordDependencies();
        $record = AttendanceRecord::create([
            'event_schedule_id' => $event->id,
            'booth_id' => $booth->id,
            'student_id' => $student->id,
            'scanned_at' => '2026-07-08 08:15:00',
        ]);

        $response = $this->delete('/attendance-records/'.$record->id);

        $response->assertRedirect('/attendance-records');

        $this->assertDatabaseMissing('attendance_records', [
            'id' => $record->id,
        ]);
    }

    private function recordDependencies(string $eventTitle = 'Orientation Attendance', string $boothName = 'Main Gate', string $studentId = 'STU-31001'): array
    {
        $event = EventSchedule::create([
            'title' => $eventTitle,
            'event_date' => '2026-07-08',
            'start_time' => '08:00',
            'end_time' => '09:00',
            'venue' => 'Auditorium',
            'booth' => 'Not assigned',
            'status' => 'Scheduled',
        ]);

        $booth = Booth::create([
            'name' => $boothName,
            'status' => 'Online',
            'admin_name' => 'Admin Cruz',
            'admin_email' => 'cruz-'.$studentId.'@example.com',
        ]);

        $student = Student::create([
            'student_id' => $studentId,
            'last_name' => $studentId === 'STU-31001' ? 'Santos' : 'Ramos',
            'first_name' => $studentId === 'STU-31001' ? 'Mika' : 'Noah',
            'course' => 'Information Technology',
            'block' => 'B',
            'year_level' => '3rd Year',
        ]);

        return [$event, $booth, $student];
    }
}

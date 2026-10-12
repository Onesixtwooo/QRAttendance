<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\Booth;
use App\Models\EventSchedule;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_displays_metrics_from_the_database(): void
    {
        Carbon::setTestNow('2026-07-14 10:00:00');
        $admin = User::factory()->create(['role' => 'admin']);
        $event = EventSchedule::create([
            'title' => 'Assembly',
            'event_date' => today(),
            'start_time' => '08:00:00',
            'end_time' => '12:00:00',
            'venue' => 'Gym',
            'booth' => 'Main Gate',
            'status' => 'In Progress',
        ]);
        $booth = Booth::create([
            'name' => 'Main Gate',
            'status' => 'Online',
            'last_seen_at' => now()->subMinute(),
            'admin_name' => 'Operator',
            'admin_email' => 'operator@example.com',
        ]);
        Booth::create([
            'name' => 'Review Gate',
            'status' => 'Review',
            'last_seen_at' => now(),
            'admin_name' => 'Reviewer',
            'admin_email' => 'reviewer@example.com',
        ]);
        $students = collect(range(1, 4))->map(fn (int $number) => Student::create([
            'student_id' => 'STU-'.$number,
            'last_name' => 'Student'.$number,
            'first_name' => 'Test',
            'course' => 'BSIT',
            'block' => 'A',
            'year_level' => '1st Year',
        ]));

        AttendanceRecord::create(['event_schedule_id' => $event->id, 'booth_id' => $booth->id, 'student_id' => $students[0]->id, 'scanned_at' => today()->setTime(7, 45), 'scan_type' => 'time_in']);
        AttendanceRecord::create(['event_schedule_id' => $event->id, 'booth_id' => $booth->id, 'student_id' => $students[1]->id, 'scanned_at' => today()->setTime(8, 15), 'scan_type' => 'time_in']);
        AttendanceRecord::create(['event_schedule_id' => $event->id, 'booth_id' => $booth->id, 'student_id' => $students[1]->id, 'scanned_at' => today()->setTime(9, 0), 'scan_type' => 'verification']);

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertOk();
        $response->assertSeeInOrder(['Total Students', '4']);
        $response->assertSeeInOrder(['Present Today', '2', '50.0% attendance']);
        $response->assertSeeInOrder(['Late Entries', '1']);
        $response->assertSeeInOrder(['Active Booths', '1', '1 need review']);
        $response->assertSee('1 booth connected');
        $response->assertSeeInOrder(['7 AM', '1', '8 AM', '1', '9 AM', '0']);
    }
}

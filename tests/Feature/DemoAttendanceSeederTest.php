<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\EventSchedule;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\DemoAttendanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoAttendanceSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_attendance_can_be_seeded_twice_and_viewed(): void
    {
        $this->seed(DemoAttendanceSeeder::class);
        $this->seed(DemoAttendanceSeeder::class);

        $event = EventSchedule::where('title', 'Demo Attendance Event')->firstOrFail();

        $this->assertSame(36, Student::where('student_id', 'like', 'DEMO-%')->count());
        $this->assertSame(54, AttendanceRecord::where('event_schedule_id', $event->id)->count());
        $this->assertSame(30, AttendanceRecord::where('event_schedule_id', $event->id)->where('scan_type', 'time_in')->count());

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('records', ['event_schedule_id' => $event->id, 'course' => 'BSIT']))
            ->assertOk()
            ->assertSee('Demo Attendance Event')
            ->assertSee('DEMO-0001');
    }
}

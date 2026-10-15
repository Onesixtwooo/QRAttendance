<?php

namespace Database\Seeders;

use App\Models\AttendanceRecord;
use App\Models\Booth;
use App\Models\EventSchedule;
use App\Models\Student;
use Carbon\CarbonImmutable;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoAttendanceSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create();
        $faker->seed(2026);
        $eventDate = CarbonImmutable::now(config('app.timezone'))->subDay()->startOfDay();

        DB::transaction(function () use ($faker, $eventDate): void {
            $event = EventSchedule::firstOrCreate(
                ['title' => 'Demo Attendance Event', 'event_date' => $eventDate->toDateString()],
                [
                    'start_time' => '08:00:00',
                    'end_time' => '16:00:00',
                    'venue' => 'Demo Hall',
                    'booth' => 'Demo Entrance',
                    'status' => 'Completed',
                    'scan_phase' => EventSchedule::PHASE_TIME_OUT,
                    'time_in_open' => false,
                ]
            );

            $booth = Booth::firstOrCreate(
                ['name' => 'Demo Entrance'],
                ['status' => 'Offline', 'admin_name' => 'Demo Operator', 'admin_email' => null]
            );

            $courses = ['BSIT', 'BSCS', 'BSA'];
            $blocks = ['A', 'B', 'C'];
            $years = ['1st Year', '2nd Year', '3rd Year'];

            for ($number = 1; $number <= 36; $number++) {
                $student = Student::firstOrCreate(
                    ['student_id' => sprintf('DEMO-%04d', $number)],
                    [
                        'first_name' => $faker->firstName(),
                        'last_name' => $faker->lastName(),
                        'course' => $courses[($number - 1) % count($courses)],
                        'block' => $blocks[intdiv($number - 1, 3) % count($blocks)],
                        'year_level' => $years[intdiv($number - 1, 9) % count($years)],
                    ]
                );

                // Leave some students absent and some with a pending time-out.
                if ($number % 6 === 0) {
                    continue;
                }

                $timeIn = $eventDate->setTime(8, 0)->addMinutes($faker->numberBetween(-10, 55));
                AttendanceRecord::firstOrCreate(
                    ['event_schedule_id' => $event->id, 'student_id' => $student->id, 'scan_type' => AttendanceRecord::TYPE_TIME_IN],
                    ['booth_id' => $booth->id, 'scanned_at' => $timeIn]
                );

                if ($number % 4 === 0) {
                    continue;
                }

                AttendanceRecord::firstOrCreate(
                    ['event_schedule_id' => $event->id, 'student_id' => $student->id, 'scan_type' => AttendanceRecord::TYPE_TIME_OUT],
                    ['booth_id' => $booth->id, 'scanned_at' => $eventDate->setTime(16, 0)->addMinutes($faker->numberBetween(-20, 30))]
                );
            }
        });
    }
}

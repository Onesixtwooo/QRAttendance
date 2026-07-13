<?php

namespace Database\Seeders;

use App\Models\AttendanceRecord;
use App\Models\Booth;
use App\Models\User;
use Illuminate\Database\Seeder;

class BoothSeeder extends Seeder
{
    public function run(): void
    {
        $booths = [
            ['name' => 'Main Gate', 'status' => 'Online', 'scan_count' => 682, 'admin_name' => 'Admin Cruz', 'admin_email' => 'cruz@example.com'],
            ['name' => 'Library Entrance', 'status' => 'Online', 'scan_count' => 214, 'admin_name' => 'Admin Ford', 'admin_email' => 'ford@example.com'],
            ['name' => 'Science Hall', 'status' => 'Review', 'scan_count' => 188, 'admin_name' => 'Admin Lim', 'admin_email' => 'lim@example.com'],
            ['name' => 'Gym Lobby', 'status' => 'Offline', 'scan_count' => 0, 'admin_name' => 'Unassigned', 'admin_email' => 'gym@example.com'],
        ];

        foreach ($booths as $booth) {
            $user = User::updateOrCreate(
                ['email' => $booth['admin_email']],
                [
                    'name' => $booth['admin_name'],
                    'password' => 'password',
                    'role' => 'booth',
                ]
            );

            $savedBooth = Booth::updateOrCreate(
                ['name' => $booth['name']],
                [
                    'status' => $booth['status'],
                    'user_id' => $user->id,
                    'admin_name' => $user->name,
                    'admin_email' => $user->email,
                ]
            );

            AttendanceRecord::query()
                ->where('booth_id', $savedBooth->id)
                ->whereDate('scanned_at', today())
                ->delete();

            for ($scan = 0; $scan < $booth['scan_count']; $scan++) {
                AttendanceRecord::create([
                    'booth_id' => $savedBooth->id,
                    'student_id' => null,
                    'scanned_at' => now()->startOfDay()->addMinutes($scan),
                ]);
            }
        }
    }
}

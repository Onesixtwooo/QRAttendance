<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $adminName = env('SEED_ADMIN_NAME');
        $adminEmail = env('SEED_ADMIN_EMAIL');
        $adminPassword = env('SEED_ADMIN_PASSWORD');

        if (filled($adminName) && filled($adminEmail) && filled($adminPassword)) {
            User::firstOrCreate(
                ['email' => $adminEmail],
                [
                    'name' => $adminName,
                    'password' => $adminPassword,
                    'role' => 'admin',
                    'is_active' => true,
                ]
            );
        }

        $this->call(DemoAttendanceSeeder::class);
    }
}

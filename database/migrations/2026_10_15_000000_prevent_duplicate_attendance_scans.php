<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicate = DB::table('attendance_records')
            ->select('event_schedule_id', 'student_id', 'scan_type')
            ->whereNotNull('event_schedule_id')
            ->whereNotNull('student_id')
            ->groupBy('event_schedule_id', 'student_id', 'scan_type')
            ->havingRaw('COUNT(*) > 1')
            ->first();

        if ($duplicate) {
            throw new RuntimeException(
                'Duplicate attendance scans exist. Resolve repeated event/student/scan-type records before running this migration.'
            );
        }

        Schema::table('attendance_records', function (Blueprint $table): void {
            $table->unique(
                ['event_schedule_id', 'student_id', 'scan_type'],
                'attendance_event_student_scan_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table): void {
            $table->dropUnique('attendance_event_student_scan_unique');
        });
    }
};

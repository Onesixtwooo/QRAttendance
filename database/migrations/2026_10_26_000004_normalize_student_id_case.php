<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('students')
            ->selectRaw('LOWER(student_id) AS normalized_id')
            ->whereNotNull('student_id')
            ->groupByRaw('LOWER(student_id)')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('normalized_id');

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException('Cannot normalize duplicate Student IDs: '.$duplicates->implode(', '));
        }

        DB::table('students')
            ->whereNotNull('student_id')
            ->orderBy('id')
            ->eachById(function ($student): void {
                DB::table('students')->where('id', $student->id)->update([
                    'student_id' => strtoupper($student->student_id),
                ]);
            });
    }

    public function down(): void
    {
        // Student IDs remain in canonical uppercase form.
    }
};

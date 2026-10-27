<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('students')->where('student_id', '')->update(['student_id' => null]);
    }

    public function down(): void
    {
        // NULL student IDs are intentionally not converted back to empty strings.
    }
};

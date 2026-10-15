<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_schedules', function (Blueprint $table): void {
            $table->boolean('override_schedule_time')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('event_schedules', function (Blueprint $table): void {
            $table->dropColumn('override_schedule_time');
        });
    }
};

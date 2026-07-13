<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booths', function (Blueprint $table) {
            $table->dropColumn('scans_today');
        });
    }

    public function down(): void
    {
        Schema::table('booths', function (Blueprint $table) {
            $table->unsignedInteger('scans_today')->default(0)->after('status');
        });
    }
};

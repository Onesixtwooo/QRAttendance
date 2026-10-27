<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->uuid('qr_token')->nullable()->unique();
        });

        DB::table('students')->whereNull('qr_token')->orderBy('id')->eachById(function ($student): void {
            DB::table('students')->where('id', $student->id)->update(['qr_token' => (string) Str::uuid()]);
        });

        Schema::table('students', function (Blueprint $table): void {
            $table->uuid('qr_token')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->dropUnique(['qr_token']);
            $table->dropColumn('qr_token');
        });
    }
};

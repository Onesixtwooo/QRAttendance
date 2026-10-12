<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('students', 'user_id')) {
            Schema::table('students', function (Blueprint $table): void {
                $table->foreignId('user_id')->nullable()->unique()->after('id')->constrained('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('students', 'user_id')) {
            Schema::table('students', function (Blueprint $table): void {
                $table->dropForeign(['user_id']);
            });
            Schema::table('students', function (Blueprint $table): void {
                $table->dropUnique('students_user_id_unique');
            });
            Schema::table('students', function (Blueprint $table): void {
                $table->dropColumn('user_id');
            });
        }
    }
};

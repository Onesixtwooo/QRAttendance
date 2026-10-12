<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('students', 'user_id')) {
            Schema::table('students', function (Blueprint $table): void {
                $table->dropUnique('students_user_id_unique');
                $table->dropConstrainedForeignId('user_id');
            });
        }

        if (Schema::hasColumn('users', 'portal_user_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropUnique(['portal_user_id']);
                $table->dropColumn('portal_user_id');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'portal_user_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('portal_user_id')->nullable()->unique()->after('id');
            });
        }

        if (! Schema::hasColumn('students', 'user_id')) {
            Schema::table('students', function (Blueprint $table): void {
                $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            });
        }
    }
};

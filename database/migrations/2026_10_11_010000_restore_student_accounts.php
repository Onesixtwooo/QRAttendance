<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Student accounts are owned by the earlier add_user_id migration.
        // This migration remains as a historical marker and must not recreate
        // or remove that column during rollback.
    }

    public function down(): void
    {
        // Intentionally empty; see up().
    }
};

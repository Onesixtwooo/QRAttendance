<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->orderBy('id')->each(function ($user) {
            if ($user->portal_user_id === (string) $user->id) {
                DB::table('users')->where('id', $user->id)->update(['portal_user_id' => null]);
            }
        });
    }

    public function down(): void
    {
        // Independent SAMS IDs must not be copied into the portal identity field.
    }
};

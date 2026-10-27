<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_delivery_attempts', function (Blueprint $table): void {
            $table->id();
            $table->string('kind');
            $table->string('recipient');
            $table->string('status')->index();
            $table->text('error')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->index(['status', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_delivery_attempts');
    }
};

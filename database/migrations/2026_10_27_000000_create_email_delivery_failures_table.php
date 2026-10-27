<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_delivery_failures', function (Blueprint $table): void {
            $table->id();
            $table->string('kind');
            $table->string('recipient');
            $table->string('related_type')->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->text('error');
            $table->string('status')->default('pending')->index();
            $table->unsignedInteger('attempts')->default(1);
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['kind', 'related_type', 'related_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_delivery_failures');
    }
};

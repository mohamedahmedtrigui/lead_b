<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('dispatcher_id')->index()->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('attempt_number');
            $table->string('outcome', 30)->nullable()->index();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['dispatcher_id', 'started_at']);
            $table->index(['lead_id', 'ended_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_attempts');
    }
};

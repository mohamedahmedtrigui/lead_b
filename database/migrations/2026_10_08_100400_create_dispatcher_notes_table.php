<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispatcher_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->foreignId('call_attempt_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20)->default('NOTE');
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispatcher_notes');
    }
};

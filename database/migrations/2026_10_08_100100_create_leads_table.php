<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();

            // --- Source data (CSV import). Never touched by the qualification flow.
            $table->string('dedupe_key')->unique();
            $table->dateTime('source_created_at')->nullable();
            $table->string('name');
            $table->string('email')->nullable()->index();
            $table->string('source', 50)->nullable();
            $table->string('form')->nullable();
            $table->string('channel', 50)->nullable();
            $table->string('stage', 50)->nullable();
            $table->string('source_owner')->nullable();
            $table->string('labels')->nullable();
            $table->string('phone', 30)->nullable()->index();
            $table->string('secondary_phone', 30)->nullable();
            $table->string('whatsapp_number', 30)->nullable();
            $table->foreignId('lead_import_id')->nullable()->constrained()->nullOnDelete();

            // --- Pipeline state (owned by the CRM).
            $table->string('status', 20)->default('PENDING')->index();
            $table->foreignId('assigned_to')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->unsignedTinyInteger('nrp_attempts')->default(0);
            $table->timestamp('last_nrp_at')->nullable();
            $table->timestamp('last_contacted_at')->nullable()->index();
            $table->timestamp('callback_at')->nullable()->index();
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();

            $table->index(['assigned_to', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};

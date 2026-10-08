<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_qualifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('dispatcher_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('DRAFT')->index();
            $table->string('current_step', 40)->nullable();

            // Level 1 - Introduction
            $table->string('call_availability', 20)->nullable();
            // Level 2 - Beneficiary
            $table->string('beneficiary', 20)->nullable();
            $table->string('beneficiary_details')->nullable();
            // Level 3 - Transportation need
            $table->string('transport_need', 20)->nullable()->index();
            $table->string('transport_need_details')->nullable();
            // Level 4 - Route
            $table->string('departure')->nullable();
            $table->string('destination')->nullable();
            $table->string('trip_type', 20)->nullable();
            $table->time('departure_time')->nullable();
            $table->time('return_time')->nullable();
            // Level 5 - Schedule
            $table->json('days_of_week')->nullable();
            $table->string('frequency', 30)->nullable();
            $table->unsignedSmallInteger('trips_per_week')->nullable();
            $table->boolean('is_recurring')->nullable();
            // Level 6 - Passengers
            $table->unsignedSmallInteger('passengers_count')->nullable();
            $table->unsignedInteger('total_employees')->nullable();
            $table->unsignedSmallInteger('estimated_passengers_per_trip')->nullable();
            // Level 7 - Shared transportation
            $table->string('shared_transport', 10)->nullable();
            $table->string('shared_direction', 10)->nullable();
            // Level 8 - Previous MiralDrive experience
            $table->string('used_miraldrive', 10)->nullable();
            $table->unsignedTinyInteger('experience_rating')->nullable();
            $table->text('experience_feedback')->nullable();
            $table->text('improvement_request')->nullable();
            // Level 9 - Current transportation solution
            $table->string('current_provider', 30)->nullable();
            $table->string('current_provider_details')->nullable();
            $table->text('customer_preference')->nullable();
            $table->text('pain_point')->nullable();
            // Level 10 - B2B
            $table->boolean('is_b2b')->default(false)->index();
            $table->string('company_name')->nullable();
            $table->unsignedInteger('company_size')->nullable();
            $table->unsignedInteger('employees_concerned')->nullable();
            $table->unsignedSmallInteger('trips_per_day')->nullable();
            $table->string('decision_maker_name')->nullable();
            $table->string('decision_role', 20)->nullable();
            // Level 11 - Buying priorities
            $table->string('main_priority', 20)->nullable();
            // Level 12 - Qualification
            $table->boolean('wants_quotation')->nullable();
            $table->boolean('wants_callback')->nullable();
            $table->unsignedTinyInteger('interest_score')->default(0);
            $table->string('interest_level', 20)->nullable()->index();
            $table->json('score_breakdown')->nullable();
            $table->unsignedTinyInteger('priority_stars')->nullable();
            // Closing
            $table->text('summary_note')->nullable();
            $table->string('next_action', 30)->nullable()->index();
            $table->timestamp('callback_at')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_qualifications');
    }
};

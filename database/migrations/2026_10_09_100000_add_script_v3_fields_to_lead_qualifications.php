<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Call script v3: exact arrival time, extra routes / schedules (one per
 * employee or per day group) and the client's experience with other apps.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_qualifications', function (Blueprint $table) {
            $table->time('arrival_time')->nullable()->after('departure_time');
            // [{label, departure, destination, days[], arrival_time, return_time, note}] — free-form.
            $table->json('extra_routes')->nullable()->after('return_time');
            $table->boolean('other_apps_used')->nullable()->after('improvement_request');
            $table->json('other_apps')->nullable()->after('other_apps_used');
            $table->json('other_apps_issues')->nullable()->after('other_apps');
            $table->text('other_apps_feedback')->nullable()->after('other_apps_issues');
        });
    }

    public function down(): void
    {
        Schema::table('lead_qualifications', function (Blueprint $table) {
            $table->dropColumn(['arrival_time', 'extra_routes', 'other_apps_used', 'other_apps', 'other_apps_issues', 'other_apps_feedback']);
        });
    }
};

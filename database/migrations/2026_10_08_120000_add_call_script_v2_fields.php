<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Call script v2 (dialect script): replies per answer, recap validation and
 * the B2B "same schedule" question.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('script_steps', function (Blueprint $table) {
            // { field: { VALUE: "reply the dispatcher says when the client answers VALUE" } }
            $table->json('responses')->nullable()->after('options');
        });

        Schema::table('lead_qualifications', function (Blueprint $table) {
            $table->boolean('b2b_same_schedule')->nullable()->after('trips_per_day');
            $table->boolean('recap_confirmed')->nullable()->after('main_priority');
        });
    }

    public function down(): void
    {
        Schema::table('script_steps', function (Blueprint $table) {
            $table->dropColumn('responses');
        });

        Schema::table('lead_qualifications', function (Blueprint $table) {
            $table->dropColumn(['b2b_same_schedule', 'recap_confirmed']);
        });
    }
};

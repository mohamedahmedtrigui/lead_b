<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Closing step: several next actions can be chosen (e.g. interested + quote +
 * transfer to sales). `next_action` stays as the main one (lead status, filters).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_qualifications', function (Blueprint $table) {
            $table->json('next_actions')->nullable()->after('next_action');
        });

        // Existing qualifications: their single action becomes the list.
        DB::table('lead_qualifications')->whereNotNull('next_action')->orderBy('id')->each(function ($row) {
            DB::table('lead_qualifications')->where('id', $row->id)->update(['next_actions' => json_encode([$row->next_action])]);
        });
    }

    public function down(): void
    {
        Schema::table('lead_qualifications', function (Blueprint $table) {
            $table->dropColumn('next_actions');
        });
    }
};

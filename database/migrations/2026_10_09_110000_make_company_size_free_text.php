<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B2B step: company size is answered in free text ("environ 50", "20 à 30").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_qualifications', function (Blueprint $table) {
            $table->string('company_size', 100)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('lead_qualifications', function (Blueprint $table) {
            $table->unsignedInteger('company_size')->nullable()->change();
        });
    }
};

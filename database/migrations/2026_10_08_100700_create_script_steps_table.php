<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Admin-editable call script. Step keys and fields are defined in code
        // (App\Domain\Scripts\DefaultScript); only the wording is stored here.
        Schema::create('script_steps', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40)->unique();
            $table->unsignedSmallInteger('position');
            $table->string('title');
            $table->string('objective')->nullable();
            $table->text('script')->nullable();
            $table->text('question')->nullable();
            $table->json('prompts')->nullable();
            $table->json('options')->nullable();
            $table->text('tips')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('script_steps');
    }
};

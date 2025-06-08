<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tryouts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('question_bank_id')->constrained('question_banks')->onDelete('cascade');
            $table->foreignUuid('program_id')->constrained('programs')->onDelete('cascade');
            $table->text('title');
            $table->integer('minute_limit');
            $table->string('hash')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tryouts');
    }
};

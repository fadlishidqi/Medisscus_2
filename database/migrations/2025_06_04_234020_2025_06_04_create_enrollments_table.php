<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('program_id');
            $table->boolean('is_active')->default(false);
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('program_id')->references('id')->on('programs')->onDelete('cascade');

            // Unique constraint - user hanya bisa enroll sekali per program
            $table->unique(['user_id', 'program_id']);

            // Indexes
            $table->index('is_active');
            $table->index('paid_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};

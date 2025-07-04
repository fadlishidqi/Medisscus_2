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
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('username')->unique();
            $table->string('email')->unique();
            $table->string('university');
            $table->string('phone');
            $table->enum('role', ['user', 'admin'])->default('user');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('profile_image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('current_jwt_token')->nullable();
            $table->string('last_login_ip')->nullable();
            $table->text('last_login_user_agent')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();

            // Indexes untuk performance
            $table->index('email');
            $table->index('username');
            $table->index('university');
            $table->index('role');
            $table->index('is_active');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
    }
};

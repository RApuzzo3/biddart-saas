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
        Schema::create('phone_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('shared_credential_id')->constrained()->onDelete('cascade');
            $table->string('phone_number');
            $table->string('verification_code');
            $table->string('alias_name')->nullable(); // Auto-generated or user-provided alias
            $table->boolean('verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('expires_at');
            $table->string('session_id')->nullable(); // To track active sessions
            $table->timestamps();
            
            $table->index(['phone_number', 'verified']);
            $table->index(['shared_credential_id', 'phone_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('phone_verifications');
    }
};

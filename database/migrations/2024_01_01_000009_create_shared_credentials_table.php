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
        Schema::create('shared_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('event_id')->constrained()->onDelete('cascade');
            $table->string('credential_name'); // e.g., "Event Staff", "Booth Managers"
            $table->string('username');
            $table->string('password');
            $table->boolean('active')->default(true);
            $table->json('permissions')->nullable(); // What this shared credential can do
            $table->timestamps();
            
            $table->unique(['tenant_id', 'event_id', 'username']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shared_credentials');
    }
};

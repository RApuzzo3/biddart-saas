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
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->datetime('start_date');
            $table->datetime('end_date');
            $table->string('location')->nullable();
            $table->string('status')->default('draft'); // draft, active, completed, cancelled
            $table->json('settings')->nullable(); // Event-specific settings
            $table->decimal('transaction_fee_percentage', 5, 2)->default(2.50); // Your profit percentage
            $table->decimal('fixed_transaction_fee', 8, 2)->default(0.30); // Fixed fee per transaction
            $table->timestamps();
            
            $table->unique(['tenant_id', 'slug']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};

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
        Schema::create('checkout_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('event_id')->constrained()->onDelete('cascade');
            $table->foreignId('bidder_id')->constrained()->onDelete('cascade');
            $table->string('session_id')->unique();
            $table->decimal('subtotal', 10, 2);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('platform_fee', 10, 2); // Your profit
            $table->decimal('payment_processing_fee', 10, 2); // Square fees
            $table->decimal('total_amount', 10, 2);
            $table->string('status')->default('pending'); // pending, processing, completed, failed, refunded
            $table->string('payment_method')->nullable(); // card, cash, check
            $table->string('square_payment_id')->nullable();
            $table->string('square_receipt_url')->nullable();
            $table->json('payment_details')->nullable(); // Square payment response
            $table->json('items')->nullable(); // Snapshot of items at checkout
            $table->foreignId('processed_by')->constrained('tenant_users'); // Staff member who processed checkout
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            $table->index(['tenant_id', 'event_id']);
            $table->index(['bidder_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('checkout_sessions');
    }
};

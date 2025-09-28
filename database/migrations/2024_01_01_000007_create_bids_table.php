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
        Schema::create('bids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('event_id')->constrained()->onDelete('cascade');
            $table->foreignId('bidder_id')->constrained()->onDelete('cascade');
            $table->foreignId('bid_item_id')->constrained()->onDelete('cascade');
            $table->decimal('amount', 10, 2);
            $table->string('type')->default('bid'); // bid, buy_now, raffle_entry, donation
            $table->boolean('is_winning')->default(false);
            $table->boolean('is_paid')->default(false);
            $table->foreignId('created_by')->constrained('tenant_users'); // Staff member who recorded the bid
            $table->json('metadata')->nullable(); // Additional data like payment method preference
            $table->timestamps();
            
            $table->index(['tenant_id', 'event_id']);
            $table->index(['bidder_id', 'bid_item_id']);
            $table->index(['bid_item_id', 'amount']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bids');
    }
};

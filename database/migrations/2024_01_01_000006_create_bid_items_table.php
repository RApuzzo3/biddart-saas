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
        Schema::create('bid_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('event_id')->constrained()->onDelete('cascade');
            $table->foreignId('category_id')->constrained('bid_item_categories')->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('starting_price', 10, 2)->default(0);
            $table->decimal('current_bid', 10, 2)->default(0);
            $table->decimal('buy_now_price', 10, 2)->nullable();
            $table->integer('bid_increment')->default(5); // Minimum bid increment
            $table->string('type')->default('auction'); // auction, raffle, buy_now, donation
            $table->json('images')->nullable(); // Array of image URLs
            $table->boolean('featured')->default(false);
            $table->boolean('active')->default(true);
            $table->datetime('bidding_starts_at')->nullable();
            $table->datetime('bidding_ends_at')->nullable();
            $table->json('metadata')->nullable(); // Additional custom fields
            $table->timestamps();
            
            $table->index(['tenant_id', 'event_id']);
            $table->index(['category_id', 'active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bid_items');
    }
};

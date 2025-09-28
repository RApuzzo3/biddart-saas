<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bid extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'event_id',
        'bidder_id',
        'bid_item_id',
        'amount',
        'type',
        'is_winning',
        'is_paid',
        'created_by',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_winning' => 'boolean',
        'is_paid' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * Get the tenant that owns the bid.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the event that owns the bid.
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Get the bidder that owns the bid.
     */
    public function bidder(): BelongsTo
    {
        return $this->belongsTo(Bidder::class);
    }

    /**
     * Get the bid item that owns the bid.
     */
    public function bidItem(): BelongsTo
    {
        return $this->belongsTo(BidItem::class);
    }

    /**
     * Get the user who created the bid.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'created_by');
    }

    /**
     * Mark this bid as winning and others as losing.
     */
    public function markAsWinning(): void
    {
        // Mark all other bids for this item as not winning
        $this->bidItem->bids()->where('id', '!=', $this->id)->update(['is_winning' => false]);
        
        // Mark this bid as winning
        $this->update(['is_winning' => true]);
        
        // Update the bid item's current bid
        $this->bidItem->updateCurrentBid();
    }

    /**
     * Check if this is an auction bid.
     */
    public function isAuctionBid(): bool
    {
        return $this->type === 'bid';
    }

    /**
     * Check if this is a buy now purchase.
     */
    public function isBuyNow(): bool
    {
        return $this->type === 'buy_now';
    }

    /**
     * Check if this is a raffle entry.
     */
    public function isRaffleEntry(): bool
    {
        return $this->type === 'raffle_entry';
    }

    /**
     * Check if this is a donation.
     */
    public function isDonation(): bool
    {
        return $this->type === 'donation';
    }

    /**
     * Get formatted amount.
     */
    public function getFormattedAmountAttribute(): string
    {
        return '$' . number_format($this->amount, 2);
    }
}

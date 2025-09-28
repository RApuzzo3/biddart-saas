<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BidItem extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'event_id',
        'category_id',
        'name',
        'description',
        'starting_price',
        'current_bid',
        'buy_now_price',
        'bid_increment',
        'type',
        'images',
        'featured',
        'active',
        'bidding_starts_at',
        'bidding_ends_at',
        'metadata',
    ];

    protected $casts = [
        'starting_price' => 'decimal:2',
        'current_bid' => 'decimal:2',
        'buy_now_price' => 'decimal:2',
        'images' => 'array',
        'featured' => 'boolean',
        'active' => 'boolean',
        'bidding_starts_at' => 'datetime',
        'bidding_ends_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Get the tenant that owns the bid item.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the event that owns the bid item.
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Get the category that owns the bid item.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(BidItemCategory::class, 'category_id');
    }

    /**
     * Get the bids for the bid item.
     */
    public function bids(): HasMany
    {
        return $this->hasMany(Bid::class);
    }

    /**
     * Get the highest bid for this item.
     */
    public function highestBid()
    {
        return $this->bids()->orderBy('amount', 'desc')->first();
    }

    /**
     * Get the winning bid for this item.
     */
    public function winningBid()
    {
        return $this->bids()->where('is_winning', true)->first();
    }

    /**
     * Check if bidding is currently open.
     */
    public function isBiddingOpen(): bool
    {
        if (!$this->active) {
            return false;
        }

        $now = now();
        
        if ($this->bidding_starts_at && $now->lt($this->bidding_starts_at)) {
            return false;
        }

        if ($this->bidding_ends_at && $now->gt($this->bidding_ends_at)) {
            return false;
        }

        return true;
    }

    /**
     * Check if item can be purchased with buy now.
     */
    public function canBuyNow(): bool
    {
        return $this->buy_now_price > 0 && $this->isBiddingOpen();
    }

    /**
     * Get the next minimum bid amount.
     */
    public function getNextMinimumBid(): float
    {
        $currentBid = $this->current_bid > 0 ? $this->current_bid : $this->starting_price;
        return $currentBid + $this->bid_increment;
    }

    /**
     * Update current bid amount.
     */
    public function updateCurrentBid(): void
    {
        $highestBid = $this->highestBid();
        $this->current_bid = $highestBid ? $highestBid->amount : $this->starting_price;
        $this->save();
    }

    /**
     * Get the primary image URL.
     */
    public function getPrimaryImageAttribute(): ?string
    {
        return $this->images && count($this->images) > 0 ? $this->images[0] : null;
    }
}

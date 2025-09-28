<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'description',
        'start_date',
        'end_date',
        'location',
        'status',
        'settings',
        'transaction_fee_percentage',
        'fixed_transaction_fee',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'settings' => 'array',
        'transaction_fee_percentage' => 'decimal:2',
        'fixed_transaction_fee' => 'decimal:2',
    ];

    /**
     * Get the tenant that owns the event.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the bidders for the event.
     */
    public function bidders(): HasMany
    {
        return $this->hasMany(Bidder::class);
    }

    /**
     * Get the bid items for the event.
     */
    public function bidItems(): HasMany
    {
        return $this->hasMany(BidItem::class);
    }

    /**
     * Get the bids for the event.
     */
    public function bids(): HasMany
    {
        return $this->hasMany(Bid::class);
    }

    /**
     * Get the checkout sessions for the event.
     */
    public function checkoutSessions(): HasMany
    {
        return $this->hasMany(CheckoutSession::class);
    }

    /**
     * Get the shared credentials for the event.
     */
    public function sharedCredentials(): HasMany
    {
        return $this->hasMany(SharedCredential::class);
    }

    /**
     * Check if event is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if event is currently running.
     */
    public function isRunning(): bool
    {
        return $this->isActive() && 
               now()->between($this->start_date, $this->end_date);
    }

    /**
     * Get total revenue for the event.
     */
    public function getTotalRevenue(): float
    {
        return $this->checkoutSessions()
            ->where('status', 'completed')
            ->sum('total_amount');
    }

    /**
     * Get platform fees earned from this event.
     */
    public function getPlatformFeesEarned(): float
    {
        return $this->checkoutSessions()
            ->where('status', 'completed')
            ->sum('platform_fee');
    }
}

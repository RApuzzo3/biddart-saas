<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bidder extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'event_id',
        'bidder_number',
        'first_name',
        'last_name',
        'email',
        'phone',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'zip_code',
        'company_name',
        'active',
        'metadata',
    ];

    protected $casts = [
        'active' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * Get the tenant that owns the bidder.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the event that owns the bidder.
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Get the bids for the bidder.
     */
    public function bids(): HasMany
    {
        return $this->hasMany(Bid::class);
    }

    /**
     * Get the checkout sessions for the bidder.
     */
    public function checkoutSessions(): HasMany
    {
        return $this->hasMany(CheckoutSession::class);
    }

    /**
     * Get the bidder's full name.
     */
    public function getFullNameAttribute(): string
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    /**
     * Get the bidder's display name with number.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->bidder_number . ' - ' . $this->full_name;
    }

    /**
     * Get total amount bid by this bidder.
     */
    public function getTotalBidAmount(): float
    {
        return $this->bids()->sum('amount');
    }

    /**
     * Get winning bids for this bidder.
     */
    public function getWinningBids()
    {
        return $this->bids()->where('is_winning', true)->get();
    }

    /**
     * Get total amount owed by this bidder.
     */
    public function getTotalAmountOwed(): float
    {
        return $this->bids()->where('is_winning', true)->where('is_paid', false)->sum('amount');
    }

    /**
     * Check if bidder has any unpaid winning bids.
     */
    public function hasUnpaidBids(): bool
    {
        return $this->bids()->where('is_winning', true)->where('is_paid', false)->exists();
    }
}

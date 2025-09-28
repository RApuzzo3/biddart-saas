<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'domain',
        'database',
        'config',
        'active',
        'trial_ends_at',
        'subscription_ends_at',
        'subscription_status',
    ];

    protected $casts = [
        'config' => 'array',
        'active' => 'boolean',
        'trial_ends_at' => 'datetime',
        'subscription_ends_at' => 'datetime',
    ];

    /**
     * Get the users for the tenant.
     */
    public function users(): HasMany
    {
        return $this->hasMany(TenantUser::class);
    }

    /**
     * Get the events for the tenant.
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /**
     * Get the bidders for the tenant.
     */
    public function bidders(): HasMany
    {
        return $this->hasMany(Bidder::class);
    }

    /**
     * Get the bid item categories for the tenant.
     */
    public function bidItemCategories(): HasMany
    {
        return $this->hasMany(BidItemCategory::class);
    }

    /**
     * Check if tenant is on trial.
     */
    public function isOnTrial(): bool
    {
        return $this->subscription_status === 'trial' && 
               $this->trial_ends_at && 
               $this->trial_ends_at->isFuture();
    }

    /**
     * Check if tenant subscription is active.
     */
    public function hasActiveSubscription(): bool
    {
        return in_array($this->subscription_status, ['active', 'trial']) && 
               ($this->subscription_ends_at === null || $this->subscription_ends_at->isFuture());
    }
}

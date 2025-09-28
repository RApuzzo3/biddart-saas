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
     * Get total revenue for this tenant.
     */
    public function getTotalRevenue(): float
    {
        return $this->checkoutSessions()
            ->where('status', 'completed')
            ->sum('total_amount');
    }

    /**
     * Get platform fees earned from this tenant.
     */
    public function getPlatformFeesEarned(): float
    {
        return $this->checkoutSessions()
            ->where('status', 'completed')
            ->sum('platform_fee');
    }

    /**
     * Get Square access token for this tenant.
     */
    public function getSquareAccessToken(): ?string
    {
        return $this->config['square_access_token'] ?? null;
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

    /**
     * Get Square access token for this tenant.
     */
    public function getSquareAccessToken(): ?string
    {
        return $this->config['square_access_token'] ?? null;
    }

    /**
     * Get Square application ID for this tenant.
     */
    public function getSquareApplicationId(): ?string
    {
        return $this->config['square_application_id'] ?? null;
    }

    /**
     * Get Square environment (sandbox/production) for this tenant.
     */
    public function getSquareEnvironment(): string
    {
        return $this->config['square_environment'] ?? 'sandbox';
    }

    /**
     * Set Square credentials for this tenant.
     */
    public function setSquareCredentials(string $accessToken, string $applicationId, string $environment = 'sandbox'): void
    {
        $config = $this->config ?? [];
        $config['square_access_token'] = $accessToken;
        $config['square_application_id'] = $applicationId;
        $config['square_environment'] = $environment;
        
        $this->update(['config' => $config]);
    }

    /**
     * Check if Square is configured for this tenant.
     */
    public function hasSquareConfigured(): bool
    {
        return !empty($this->getSquareAccessToken()) && !empty($this->getSquareApplicationId());
    }

    /**
     * Get Square webhook signature key for this tenant.
     */
    public function getSquareWebhookSignatureKey(): ?string
    {
        return $this->config['square_webhook_signature_key'] ?? null;
    }
}

<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SharedCredential extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'event_id',
        'credential_name',
        'username',
        'password',
        'active',
        'permissions',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'active' => 'boolean',
        'permissions' => 'array',
        'password' => 'hashed',
    ];

    /**
     * Get the tenant that owns the shared credential.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the event that owns the shared credential.
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Get the phone verifications for this shared credential.
     */
    public function phoneVerifications(): HasMany
    {
        return $this->hasMany(PhoneVerification::class);
    }

    /**
     * Get active phone verifications.
     */
    public function activePhoneVerifications(): HasMany
    {
        return $this->phoneVerifications()->where('verified', true);
    }

    /**
     * Check if credential has a specific permission.
     */
    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions ?? []);
    }

    /**
     * Check if credential can manage bidders.
     */
    public function canManageBidders(): bool
    {
        return $this->hasPermission('manage_bidders');
    }

    /**
     * Check if credential can record bids.
     */
    public function canRecordBids(): bool
    {
        return $this->hasPermission('record_bids');
    }

    /**
     * Check if credential can process checkouts.
     */
    public function canProcessCheckouts(): bool
    {
        return $this->hasPermission('process_checkouts');
    }

    /**
     * Check if credential can view reports.
     */
    public function canViewReports(): bool
    {
        return $this->hasPermission('view_reports');
    }

    /**
     * Get default permissions for event staff.
     */
    public static function getDefaultPermissions(): array
    {
        return [
            'manage_bidders',
            'record_bids',
            'process_checkouts',
            'view_reports',
        ];
    }
}

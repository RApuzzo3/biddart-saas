<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhoneVerification extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'shared_credential_id',
        'phone_number',
        'verification_code',
        'alias_name',
        'verified',
        'verified_at',
        'expires_at',
        'session_id',
    ];

    protected $casts = [
        'verified' => 'boolean',
        'verified_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Get the tenant that owns the phone verification.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the shared credential that owns the phone verification.
     */
    public function sharedCredential(): BelongsTo
    {
        return $this->belongsTo(SharedCredential::class);
    }

    /**
     * Check if verification code is expired.
     */
    public function isExpired(): bool
    {
        return now()->gt($this->expires_at);
    }

    /**
     * Check if verification is still valid.
     */
    public function isValid(): bool
    {
        return $this->verified && !$this->isExpired();
    }

    /**
     * Mark verification as verified.
     */
    public function markAsVerified(): void
    {
        $this->update([
            'verified' => true,
            'verified_at' => now(),
        ]);
    }

    /**
     * Generate a random verification code.
     */
    public static function generateVerificationCode(): string
    {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Generate a random alias name.
     */
    public static function generateAliasName(): string
    {
        $adjectives = [
            'Swift', 'Bright', 'Quick', 'Smart', 'Bold', 'Keen', 'Sharp', 'Fast',
            'Wise', 'Cool', 'Calm', 'Kind', 'Fair', 'True', 'Good', 'Nice'
        ];

        $animals = [
            'Eagle', 'Tiger', 'Lion', 'Bear', 'Wolf', 'Fox', 'Hawk', 'Owl',
            'Deer', 'Rabbit', 'Dolphin', 'Whale', 'Shark', 'Falcon', 'Raven', 'Swan'
        ];

        $adjective = $adjectives[array_rand($adjectives)];
        $animal = $animals[array_rand($animals)];
        $number = random_int(10, 99);

        return $adjective . $animal . $number;
    }

    /**
     * Create a new phone verification.
     */
    public static function createVerification(
        int $tenantId,
        int $sharedCredentialId,
        string $phoneNumber
    ): self {
        return self::create([
            'tenant_id' => $tenantId,
            'shared_credential_id' => $sharedCredentialId,
            'phone_number' => $phoneNumber,
            'verification_code' => self::generateVerificationCode(),
            'alias_name' => self::generateAliasName(),
            'expires_at' => now()->addMinutes(10), // Code expires in 10 minutes
        ]);
    }

    /**
     * Get formatted phone number.
     */
    public function getFormattedPhoneAttribute(): string
    {
        $phone = preg_replace('/\D/', '', $this->phone_number);
        
        if (strlen($phone) === 10) {
            return sprintf('(%s) %s-%s',
                substr($phone, 0, 3),
                substr($phone, 3, 3),
                substr($phone, 6, 4)
            );
        }
        
        return $this->phone_number;
    }
}

<?php

namespace App\Helpers;

use App\Models\Tenant;
use App\Models\Event;
use App\Http\Controllers\SharedCredentialController;
use Illuminate\Support\Facades\Auth;

class TenantHelper
{
    /**
     * Get the current tenant.
     */
    public static function current(): ?Tenant
    {
        return app('tenant');
    }

    /**
     * Get the current tenant ID.
     */
    public static function currentId(): ?int
    {
        $tenant = self::current();
        return $tenant ? $tenant->id : null;
    }

    /**
     * Check if we're in a tenant context.
     */
    public static function hasTenant(): bool
    {
        return self::current() !== null;
    }

    /**
     * Get the current user (regular or shared credential).
     */
    public static function currentUser(): ?array
    {
        // Check shared credential authentication
        if (SharedCredentialController::isSharedAuthenticated()) {
            $sharedUser = SharedCredentialController::getSharedUser();
            return [
                'type' => 'shared',
                'id' => null,
                'name' => $sharedUser['alias'],
                'phone' => $sharedUser['phone'],
                'tenant_id' => $sharedUser['tenant_id'],
                'event_id' => $sharedUser['event_id'],
                'permissions' => ['event_staff'], // Default permissions for shared users
            ];
        }

        // Check regular authentication
        if (Auth::check()) {
            $user = Auth::user();
            return [
                'type' => 'regular',
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'tenant_id' => $user->tenant_id ?? null,
                'role' => $user->role ?? null,
                'permissions' => self::getUserPermissions($user),
            ];
        }

        return null;
    }

    /**
     * Get user permissions based on role.
     */
    private static function getUserPermissions($user): array
    {
        if (!method_exists($user, 'hasRole')) {
            return [];
        }

        $permissions = [];

        if ($user->hasRole('super_admin')) {
            $permissions = ['*']; // All permissions
        } elseif ($user->hasRole('org_admin')) {
            $permissions = [
                'manage_events',
                'manage_bidders',
                'manage_bid_items',
                'record_bids',
                'process_checkouts',
                'view_reports',
                'manage_staff',
            ];
        } elseif ($user->hasRole('event_staff')) {
            $permissions = [
                'manage_bidders',
                'record_bids',
                'process_checkouts',
                'view_reports',
            ];
        } elseif ($user->hasRole('staff')) {
            $permissions = [
                'record_bids',
                'view_reports',
            ];
        }

        return $permissions;
    }

    /**
     * Check if current user has a specific permission.
     */
    public static function can(string $permission): bool
    {
        $user = self::currentUser();
        
        if (!$user) {
            return false;
        }

        // Super admin has all permissions
        if (in_array('*', $user['permissions'] ?? [])) {
            return true;
        }

        return in_array($permission, $user['permissions'] ?? []);
    }

    /**
     * Get tenant subdomain URL.
     */
    public static function getSubdomainUrl(?Tenant $tenant = null): string
    {
        $tenant = $tenant ?? self::current();
        
        if (!$tenant) {
            return config('app.url');
        }

        // If tenant has custom domain
        if ($tenant->domain) {
            return 'https://' . $tenant->domain;
        }

        // Use subdomain
        $baseUrl = config('app.url');
        $parsedUrl = parse_url($baseUrl);
        
        return $parsedUrl['scheme'] . '://' . $tenant->slug . '.' . $parsedUrl['host'] . 
               (isset($parsedUrl['port']) ? ':' . $parsedUrl['port'] : '');
    }

    /**
     * Generate unique bidder number for event.
     */
    public static function generateBidderNumber(Event $event): string
    {
        $lastBidder = $event->bidders()->latest('id')->first();
        
        if (!$lastBidder) {
            return '001';
        }

        $lastNumber = intval($lastBidder->bidder_number);
        $nextNumber = $lastNumber + 1;

        return str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Calculate platform fees for checkout.
     */
    public static function calculatePlatformFees(Event $event, float $subtotal): array
    {
        $percentageFee = ($subtotal * $event->transaction_fee_percentage) / 100;
        $fixedFee = $event->fixed_transaction_fee;
        $totalPlatformFee = $percentageFee + $fixedFee;

        // Square processing fees (2.6% + $0.10 for card-present)
        $processingFee = ($subtotal * 0.026) + 0.10;

        return [
            'percentage_fee' => $percentageFee,
            'fixed_fee' => $fixedFee,
            'total_platform_fee' => $totalPlatformFee,
            'processing_fee' => $processingFee,
            'total_fees' => $totalPlatformFee + $processingFee,
            'grand_total' => $subtotal + $totalPlatformFee + $processingFee,
        ];
    }

    /**
     * Format currency amount.
     */
    public static function formatCurrency(float $amount, string $currency = 'USD'): string
    {
        return '$' . number_format($amount, 2);
    }

    /**
     * Get tenant statistics.
     */
    public static function getTenantStats(?Tenant $tenant = null): array
    {
        $tenant = $tenant ?? self::current();
        
        if (!$tenant) {
            return [];
        }

        return [
            'total_events' => $tenant->events()->count(),
            'active_events' => $tenant->events()->where('status', 'active')->count(),
            'total_bidders' => $tenant->bidders()->count(),
            'total_bids' => $tenant->events()->withCount('bids')->get()->sum('bids_count'),
            'total_revenue' => $tenant->events()->sum(function ($event) {
                return $event->checkoutSessions()->where('status', 'completed')->sum('total_amount');
            }),
            'platform_fees_earned' => $tenant->events()->sum(function ($event) {
                return $event->checkoutSessions()->where('status', 'completed')->sum('platform_fee');
            }),
        ];
    }

    /**
     * Check if tenant subscription is active.
     */
    public static function hasActiveSubscription(?Tenant $tenant = null): bool
    {
        $tenant = $tenant ?? self::current();
        return $tenant ? $tenant->hasActiveSubscription() : false;
    }

    /**
     * Check if tenant is on trial.
     */
    public static function isOnTrial(?Tenant $tenant = null): bool
    {
        $tenant = $tenant ?? self::current();
        return $tenant ? $tenant->isOnTrial() : false;
    }

    /**
     * Get days remaining in trial.
     */
    public static function trialDaysRemaining(?Tenant $tenant = null): int
    {
        $tenant = $tenant ?? self::current();
        
        if (!$tenant || !$tenant->trial_ends_at) {
            return 0;
        }

        return max(0, now()->diffInDays($tenant->trial_ends_at, false));
    }
}

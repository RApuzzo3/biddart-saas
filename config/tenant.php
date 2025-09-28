<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Multi-Tenancy Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration options for the multi-tenant
    | functionality of the Biddart SAAS application.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Tenant Resolution Methods
    |--------------------------------------------------------------------------
    |
    | Define how tenants should be resolved from incoming requests.
    | Available methods: 'subdomain', 'domain', 'header', 'session'
    |
    */

    'resolution_methods' => [
        'subdomain' => true,
        'domain' => true,
        'session' => true, // For shared credentials
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how tenant data should be isolated.
    | 'single' - All tenants share the same database with tenant_id filtering
    | 'separate' - Each tenant has their own database
    |
    */

    'database_strategy' => env('TENANT_DATABASE_STRATEGY', 'single'),

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configure tenant-specific caching behavior.
    |
    */

    'cache' => [
        'prefix_with_tenant_id' => true,
        'tenant_data_ttl' => 3600, // 1 hour
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Tenant Settings
    |--------------------------------------------------------------------------
    |
    | Default configuration values for new tenants.
    |
    */

    'defaults' => [
        'subscription_status' => 'trial',
        'trial_days' => 30,
        'transaction_fee_percentage' => 2.50,
        'fixed_transaction_fee' => 0.30,
        'max_events' => 10,
        'max_bidders_per_event' => 1000,
        'max_bid_items_per_event' => 500,
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature Limits by Subscription
    |--------------------------------------------------------------------------
    |
    | Define feature limits based on subscription tiers.
    |
    */

    'subscription_limits' => [
        'trial' => [
            'max_events' => 3,
            'max_bidders_per_event' => 100,
            'max_bid_items_per_event' => 50,
            'max_users' => 5,
            'features' => [
                'basic_reporting',
                'email_receipts',
                'square_integration',
            ],
        ],
        'basic' => [
            'max_events' => 10,
            'max_bidders_per_event' => 500,
            'max_bid_items_per_event' => 200,
            'max_users' => 15,
            'features' => [
                'basic_reporting',
                'advanced_reporting',
                'email_receipts',
                'square_integration',
                'shared_credentials',
            ],
        ],
        'professional' => [
            'max_events' => 50,
            'max_bidders_per_event' => 2000,
            'max_bid_items_per_event' => 1000,
            'max_users' => 50,
            'features' => [
                'basic_reporting',
                'advanced_reporting',
                'custom_reporting',
                'email_receipts',
                'sms_receipts',
                'square_integration',
                'shared_credentials',
                'api_access',
                'webhooks',
            ],
        ],
        'enterprise' => [
            'max_events' => -1, // Unlimited
            'max_bidders_per_event' => -1,
            'max_bid_items_per_event' => -1,
            'max_users' => -1,
            'features' => [
                'basic_reporting',
                'advanced_reporting',
                'custom_reporting',
                'email_receipts',
                'sms_receipts',
                'square_integration',
                'shared_credentials',
                'api_access',
                'webhooks',
                'custom_branding',
                'priority_support',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Revenue Model Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the transaction-based revenue model.
    |
    */

    'revenue' => [
        'default_platform_fee_percentage' => 2.50,
        'default_fixed_fee' => 0.30,
        'minimum_platform_fee' => 0.50,
        'maximum_platform_fee_percentage' => 5.00,
        
        // Square processing fees (for calculation purposes)
        'square_card_present_percentage' => 2.6,
        'square_card_present_fixed' => 0.10,
        'square_card_not_present_percentage' => 2.9,
        'square_card_not_present_fixed' => 0.30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Shared Credentials Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the shared login system for event staff.
    |
    */

    'shared_credentials' => [
        'sms_verification_enabled' => true,
        'verification_code_length' => 6,
        'verification_code_ttl' => 600, // 10 minutes
        'max_verification_attempts' => 3,
        'session_timeout' => 28800, // 8 hours
        'auto_generate_aliases' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | Security-related configuration for multi-tenancy.
    |
    */

    'security' => [
        'enforce_tenant_isolation' => true,
        'log_tenant_switches' => true,
        'require_https_for_custom_domains' => true,
        'max_login_attempts' => 5,
        'lockout_duration' => 900, // 15 minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Configuration
    |--------------------------------------------------------------------------
    |
    | Configure tenant-specific notifications.
    |
    */

    'notifications' => [
        'trial_expiry_warning_days' => [7, 3, 1],
        'subscription_expiry_warning_days' => [30, 7, 3, 1],
        'payment_failure_retry_days' => [1, 3, 7],
    ],

];

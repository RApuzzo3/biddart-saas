<?php

namespace App\Providers;

use App\Models\Tenant;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Blade;

class TenantServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register tenant resolver
        $this->app->singleton('tenant', function () {
            return app('tenant') ?? null;
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register Blade directives for tenant-specific functionality
        $this->registerBladeDirectives();

        // Register view composers
        $this->registerViewComposers();

        // Register tenant-specific configurations
        $this->registerTenantConfigurations();
    }

    /**
     * Register custom Blade directives.
     */
    private function registerBladeDirectives(): void
    {
        // @tenant directive to check if we're in a tenant context
        Blade::if('tenant', function () {
            return app()->has('tenant') && app('tenant') instanceof Tenant;
        });

        // @tenantIs directive to check specific tenant
        Blade::if('tenantIs', function ($slug) {
            $tenant = app('tenant');
            return $tenant && $tenant->slug === $slug;
        });

        // @hasSubscription directive to check subscription status
        Blade::if('hasSubscription', function () {
            $tenant = app('tenant');
            return $tenant && $tenant->hasActiveSubscription();
        });

        // @onTrial directive to check if tenant is on trial
        Blade::if('onTrial', function () {
            $tenant = app('tenant');
            return $tenant && $tenant->isOnTrial();
        });

        // @canManageEvents directive for role checking
        Blade::if('canManageEvents', function () {
            if (auth()->check() && method_exists(auth()->user(), 'canManageEvents')) {
                return auth()->user()->canManageEvents();
            }
            return false;
        });

        // @canProcessCheckouts directive for role checking
        Blade::if('canProcessCheckouts', function () {
            if (auth()->check() && method_exists(auth()->user(), 'canProcessCheckouts')) {
                return auth()->user()->canProcessCheckouts();
            }
            return false;
        });
    }

    /**
     * Register view composers.
     */
    private function registerViewComposers(): void
    {
        // Share tenant data with all views
        View::composer('*', function ($view) {
            $tenant = app('tenant');
            
            if ($tenant) {
                $view->with([
                    'currentTenant' => $tenant,
                    'tenantConfig' => $tenant->config ?? [],
                    'isOnTrial' => $tenant->isOnTrial(),
                    'hasActiveSubscription' => $tenant->hasActiveSubscription(),
                ]);
            }
        });

        // Share navigation data with layout views
        View::composer(['layouts.*', 'tenant.*'], function ($view) {
            $tenant = app('tenant');
            
            if ($tenant) {
                $navigationData = [
                    'activeEvents' => $tenant->events()->where('status', 'active')->count(),
                    'totalBidders' => $tenant->bidders()->count(),
                    'totalRevenue' => $tenant->events()->sum(function ($event) {
                        return $event->checkoutSessions()->where('status', 'completed')->sum('total_amount');
                    }),
                ];

                $view->with('navigationData', $navigationData);
            }
        });
    }

    /**
     * Register tenant-specific configurations.
     */
    private function registerTenantConfigurations(): void
    {
        // Set up tenant-specific mail configuration
        $this->app->resolving('mailer', function ($mailer) {
            $tenant = app('tenant');
            
            if ($tenant && isset($tenant->config['mail'])) {
                // Override mail configuration with tenant-specific settings
                config(['mail' => array_merge(config('mail'), $tenant->config['mail'])]);
            }
        });

        // Set up tenant-specific cache prefix
        $this->app->booting(function () {
            $tenant = app('tenant');
            
            if ($tenant) {
                config(['cache.prefix' => config('cache.prefix') . '_tenant_' . $tenant->id]);
            }
        });
    }
}

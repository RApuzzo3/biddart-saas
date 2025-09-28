<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\TenantUser;
use App\Http\Controllers\SharedCredentialController;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class TenantMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            return $this->handleMissingTenant($request);
        }

        // Set tenant context
        $this->setTenantContext($tenant);

        // Verify user belongs to tenant (unless using shared credentials)
        if (!$this->verifyTenantAccess($request, $tenant)) {
            return $this->handleUnauthorizedAccess($request);
        }

        return $next($request);
    }

    /**
     * Resolve the tenant from the request.
     */
    private function resolveTenant(Request $request): ?Tenant
    {
        // Method 1: Check for shared credential session (has tenant_id in session)
        if (SharedCredentialController::isSharedAuthenticated()) {
            $sharedUser = SharedCredentialController::getSharedUser();
            if ($sharedUser && $sharedUser['tenant_id']) {
                return Tenant::find($sharedUser['tenant_id']);
            }
        }

        // Method 2: Check authenticated user's tenant
        if (Auth::check() && Auth::user() instanceof TenantUser) {
            return Auth::user()->tenant;
        }

        // Method 3: Resolve from subdomain (e.g., nonprofit1.biddart.com)
        $host = $request->getHost();
        $subdomain = $this->extractSubdomain($host);
        
        if ($subdomain && $subdomain !== 'www') {
            return Tenant::where('slug', $subdomain)
                ->where('active', true)
                ->first();
        }

        // Method 4: Resolve from custom domain
        return Tenant::where('domain', $host)
            ->where('active', true)
            ->first();
    }

    /**
     * Extract subdomain from host.
     */
    private function extractSubdomain(string $host): ?string
    {
        $parts = explode('.', $host);
        
        // For localhost development
        if ($host === 'localhost' || str_contains($host, '127.0.0.1')) {
            return null;
        }

        // For production domains like nonprofit1.biddart.com
        if (count($parts) >= 3) {
            return $parts[0];
        }

        return null;
    }

    /**
     * Set tenant context for the application.
     */
    private function setTenantContext(Tenant $tenant): void
    {
        // Store tenant in app container
        app()->instance('tenant', $tenant);
        
        // Set database connection if using separate databases per tenant
        if ($tenant->database) {
            Config::set('database.connections.tenant', [
                'driver' => 'mysql',
                'host' => env('DB_HOST', '127.0.0.1'),
                'port' => env('DB_PORT', '3306'),
                'database' => $tenant->database,
                'username' => env('DB_USERNAME', 'forge'),
                'password' => env('DB_PASSWORD', ''),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => true,
            ]);

            DB::setDefaultConnection('tenant');
        }

        // Set tenant-specific configuration
        if ($tenant->config) {
            foreach ($tenant->config as $key => $value) {
                Config::set("tenant.{$key}", $value);
            }
        }

        // Share tenant with all views
        view()->share('currentTenant', $tenant);
    }

    /**
     * Verify user has access to the tenant.
     */
    private function verifyTenantAccess(Request $request, Tenant $tenant): bool
    {
        // Allow shared credential access
        if (SharedCredentialController::isSharedAuthenticated()) {
            $sharedUser = SharedCredentialController::getSharedUser();
            return $sharedUser && $sharedUser['tenant_id'] == $tenant->id;
        }

        // Check regular authenticated user
        if (Auth::check()) {
            $user = Auth::user();
            
            // Super admin can access any tenant
            if ($user instanceof TenantUser && $user->isSuperAdmin()) {
                return true;
            }

            // Regular user must belong to the tenant
            if ($user instanceof TenantUser && $user->tenant_id == $tenant->id) {
                return true;
            }
        }

        return false;
    }

    /**
     * Handle missing tenant scenario.
     */
    private function handleMissingTenant(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'error' => 'Tenant not found or inactive.',
            ], 404);
        }

        // Redirect to main site or show tenant selection
        return redirect()->to(config('app.url'))->with('error', 'Organization not found.');
    }

    /**
     * Handle unauthorized access.
     */
    private function handleUnauthorizedAccess(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'error' => 'Unauthorized access to this organization.',
            ], 403);
        }

        return redirect()->route('shared.login')->with('error', 'You do not have access to this organization.');
    }
}

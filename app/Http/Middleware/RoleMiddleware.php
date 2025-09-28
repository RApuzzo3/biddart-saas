<?php

namespace App\Http\Middleware;

use App\Http\Controllers\SharedCredentialController;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!$this->hasRequiredRole($roles)) {
            return $this->handleUnauthorized($request);
        }

        return $next($request);
    }

    /**
     * Check if user has any of the required roles.
     */
    private function hasRequiredRole(array $roles): bool
    {
        // Check shared credential access
        if (SharedCredentialController::isSharedAuthenticated()) {
            return $this->checkSharedCredentialPermissions($roles);
        }

        // Check regular authenticated user
        if (Auth::check()) {
            $user = Auth::user();
            
            if (method_exists($user, 'hasRole')) {
                foreach ($roles as $role) {
                    if ($user->hasRole($role)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Check shared credential permissions.
     */
    private function checkSharedCredentialPermissions(array $roles): bool
    {
        // Shared credentials have limited roles
        $allowedRoles = ['event_staff', 'staff'];
        
        foreach ($roles as $role) {
            if (in_array($role, $allowedRoles)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Handle unauthorized access.
     */
    private function handleUnauthorized(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'error' => 'Insufficient permissions.',
            ], 403);
        }

        return redirect()->back()->with('error', 'You do not have permission to access this resource.');
    }
}

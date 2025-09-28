<?php

namespace App\Http\Middleware;

use App\Http\Controllers\SharedCredentialController;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SharedAuthMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is authenticated via regular auth or shared credentials
        if (!$this->isAuthenticated()) {
            return $this->handleUnauthenticated($request);
        }

        return $next($request);
    }

    /**
     * Check if user is authenticated via any method.
     */
    private function isAuthenticated(): bool
    {
        // Check regular authentication
        if (Auth::check()) {
            return true;
        }

        // Check shared credential authentication
        if (SharedCredentialController::isSharedAuthenticated()) {
            return true;
        }

        return false;
    }

    /**
     * Handle unauthenticated access.
     */
    private function handleUnauthenticated(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'error' => 'Authentication required.',
            ], 401);
        }

        // Redirect to appropriate login based on request path
        if (str_starts_with($request->path(), 'shared/')) {
            return redirect()->route('shared.login');
        }

        return redirect()->route('login');
    }
}

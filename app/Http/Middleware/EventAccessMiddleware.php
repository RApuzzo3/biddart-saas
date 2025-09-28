<?php

namespace App\Http\Middleware;

use App\Models\Event;
use App\Http\Controllers\SharedCredentialController;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EventAccessMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $event = $this->getEventFromRequest($request);

        if (!$event) {
            return $this->handleEventNotFound($request);
        }

        if (!$this->hasEventAccess($event)) {
            return $this->handleUnauthorizedAccess($request);
        }

        // Share event with views
        view()->share('currentEvent', $event);

        return $next($request);
    }

    /**
     * Get event from request parameters.
     */
    private function getEventFromRequest(Request $request): ?Event
    {
        $eventId = $request->route('event');
        
        if (!$eventId) {
            return null;
        }

        // If it's an Event model instance (route model binding)
        if ($eventId instanceof Event) {
            return $eventId;
        }

        // If it's an ID, find the event
        return Event::find($eventId);
    }

    /**
     * Check if user has access to the event.
     */
    private function hasEventAccess(Event $event): bool
    {
        // Check shared credential access
        if (SharedCredentialController::isSharedAuthenticated()) {
            $sharedUser = SharedCredentialController::getSharedUser();
            return $sharedUser && $sharedUser['event_id'] == $event->id;
        }

        // Check regular authenticated user
        if (Auth::check()) {
            $user = Auth::user();
            
            // Super admin can access any event
            if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
                return true;
            }

            // User must belong to the same tenant as the event
            if (method_exists($user, 'tenant_id') && $user->tenant_id == $event->tenant_id) {
                return true;
            }
        }

        return false;
    }

    /**
     * Handle event not found.
     */
    private function handleEventNotFound(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'error' => 'Event not found.',
            ], 404);
        }

        return redirect()->route('events.index')->with('error', 'Event not found.');
    }

    /**
     * Handle unauthorized access.
     */
    private function handleUnauthorizedAccess(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'error' => 'Unauthorized access to this event.',
            ], 403);
        }

        return redirect()->route('events.index')->with('error', 'You do not have access to this event.');
    }
}

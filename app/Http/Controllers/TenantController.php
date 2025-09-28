<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TenantController extends Controller
{
    /**
     * Display a listing of tenants (Super Admin only).
     */
    public function index()
    {
        $tenants = Tenant::with(['users', 'events'])
            ->withCount(['users', 'events'])
            ->paginate(20);

        return view('admin.tenants.index', compact('tenants'));
    }

    /**
     * Show the form for creating a new tenant.
     */
    public function create()
    {
        return view('admin.tenants.create');
    }

    /**
     * Store a newly created tenant.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:tenants,slug',
            'domain' => 'nullable|string|max:255|unique:tenants,domain',
            'subscription_status' => 'required|in:trial,active,inactive,cancelled',
            'trial_ends_at' => 'nullable|date|after:today',
        ]);

        // Auto-generate slug if not provided
        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $tenant = Tenant::create($validated);

        return redirect()
            ->route('admin.tenants.show', $tenant)
            ->with('success', 'Tenant created successfully!');
    }

    /**
     * Display the specified tenant.
     */
    public function show(Tenant $tenant)
    {
        $tenant->load(['users', 'events.checkoutSessions']);
        
        $stats = [
            'total_users' => $tenant->users()->count(),
            'total_events' => $tenant->events()->count(),
            'active_events' => $tenant->events()->where('status', 'active')->count(),
            'total_revenue' => $tenant->events()->sum(function ($event) {
                return $event->checkoutSessions()->where('status', 'completed')->sum('total_amount');
            }),
            'platform_fees_earned' => $tenant->events()->sum(function ($event) {
                return $event->checkoutSessions()->where('status', 'completed')->sum('platform_fee');
            }),
        ];

        return view('admin.tenants.show', compact('tenant', 'stats'));
    }

    /**
     * Show the form for editing the tenant.
     */
    public function edit(Tenant $tenant)
    {
        return view('admin.tenants.edit', compact('tenant'));
    }

    /**
     * Update the specified tenant.
     */
    public function update(Request $request, Tenant $tenant)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:tenants,slug,' . $tenant->id,
            'domain' => 'nullable|string|max:255|unique:tenants,domain,' . $tenant->id,
            'subscription_status' => 'required|in:trial,active,inactive,cancelled',
            'trial_ends_at' => 'nullable|date',
            'subscription_ends_at' => 'nullable|date',
            'active' => 'boolean',
        ]);

        $tenant->update($validated);

        return redirect()
            ->route('admin.tenants.show', $tenant)
            ->with('success', 'Tenant updated successfully!');
    }

    /**
     * Remove the specified tenant.
     */
    public function destroy(Tenant $tenant)
    {
        $tenant->delete();

        return redirect()
            ->route('admin.tenants.index')
            ->with('success', 'Tenant deleted successfully!');
    }

    /**
     * Tenant dashboard (for organization admins).
     */
    public function dashboard()
    {
        $tenant = auth()->user()->tenant;
        
        $stats = [
            'total_events' => $tenant->events()->count(),
            'active_events' => $tenant->events()->where('status', 'active')->count(),
            'total_bidders' => $tenant->bidders()->count(),
            'total_revenue' => $tenant->events()->sum(function ($event) {
                return $event->checkoutSessions()->where('status', 'completed')->sum('total_amount');
            }),
        ];

        $recentEvents = $tenant->events()
            ->with(['bidders', 'checkoutSessions'])
            ->latest()
            ->take(5)
            ->get();

        return view('tenant.dashboard', compact('tenant', 'stats', 'recentEvents'));
    }
}

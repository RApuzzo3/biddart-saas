<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EventController extends Controller
{
    /**
     * Display a listing of events for the tenant.
     */
    public function index()
    {
        $tenant = auth()->user()->tenant;
        
        $events = $tenant->events()
            ->withCount(['bidders', 'bidItems', 'bids'])
            ->latest()
            ->paginate(20);

        return view('tenant.events.index', compact('events'));
    }

    /**
     * Show the form for creating a new event.
     */
    public function create()
    {
        return view('tenant.events.create');
    }

    /**
     * Store a newly created event.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'required|date|after:today',
            'end_date' => 'required|date|after:start_date',
            'location' => 'nullable|string|max:255',
            'transaction_fee_percentage' => 'required|numeric|min:0|max:10',
            'fixed_transaction_fee' => 'required|numeric|min:0|max:5',
        ]);

        $tenant = auth()->user()->tenant;

        // Auto-generate slug if not provided
        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        // Ensure slug is unique within tenant
        $originalSlug = $validated['slug'];
        $counter = 1;
        while ($tenant->events()->where('slug', $validated['slug'])->exists()) {
            $validated['slug'] = $originalSlug . '-' . $counter;
            $counter++;
        }

        $validated['tenant_id'] = $tenant->id;
        $validated['status'] = 'draft';

        $event = Event::create($validated);

        return redirect()
            ->route('events.show', $event)
            ->with('success', 'Event created successfully!');
    }

    /**
     * Display the specified event.
     */
    public function show(Event $event)
    {
        $event->load(['bidders', 'bidItems.category', 'bids.bidder']);
        
        $stats = [
            'total_bidders' => $event->bidders()->count(),
            'total_bid_items' => $event->bidItems()->count(),
            'total_bids' => $event->bids()->count(),
            'total_revenue' => $event->getTotalRevenue(),
            'platform_fees_earned' => $event->getPlatformFeesEarned(),
            'current_bid_total' => $event->bids()->sum('amount'),
        ];

        $recentBids = $event->bids()
            ->with(['bidder', 'bidItem'])
            ->latest()
            ->take(10)
            ->get();

        return view('tenant.events.show', compact('event', 'stats', 'recentBids'));
    }

    /**
     * Show the form for editing the event.
     */
    public function edit(Event $event)
    {
        return view('tenant.events.edit', compact('event'));
    }

    /**
     * Update the specified event.
     */
    public function update(Request $request, Event $event)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'location' => 'nullable|string|max:255',
            'status' => 'required|in:draft,active,completed,cancelled',
            'transaction_fee_percentage' => 'required|numeric|min:0|max:10',
            'fixed_transaction_fee' => 'required|numeric|min:0|max:5',
        ]);

        // Ensure slug is unique within tenant (excluding current event)
        $tenant = auth()->user()->tenant;
        $originalSlug = $validated['slug'];
        $counter = 1;
        while ($tenant->events()->where('slug', $validated['slug'])->where('id', '!=', $event->id)->exists()) {
            $validated['slug'] = $originalSlug . '-' . $counter;
            $counter++;
        }

        $event->update($validated);

        return redirect()
            ->route('events.show', $event)
            ->with('success', 'Event updated successfully!');
    }

    /**
     * Remove the specified event.
     */
    public function destroy(Event $event)
    {
        $event->delete();

        return redirect()
            ->route('events.index')
            ->with('success', 'Event deleted successfully!');
    }

    /**
     * Event dashboard for real-time monitoring.
     */
    public function dashboard(Event $event)
    {
        $stats = [
            'total_bidders' => $event->bidders()->count(),
            'active_bidders' => $event->bidders()->where('active', true)->count(),
            'total_bid_items' => $event->bidItems()->count(),
            'active_bid_items' => $event->bidItems()->where('active', true)->count(),
            'total_bids' => $event->bids()->count(),
            'current_bid_total' => $event->bids()->sum('amount'),
            'completed_checkouts' => $event->checkoutSessions()->where('status', 'completed')->count(),
            'total_revenue' => $event->getTotalRevenue(),
            'platform_fees_earned' => $event->getPlatformFeesEarned(),
        ];

        return view('tenant.events.dashboard', compact('event', 'stats'));
    }

    /**
     * Get real-time stats for event dashboard (AJAX endpoint).
     */
    public function realtimeStats(Event $event)
    {
        return response()->json([
            'total_bids' => $event->bids()->count(),
            'current_bid_total' => number_format($event->bids()->sum('amount'), 2),
            'recent_bids' => $event->bids()
                ->with(['bidder', 'bidItem'])
                ->latest()
                ->take(5)
                ->get()
                ->map(function ($bid) {
                    return [
                        'bidder_name' => $bid->bidder->display_name,
                        'item_name' => $bid->bidItem->name,
                        'amount' => $bid->formatted_amount,
                        'time' => $bid->created_at->diffForHumans(),
                    ];
                }),
        ]);
    }
}

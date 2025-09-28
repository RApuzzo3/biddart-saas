<?php

namespace App\Http\Controllers;

use App\Models\BidItem;
use App\Models\BidItemCategory;
use App\Models\Event;
use Illuminate\Http\Request;

class BidItemController extends Controller
{
    /**
     * Display a listing of bid items for the event.
     */
    public function index(Event $event)
    {
        $bidItems = $event->bidItems()
            ->with(['category'])
            ->withCount('bids')
            ->paginate(20);

        $categories = $event->tenant->bidItemCategories()->where('active', true)->get();

        return view('tenant.bid-items.index', compact('event', 'bidItems', 'categories'));
    }

    /**
     * Show the form for creating a new bid item.
     */
    public function create(Event $event)
    {
        $categories = $event->tenant->bidItemCategories()->where('active', true)->get();
        
        return view('tenant.bid-items.create', compact('event', 'categories'));
    }

    /**
     * Store a newly created bid item.
     */
    public function store(Request $request, Event $event)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:bid_item_categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'starting_price' => 'required|numeric|min:0',
            'buy_now_price' => 'nullable|numeric|min:0',
            'bid_increment' => 'required|integer|min:1',
            'type' => 'required|in:auction,raffle,buy_now,donation',
            'featured' => 'boolean',
            'bidding_starts_at' => 'nullable|date',
            'bidding_ends_at' => 'nullable|date|after:bidding_starts_at',
            'images' => 'nullable|array',
            'images.*' => 'url',
        ]);

        $validated['tenant_id'] = $event->tenant_id;
        $validated['event_id'] = $event->id;
        $validated['current_bid'] = $validated['starting_price'];

        $bidItem = BidItem::create($validated);

        return redirect()
            ->route('events.bid-items.show', [$event, $bidItem])
            ->with('success', 'Bid item created successfully!');
    }

    /**
     * Display the specified bid item.
     */
    public function show(Event $event, BidItem $bidItem)
    {
        $bidItem->load(['category', 'bids.bidder']);
        
        $stats = [
            'total_bids' => $bidItem->bids()->count(),
            'current_bid' => $bidItem->current_bid,
            'next_minimum_bid' => $bidItem->getNextMinimumBid(),
            'is_bidding_open' => $bidItem->isBiddingOpen(),
            'can_buy_now' => $bidItem->canBuyNow(),
        ];

        $bids = $bidItem->bids()
            ->with(['bidder'])
            ->orderBy('amount', 'desc')
            ->get();

        return view('tenant.bid-items.show', compact('event', 'bidItem', 'stats', 'bids'));
    }

    /**
     * Show the form for editing the bid item.
     */
    public function edit(Event $event, BidItem $bidItem)
    {
        $categories = $event->tenant->bidItemCategories()->where('active', true)->get();
        
        return view('tenant.bid-items.edit', compact('event', 'bidItem', 'categories'));
    }

    /**
     * Update the specified bid item.
     */
    public function update(Request $request, Event $event, BidItem $bidItem)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:bid_item_categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'starting_price' => 'required|numeric|min:0',
            'buy_now_price' => 'nullable|numeric|min:0',
            'bid_increment' => 'required|integer|min:1',
            'type' => 'required|in:auction,raffle,buy_now,donation',
            'featured' => 'boolean',
            'active' => 'boolean',
            'bidding_starts_at' => 'nullable|date',
            'bidding_ends_at' => 'nullable|date|after:bidding_starts_at',
            'images' => 'nullable|array',
            'images.*' => 'url',
        ]);

        $bidItem->update($validated);

        return redirect()
            ->route('events.bid-items.show', [$event, $bidItem])
            ->with('success', 'Bid item updated successfully!');
    }

    /**
     * Remove the specified bid item.
     */
    public function destroy(Event $event, BidItem $bidItem)
    {
        $bidItem->delete();

        return redirect()
            ->route('events.bid-items.index', $event)
            ->with('success', 'Bid item deleted successfully!');
    }

    /**
     * Search bid items (AJAX endpoint).
     */
    public function search(Request $request, Event $event)
    {
        $query = $request->get('q', '');
        
        $bidItems = $event->bidItems()
            ->where('name', 'like', "%{$query}%")
            ->where('active', true)
            ->take(10)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'current_bid' => $item->current_bid,
                    'next_minimum_bid' => $item->getNextMinimumBid(),
                    'type' => $item->type,
                    'is_bidding_open' => $item->isBiddingOpen(),
                ];
            });

        return response()->json($bidItems);
    }

    /**
     * Toggle featured status.
     */
    public function toggleFeatured(Event $event, BidItem $bidItem)
    {
        $bidItem->update(['featured' => !$bidItem->featured]);

        return response()->json([
            'success' => true,
            'featured' => $bidItem->featured,
            'message' => $bidItem->featured ? 'Item featured successfully!' : 'Item unfeatured successfully!',
        ]);
    }

    /**
     * Toggle active status.
     */
    public function toggleActive(Event $event, BidItem $bidItem)
    {
        $bidItem->update(['active' => !$bidItem->active]);

        return response()->json([
            'success' => true,
            'active' => $bidItem->active,
            'message' => $bidItem->active ? 'Item activated successfully!' : 'Item deactivated successfully!',
        ]);
    }

    /**
     * Get item details for bidding interface (AJAX endpoint).
     */
    public function details(Event $event, BidItem $bidItem)
    {
        return response()->json([
            'id' => $bidItem->id,
            'name' => $bidItem->name,
            'description' => $bidItem->description,
            'current_bid' => $bidItem->current_bid,
            'next_minimum_bid' => $bidItem->getNextMinimumBid(),
            'buy_now_price' => $bidItem->buy_now_price,
            'type' => $bidItem->type,
            'is_bidding_open' => $bidItem->isBiddingOpen(),
            'can_buy_now' => $bidItem->canBuyNow(),
            'primary_image' => $bidItem->primary_image,
            'highest_bid' => $bidItem->highestBid()?->load('bidder'),
            'recent_bids' => $bidItem->bids()
                ->with('bidder')
                ->latest()
                ->take(5)
                ->get()
                ->map(function ($bid) {
                    return [
                        'amount' => $bid->formatted_amount,
                        'bidder' => $bid->bidder->display_name,
                        'time' => $bid->created_at->diffForHumans(),
                    ];
                }),
        ]);
    }
}

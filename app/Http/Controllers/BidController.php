<?php

namespace App\Http\Controllers;

use App\Models\Bid;
use App\Models\BidItem;
use App\Models\Bidder;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BidController extends Controller
{
    /**
     * Display a listing of bids for the event.
     */
    public function index(Event $event)
    {
        $bids = $event->bids()
            ->with(['bidder', 'bidItem', 'createdBy'])
            ->latest()
            ->paginate(50);

        return view('tenant.bids.index', compact('event', 'bids'));
    }

    /**
     * Store a newly created bid.
     */
    public function store(Request $request, Event $event)
    {
        $validated = $request->validate([
            'bidder_id' => 'required|exists:bidders,id',
            'bid_item_id' => 'required|exists:bid_items,id',
            'amount' => 'required|numeric|min:0',
            'type' => 'required|in:bid,buy_now,raffle_entry,donation',
        ]);

        $bidder = Bidder::findOrFail($validated['bidder_id']);
        $bidItem = BidItem::findOrFail($validated['bid_item_id']);

        // Validate bidder belongs to this event
        if ($bidder->event_id !== $event->id) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid bidder for this event.',
            ], 400);
        }

        // Validate bid item belongs to this event
        if ($bidItem->event_id !== $event->id) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid bid item for this event.',
            ], 400);
        }

        // Validate bidding is open
        if (!$bidItem->isBiddingOpen()) {
            return response()->json([
                'success' => false,
                'message' => 'Bidding is not currently open for this item.',
            ], 400);
        }

        // Validate bid amount based on type
        if ($validated['type'] === 'bid') {
            $minimumBid = $bidItem->getNextMinimumBid();
            if ($validated['amount'] < $minimumBid) {
                return response()->json([
                    'success' => false,
                    'message' => "Minimum bid is $" . number_format($minimumBid, 2),
                ], 400);
            }
        } elseif ($validated['type'] === 'buy_now') {
            if (!$bidItem->canBuyNow() || $validated['amount'] != $bidItem->buy_now_price) {
                return response()->json([
                    'success' => false,
                    'message' => 'Buy now is not available or amount is incorrect.',
                ], 400);
            }
        }

        DB::beginTransaction();
        try {
            // Create the bid
            $bid = Bid::create([
                'tenant_id' => $event->tenant_id,
                'event_id' => $event->id,
                'bidder_id' => $validated['bidder_id'],
                'bid_item_id' => $validated['bid_item_id'],
                'amount' => $validated['amount'],
                'type' => $validated['type'],
                'created_by' => auth()->id(),
            ]);

            // For auction bids and buy now, mark as winning bid
            if (in_array($validated['type'], ['bid', 'buy_now'])) {
                $bid->markAsWinning();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Bid recorded successfully!',
                'bid' => [
                    'id' => $bid->id,
                    'amount' => $bid->formatted_amount,
                    'type' => $bid->type,
                    'bidder' => $bid->bidder->display_name,
                    'item' => $bid->bidItem->name,
                    'time' => $bid->created_at->diffForHumans(),
                ],
                'item_stats' => [
                    'current_bid' => $bidItem->fresh()->current_bid,
                    'next_minimum_bid' => $bidItem->fresh()->getNextMinimumBid(),
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to record bid. Please try again.',
            ], 500);
        }
    }

    /**
     * Display the specified bid.
     */
    public function show(Event $event, Bid $bid)
    {
        $bid->load(['bidder', 'bidItem', 'createdBy']);

        return view('tenant.bids.show', compact('event', 'bid'));
    }

    /**
     * Remove the specified bid.
     */
    public function destroy(Event $event, Bid $bid)
    {
        // Only allow deletion if user has permission and bid is not paid
        if ($bid->is_paid) {
            return redirect()->back()->with('error', 'Cannot delete a paid bid.');
        }

        DB::beginTransaction();
        try {
            $bidItem = $bid->bidItem;
            $bid->delete();

            // Recalculate winning bid for the item
            $newHighestBid = $bidItem->bids()->orderBy('amount', 'desc')->first();
            if ($newHighestBid) {
                $newHighestBid->markAsWinning();
            } else {
                $bidItem->update(['current_bid' => $bidItem->starting_price]);
            }

            DB::commit();

            return redirect()
                ->route('events.bids.index', $event)
                ->with('success', 'Bid deleted successfully!');

        } catch (\Exception $e) {
            DB::rollback();
            
            return redirect()->back()->with('error', 'Failed to delete bid.');
        }
    }

    /**
     * Quick bid form for event staff.
     */
    public function quickBid(Event $event)
    {
        return view('tenant.bids.quick-bid', compact('event'));
    }

    /**
     * Get recent bids for real-time updates (AJAX endpoint).
     */
    public function recent(Event $event)
    {
        $bids = $event->bids()
            ->with(['bidder', 'bidItem'])
            ->latest()
            ->take(20)
            ->get()
            ->map(function ($bid) {
                return [
                    'id' => $bid->id,
                    'bidder_name' => $bid->bidder->display_name,
                    'item_name' => $bid->bidItem->name,
                    'amount' => $bid->formatted_amount,
                    'type' => $bid->type,
                    'time' => $bid->created_at->diffForHumans(),
                    'is_winning' => $bid->is_winning,
                ];
            });

        return response()->json($bids);
    }

    /**
     * Get bid statistics for the event (AJAX endpoint).
     */
    public function stats(Event $event)
    {
        return response()->json([
            'total_bids' => $event->bids()->count(),
            'total_amount' => number_format($event->bids()->sum('amount'), 2),
            'unique_bidders' => $event->bids()->distinct('bidder_id')->count(),
            'winning_bids' => $event->bids()->where('is_winning', true)->count(),
            'recent_activity' => $event->bids()
                ->with(['bidder', 'bidItem'])
                ->latest()
                ->take(5)
                ->get()
                ->map(function ($bid) {
                    return [
                        'bidder' => $bid->bidder->display_name,
                        'item' => $bid->bidItem->name,
                        'amount' => $bid->formatted_amount,
                        'time' => $bid->created_at->diffForHumans(),
                    ];
                }),
        ]);
    }

    /**
     * Bulk operations on bids.
     */
    public function bulkAction(Request $request, Event $event)
    {
        $validated = $request->validate([
            'action' => 'required|in:delete,mark_paid,mark_unpaid',
            'bid_ids' => 'required|array',
            'bid_ids.*' => 'exists:bids,id',
        ]);

        $bids = Bid::whereIn('id', $validated['bid_ids'])
            ->where('event_id', $event->id)
            ->get();

        $count = 0;
        foreach ($bids as $bid) {
            switch ($validated['action']) {
                case 'delete':
                    if (!$bid->is_paid) {
                        $bid->delete();
                        $count++;
                    }
                    break;
                case 'mark_paid':
                    $bid->update(['is_paid' => true]);
                    $count++;
                    break;
                case 'mark_unpaid':
                    $bid->update(['is_paid' => false]);
                    $count++;
                    break;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Successfully processed {$count} bids.",
        ]);
    }
}

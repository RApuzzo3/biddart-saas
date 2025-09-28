<?php

namespace App\Http\Controllers;

use App\Models\Bidder;
use App\Models\Event;
use Illuminate\Http\Request;

class BidderController extends Controller
{
    /**
     * Display a listing of bidders for the event.
     */
    public function index(Event $event)
    {
        $bidders = $event->bidders()
            ->withCount('bids')
            ->with(['bids' => function ($query) {
                $query->where('is_winning', true);
            }])
            ->paginate(20);

        return view('tenant.bidders.index', compact('event', 'bidders'));
    }

    /**
     * Show the form for creating a new bidder.
     */
    public function create(Event $event)
    {
        return view('tenant.bidders.create', compact('event'));
    }

    /**
     * Store a newly created bidder.
     */
    public function store(Request $request, Event $event)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address_line_1' => 'nullable|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:2',
            'zip_code' => 'nullable|string|max:10',
            'company_name' => 'nullable|string|max:255',
        ]);

        $validated['tenant_id'] = $event->tenant_id;
        $validated['event_id'] = $event->id;
        $validated['bidder_number'] = $this->generateBidderNumber($event);

        $bidder = Bidder::create($validated);

        return redirect()
            ->route('events.bidders.show', [$event, $bidder])
            ->with('success', 'Bidder registered successfully! Bidder Number: ' . $bidder->bidder_number);
    }

    /**
     * Display the specified bidder.
     */
    public function show(Event $event, Bidder $bidder)
    {
        $bidder->load(['bids.bidItem']);
        
        $stats = [
            'total_bids' => $bidder->bids()->count(),
            'winning_bids' => $bidder->bids()->where('is_winning', true)->count(),
            'total_bid_amount' => $bidder->getTotalBidAmount(),
            'total_amount_owed' => $bidder->getTotalAmountOwed(),
        ];

        $bids = $bidder->bids()
            ->with(['bidItem'])
            ->latest()
            ->get();

        return view('tenant.bidders.show', compact('event', 'bidder', 'stats', 'bids'));
    }

    /**
     * Show the form for editing the bidder.
     */
    public function edit(Event $event, Bidder $bidder)
    {
        return view('tenant.bidders.edit', compact('event', 'bidder'));
    }

    /**
     * Update the specified bidder.
     */
    public function update(Request $request, Event $event, Bidder $bidder)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address_line_1' => 'nullable|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:2',
            'zip_code' => 'nullable|string|max:10',
            'company_name' => 'nullable|string|max:255',
            'active' => 'boolean',
        ]);

        $bidder->update($validated);

        return redirect()
            ->route('events.bidders.show', [$event, $bidder])
            ->with('success', 'Bidder updated successfully!');
    }

    /**
     * Remove the specified bidder.
     */
    public function destroy(Event $event, Bidder $bidder)
    {
        $bidder->delete();

        return redirect()
            ->route('events.bidders.index', $event)
            ->with('success', 'Bidder deleted successfully!');
    }

    /**
     * Search bidders (AJAX endpoint).
     */
    public function search(Request $request, Event $event)
    {
        $query = $request->get('q', '');
        
        $bidders = $event->bidders()
            ->where(function ($q) use ($query) {
                $q->where('first_name', 'like', "%{$query}%")
                  ->orWhere('last_name', 'like', "%{$query}%")
                  ->orWhere('bidder_number', 'like', "%{$query}%")
                  ->orWhere('email', 'like', "%{$query}%")
                  ->orWhere('phone', 'like', "%{$query}%");
            })
            ->take(10)
            ->get()
            ->map(function ($bidder) {
                return [
                    'id' => $bidder->id,
                    'bidder_number' => $bidder->bidder_number,
                    'name' => $bidder->full_name,
                    'display_name' => $bidder->display_name,
                    'email' => $bidder->email,
                    'phone' => $bidder->phone,
                ];
            });

        return response()->json($bidders);
    }

    /**
     * Generate a unique bidder number for the event.
     */
    private function generateBidderNumber(Event $event): string
    {
        $lastBidder = $event->bidders()->latest('id')->first();
        
        if (!$lastBidder) {
            return '001';
        }

        $lastNumber = intval($lastBidder->bidder_number);
        $nextNumber = $lastNumber + 1;

        return str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Quick registration form (for event staff).
     */
    public function quickRegister(Event $event)
    {
        return view('tenant.bidders.quick-register', compact('event'));
    }

    /**
     * Store quick registration.
     */
    public function storeQuickRegister(Request $request, Event $event)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
        ]);

        $validated['tenant_id'] = $event->tenant_id;
        $validated['event_id'] = $event->id;
        $validated['bidder_number'] = $this->generateBidderNumber($event);

        $bidder = Bidder::create($validated);

        return response()->json([
            'success' => true,
            'bidder' => [
                'id' => $bidder->id,
                'bidder_number' => $bidder->bidder_number,
                'display_name' => $bidder->display_name,
            ],
            'message' => 'Bidder registered successfully! Bidder Number: ' . $bidder->bidder_number,
        ]);
    }
}

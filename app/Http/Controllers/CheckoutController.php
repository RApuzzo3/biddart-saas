<?php

namespace App\Http\Controllers;

use App\Models\Bidder;
use App\Models\CheckoutSession;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CheckoutController extends Controller
{
    /**
     * Display checkout sessions for the event.
     */
    public function index(Event $event)
    {
        $checkoutSessions = $event->checkoutSessions()
            ->with(['bidder', 'processedBy'])
            ->latest()
            ->paginate(20);

        return view('tenant.checkout.index', compact('event', 'checkoutSessions'));
    }

    /**
     * Show checkout form for a bidder.
     */
    public function create(Event $event, Bidder $bidder)
    {
        // Get all winning bids for this bidder that haven't been paid
        $winningBids = $bidder->bids()
            ->where('is_winning', true)
            ->where('is_paid', false)
            ->with('bidItem')
            ->get();

        if ($winningBids->isEmpty()) {
            return redirect()
                ->route('events.bidders.show', [$event, $bidder])
                ->with('info', 'This bidder has no unpaid winning bids.');
        }

        // Calculate totals
        $subtotal = $winningBids->sum('amount');
        $platformFee = CheckoutSession::calculatePlatformFee($event, $subtotal);
        $processingFee = CheckoutSession::calculateProcessingFee($subtotal + $platformFee);
        $total = $subtotal + $platformFee + $processingFee;

        return view('tenant.checkout.create', compact(
            'event', 
            'bidder', 
            'winningBids', 
            'subtotal', 
            'platformFee', 
            'processingFee', 
            'total'
        ));
    }

    /**
     * Process the checkout.
     */
    public function store(Request $request, Event $event, Bidder $bidder)
    {
        $validated = $request->validate([
            'payment_method' => 'required|in:card,cash,check',
            'tax_amount' => 'nullable|numeric|min:0',
            'bid_ids' => 'required|array',
            'bid_ids.*' => 'exists:bids,id',
        ]);

        // Verify all bids belong to this bidder and are winning/unpaid
        $bids = $bidder->bids()
            ->whereIn('id', $validated['bid_ids'])
            ->where('is_winning', true)
            ->where('is_paid', false)
            ->with('bidItem')
            ->get();

        if ($bids->count() !== count($validated['bid_ids'])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid bids selected for checkout.',
            ], 400);
        }

        DB::beginTransaction();
        try {
            // Calculate amounts
            $subtotal = $bids->sum('amount');
            $taxAmount = $validated['tax_amount'] ?? 0;
            $platformFee = CheckoutSession::calculatePlatformFee($event, $subtotal);
            $processingFee = CheckoutSession::calculateProcessingFee($subtotal + $taxAmount + $platformFee);
            $totalAmount = $subtotal + $taxAmount + $platformFee + $processingFee;

            // Create checkout session
            $checkoutSession = CheckoutSession::create([
                'tenant_id' => $event->tenant_id,
                'event_id' => $event->id,
                'bidder_id' => $bidder->id,
                'session_id' => CheckoutSession::generateSessionId(),
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'platform_fee' => $platformFee,
                'payment_processing_fee' => $processingFee,
                'total_amount' => $totalAmount,
                'status' => 'pending',
                'payment_method' => $validated['payment_method'],
                'items' => $bids->map(function ($bid) {
                    return [
                        'bid_id' => $bid->id,
                        'item_name' => $bid->bidItem->name,
                        'amount' => $bid->amount,
                    ];
                })->toArray(),
                'processed_by' => auth()->id(),
            ]);

            // For cash/check payments, mark as completed immediately
            if (in_array($validated['payment_method'], ['cash', 'check'])) {
                $checkoutSession->markAsCompleted();
            }

            DB::commit();

            if ($validated['payment_method'] === 'card') {
                // Redirect to Square payment processing
                return response()->json([
                    'success' => true,
                    'redirect' => route('checkout.square', [$event, $checkoutSession]),
                ]);
            } else {
                // For cash/check, return success
                return response()->json([
                    'success' => true,
                    'message' => 'Checkout completed successfully!',
                    'checkout_session' => $checkoutSession->load('bidder'),
                ]);
            }

        } catch (\Exception $e) {
            DB::rollback();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to process checkout. Please try again.',
            ], 500);
        }
    }

    /**
     * Show checkout session details.
     */
    public function show(Event $event, CheckoutSession $checkoutSession)
    {
        $checkoutSession->load(['bidder', 'processedBy']);

        return view('tenant.checkout.show', compact('event', 'checkoutSession'));
    }

    /**
     * Square payment processing page.
     */
    public function square(Event $event, CheckoutSession $checkoutSession)
    {
        if ($checkoutSession->payment_method !== 'card' || $checkoutSession->isCompleted()) {
            return redirect()
                ->route('checkout.show', [$event, $checkoutSession])
                ->with('info', 'This checkout session is not available for card processing.');
        }

        return view('tenant.checkout.square', compact('event', 'checkoutSession'));
    }

    /**
     * Process Square payment.
     */
    public function processSquarePayment(Request $request, Event $event, CheckoutSession $checkoutSession)
    {
        $validated = $request->validate([
            'source_id' => 'required|string', // Square payment token
        ]);

        // TODO: Implement Square API payment processing
        // This would integrate with Square's Payment API
        
        try {
            // Simulate Square payment processing
            $paymentResult = $this->processSquarePaymentAPI($checkoutSession, $validated['source_id']);
            
            if ($paymentResult['success']) {
                $checkoutSession->update([
                    'status' => 'completed',
                    'square_payment_id' => $paymentResult['payment_id'],
                    'square_receipt_url' => $paymentResult['receipt_url'],
                    'payment_details' => $paymentResult['details'],
                    'completed_at' => now(),
                ]);

                $checkoutSession->markAsCompleted();

                return response()->json([
                    'success' => true,
                    'message' => 'Payment processed successfully!',
                    'receipt_url' => $paymentResult['receipt_url'],
                ]);
            } else {
                $checkoutSession->update(['status' => 'failed']);
                
                return response()->json([
                    'success' => false,
                    'message' => $paymentResult['error'] ?? 'Payment failed. Please try again.',
                ], 400);
            }

        } catch (\Exception $e) {
            $checkoutSession->update(['status' => 'failed']);
            
            return response()->json([
                'success' => false,
                'message' => 'Payment processing failed. Please try again.',
            ], 500);
        }
    }

    /**
     * Generate receipt for checkout session.
     */
    public function receipt(Event $event, CheckoutSession $checkoutSession)
    {
        if (!$checkoutSession->isCompleted()) {
            return redirect()
                ->route('checkout.show', [$event, $checkoutSession])
                ->with('error', 'Receipt is not available for incomplete checkouts.');
        }

        $checkoutSession->load(['bidder', 'processedBy']);

        return view('tenant.checkout.receipt', compact('event', 'checkoutSession'));
    }

    /**
     * Email receipt to bidder.
     */
    public function emailReceipt(Event $event, CheckoutSession $checkoutSession)
    {
        if (!$checkoutSession->isCompleted() || !$checkoutSession->bidder->email) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot send receipt for this checkout session.',
            ], 400);
        }

        // TODO: Implement email receipt functionality
        // This would send an email with the receipt details

        return response()->json([
            'success' => true,
            'message' => 'Receipt sent successfully to ' . $checkoutSession->bidder->email,
        ]);
    }

    /**
     * Refund a checkout session.
     */
    public function refund(Request $request, Event $event, CheckoutSession $checkoutSession)
    {
        if (!$checkoutSession->isCompleted()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot refund incomplete checkout.',
            ], 400);
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:255',
        ]);

        DB::beginTransaction();
        try {
            // TODO: Implement Square refund API if it was a card payment
            
            $checkoutSession->update([
                'status' => 'refunded',
                'payment_details' => array_merge($checkoutSession->payment_details ?? [], [
                    'refund_reason' => $validated['reason'],
                    'refunded_at' => now()->toISOString(),
                    'refunded_by' => auth()->id(),
                ]),
            ]);

            // Mark associated bids as unpaid
            if ($checkoutSession->items) {
                foreach ($checkoutSession->items as $item) {
                    if (isset($item['bid_id'])) {
                        \App\Models\Bid::find($item['bid_id'])?->update(['is_paid' => false]);
                    }
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Checkout refunded successfully.',
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to process refund.',
            ], 500);
        }
    }

    /**
     * Simulate Square payment processing (placeholder).
     */
    private function processSquarePaymentAPI(CheckoutSession $checkoutSession, string $sourceId): array
    {
        // This is a placeholder for actual Square API integration
        // In production, this would make actual API calls to Square
        
        return [
            'success' => true,
            'payment_id' => 'sq_payment_' . uniqid(),
            'receipt_url' => 'https://squareup.com/receipt/' . uniqid(),
            'details' => [
                'source_id' => $sourceId,
                'amount' => $checkoutSession->total_amount * 100, // Square uses cents
                'currency' => 'USD',
                'processed_at' => now()->toISOString(),
            ],
        ];
    }
}

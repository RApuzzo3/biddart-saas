<?php

use App\Http\Controllers\TenantController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\BidderController;
use App\Http\Controllers\BidItemController;
use App\Http\Controllers\BidController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\SharedCredentialController;
use App\Models\Event;
use Illuminate\Support\Facades\Route;

// Public routes
Route::get('/', function () {
    return view('welcome');
})->name('home');

// Super Admin routes (for platform management)
Route::middleware(['shared.auth', 'role:super_admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('tenants', TenantController::class);
});

// Tenant-specific routes (multi-tenant middleware will be applied)
Route::middleware(['shared.auth', 'tenant'])->group(function () {
    
    // Tenant dashboard
    Route::get('/dashboard', [TenantController::class, 'dashboard'])->name('dashboard');
    
    // Event management with Livewire dashboard
    Route::resource('events', EventController::class);
    Route::get('/events/{event}/dashboard', function (Event $event) {
        return view('livewire.event-app', compact('event'));
    })->name('events.dashboard');
    Route::get('/events/{event}/realtime-stats', [EventController::class, 'realtimeStats'])->name('events.realtime-stats');
    
    // Bidder management (nested under events)
    Route::resource('events.bidders', BidderController::class)->except(['index', 'show']);
    Route::get('/events/{event}/bidders', [BidderController::class, 'index'])->name('events.bidders.index');
    Route::get('/events/{event}/bidders/{bidder}', [BidderController::class, 'show'])->name('events.bidders.show');
    Route::get('/events/{event}/bidders-search', [BidderController::class, 'search'])->name('events.bidders.search');
    Route::get('/events/{event}/quick-register', function (Event $event) {
        return view('livewire.quick-bidder-registration', compact('event'));
    })->name('events.bidders.quick-register');
    Route::post('/events/{event}/quick-register', [BidderController::class, 'storeQuickRegister'])->name('events.bidders.store-quick-register');
    
    // Bid item management (nested under events)
    Route::resource('events.bid-items', BidItemController::class)->except(['index', 'show']);
    Route::get('/events/{event}/bid-items', [BidItemController::class, 'index'])->name('events.bid-items.index');
    Route::get('/events/{event}/bid-items/{bidItem}', [BidItemController::class, 'show'])->name('events.bid-items.show');
    Route::get('/events/{event}/bid-items-search', [BidItemController::class, 'search'])->name('events.bid-items.search');
    Route::post('/events/{event}/bid-items/{bidItem}/toggle-featured', [BidItemController::class, 'toggleFeatured'])->name('events.bid-items.toggle-featured');
    Route::post('/events/{event}/bid-items/{bidItem}/toggle-active', [BidItemController::class, 'toggleActive'])->name('events.bid-items.toggle-active');
    Route::get('/events/{event}/bid-items/{bidItem}/details', [BidItemController::class, 'details'])->name('events.bid-items.details');
    
    // Bid management
    Route::get('/events/{event}/bids', [BidController::class, 'index'])->name('events.bids.index');
    Route::post('/events/{event}/bids', [BidController::class, 'store'])->name('events.bids.store');
    Route::get('/events/{event}/bids/{bid}', [BidController::class, 'show'])->name('events.bids.show');
    Route::delete('/events/{event}/bids/{bid}', [BidController::class, 'destroy'])->name('events.bids.destroy');
    Route::get('/events/{event}/quick-bid', [BidController::class, 'quickBid'])->name('events.bids.quick-bid');
    Route::get('/events/{event}/bids-recent', [BidController::class, 'recent'])->name('events.bids.recent');
    Route::get('/events/{event}/bids-stats', [BidController::class, 'stats'])->name('events.bids.stats');
    Route::post('/events/{event}/bids-bulk', [BidController::class, 'bulkAction'])->name('events.bids.bulk');
    
    // Checkout management
    Route::get('/events/{event}/checkout', [CheckoutController::class, 'index'])->name('events.checkout.index');
    Route::get('/events/{event}/bidders/{bidder}/checkout', [CheckoutController::class, 'create'])->name('checkout.create');
    Route::post('/events/{event}/bidders/{bidder}/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
    Route::get('/events/{event}/checkout/{checkoutSession}', [CheckoutController::class, 'show'])->name('checkout.show');
    Route::get('/events/{event}/checkout/{checkoutSession}/square', [CheckoutController::class, 'square'])->name('checkout.square');
    Route::post('/events/{event}/checkout/{checkoutSession}/square', [CheckoutController::class, 'processSquarePayment'])->name('checkout.square.process');
    Route::get('/events/{event}/checkout/{checkoutSession}/receipt', [CheckoutController::class, 'receipt'])->name('checkout.receipt');
    Route::post('/events/{event}/checkout/{checkoutSession}/email-receipt', [CheckoutController::class, 'emailReceipt'])->name('checkout.email-receipt');
    Route::post('/events/{event}/checkout/{checkoutSession}/refund', [CheckoutController::class, 'refund'])->name('checkout.refund');
});

// Shared credential login routes (special authentication flow)
Route::prefix('shared')->name('shared.')->group(function () {
    Route::get('/login', [SharedCredentialController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [SharedCredentialController::class, 'login'])->name('login.post');
    Route::get('/verify', [SharedCredentialController::class, 'showVerifyForm'])->name('verify');
    Route::post('/verify', [SharedCredentialController::class, 'verify'])->name('verify.post');
    Route::post('/logout', [SharedCredentialController::class, 'logout'])->name('logout');
});

// Laravel Breeze auth routes
require __DIR__.'/auth.php';

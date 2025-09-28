<?php

use App\Models\Event;
use App\Models\Bidder;
use App\Models\BidItem;
use App\Models\Bid;
use App\Helpers\TenantHelper;
use Livewire\Volt\Component;
use Livewire\Attributes\On;

new class extends Component {
    public Event $event;
    public $activeTab = 'overview';
    public $stats = [];
    public $recentBids = [];
    public $searchQuery = '';
    public $selectedBidder = null;
    public $selectedBidItem = null;
    public $bidAmount = '';
    public $bidType = 'bid';
    
    public function mount(Event $event)
    {
        $this->event = $event;
        $this->loadStats();
        $this->loadRecentBids();
    }
    
    public function loadStats()
    {
        $this->stats = [
            'total_bidders' => $this->event->bidders()->count(),
            'active_bidders' => $this->event->bidders()->where('active', true)->count(),
            'total_bid_items' => $this->event->bidItems()->count(),
            'active_bid_items' => $this->event->bidItems()->where('active', true)->count(),
            'total_bids' => $this->event->bids()->count(),
            'current_bid_total' => $this->event->bids()->sum('amount'),
            'completed_checkouts' => $this->event->checkoutSessions()->where('status', 'completed')->count(),
            'total_revenue' => $this->event->getTotalRevenue(),
            'platform_fees_earned' => $this->event->getPlatformFeesEarned(),
        ];
    }
    
    public function loadRecentBids()
    {
        $this->recentBids = $this->event->bids()
            ->with(['bidder', 'bidItem'])
            ->latest()
            ->take(10)
            ->get()
            ->toArray();
    }
    
    public function setActiveTab($tab)
    {
        $this->activeTab = $tab;
        $this->dispatch('tab-changed', $tab);
    }
    
    public function searchBidders()
    {
        if (strlen($this->searchQuery) < 2) {
            return [];
        }
        
        return $this->event->bidders()
            ->where(function ($query) {
                $query->where('first_name', 'like', "%{$this->searchQuery}%")
                      ->orWhere('last_name', 'like', "%{$this->searchQuery}%")
                      ->orWhere('bidder_number', 'like', "%{$this->searchQuery}%");
            })
            ->take(10)
            ->get();
    }
    
    public function selectBidder($bidderId)
    {
        $this->selectedBidder = $this->event->bidders()->find($bidderId);
        $this->searchQuery = $this->selectedBidder ? $this->selectedBidder->display_name : '';
    }
    
    public function searchBidItems()
    {
        return $this->event->bidItems()
            ->where('active', true)
            ->where('name', 'like', "%{$this->searchQuery}%")
            ->take(10)
            ->get();
    }
    
    public function selectBidItem($itemId)
    {
        $this->selectedBidItem = $this->event->bidItems()->find($itemId);
        
        if ($this->selectedBidItem && $this->bidType === 'bid') {
            $this->bidAmount = $this->selectedBidItem->getNextMinimumBid();
        } elseif ($this->selectedBidItem && $this->bidType === 'buy_now') {
            $this->bidAmount = $this->selectedBidItem->buy_now_price;
        }
    }
    
    public function recordBid()
    {
        $this->validate([
            'selectedBidder' => 'required',
            'selectedBidItem' => 'required',
            'bidAmount' => 'required|numeric|min:0',
            'bidType' => 'required|in:bid,buy_now,raffle_entry,donation',
        ]);
        
        try {
            // Validate bid amount based on type
            if ($this->bidType === 'bid') {
                $minimumBid = $this->selectedBidItem->getNextMinimumBid();
                if ($this->bidAmount < $minimumBid) {
                    $this->addError('bidAmount', "Minimum bid is $" . number_format($minimumBid, 2));
                    return;
                }
            }
            
            // Create the bid
            $bid = Bid::create([
                'tenant_id' => $this->event->tenant_id,
                'event_id' => $this->event->id,
                'bidder_id' => $this->selectedBidder->id,
                'bid_item_id' => $this->selectedBidItem->id,
                'amount' => $this->bidAmount,
                'type' => $this->bidType,
                'created_by' => auth()->id() ?? 1, // Handle shared credentials
            ]);
            
            // Mark as winning bid for auction items
            if (in_array($this->bidType, ['bid', 'buy_now'])) {
                $bid->markAsWinning();
            }
            
            // Reset form
            $this->reset(['selectedBidder', 'selectedBidItem', 'bidAmount', 'searchQuery']);
            $this->bidType = 'bid';
            
            // Refresh data
            $this->loadStats();
            $this->loadRecentBids();
            
            // Broadcast to other users
            $this->dispatch('bid-recorded', [
                'bidder' => $this->selectedBidder->display_name,
                'item' => $this->selectedBidItem->name,
                'amount' => number_format($this->bidAmount, 2),
            ]);
            
            session()->flash('success', 'Bid recorded successfully!');
            
        } catch (\Exception $e) {
            $this->addError('general', 'Failed to record bid. Please try again.');
        }
    }
    
    #[On('refresh-stats')]
    public function refreshStats()
    {
        $this->loadStats();
        $this->loadRecentBids();
    }
}; ?>

<div class="min-h-screen bg-gray-50">
    <!-- Header -->
    <div class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">{{ $event->name }}</h1>
                    <p class="text-gray-600">{{ $event->location }} • {{ $event->start_date->format('M j, Y') }}</p>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="text-right">
                        <div class="text-2xl font-bold text-green-600">${{ number_format($stats['current_bid_total'], 2) }}</div>
                        <div class="text-sm text-gray-500">Total Bids</div>
                    </div>
                    <div class="text-right">
                        <div class="text-2xl font-bold text-blue-600">{{ $stats['total_bids'] }}</div>
                        <div class="text-sm text-gray-500">Bids Placed</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-2 bg-blue-100 rounded-lg">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-semibold text-gray-900">{{ $stats['active_bidders'] }}</p>
                        <p class="text-gray-600">Active Bidders</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-2 bg-green-100 rounded-lg">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-semibold text-gray-900">{{ $stats['active_bid_items'] }}</p>
                        <p class="text-gray-600">Active Items</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-2 bg-yellow-100 rounded-lg">
                        <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-semibold text-gray-900">${{ number_format($stats['total_revenue'], 2) }}</p>
                        <p class="text-gray-600">Revenue</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-2 bg-purple-100 rounded-lg">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-semibold text-gray-900">${{ number_format($stats['platform_fees_earned'], 2) }}</p>
                        <p class="text-gray-600">Platform Fees</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Quick Bid Form -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Quick Bid Entry</h3>
                    </div>
                    <div class="p-6">
                        <form wire:submit="recordBid" class="space-y-6">
                            <!-- Bidder Search -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Bidder</label>
                                <div class="relative">
                                    <input 
                                        type="text" 
                                        wire:model.live="searchQuery"
                                        placeholder="Search by name or bidder number..."
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    >
                                    @if($searchQuery && !$selectedBidder)
                                        <div class="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg max-h-60 overflow-auto">
                                            @foreach($this->searchBidders() as $bidder)
                                                <button 
                                                    type="button"
                                                    wire:click="selectBidder({{ $bidder->id }})"
                                                    class="w-full px-4 py-2 text-left hover:bg-gray-50 focus:bg-gray-50 focus:outline-none"
                                                >
                                                    <div class="font-medium">{{ $bidder->display_name }}</div>
                                                    @if($bidder->email)
                                                        <div class="text-sm text-gray-500">{{ $bidder->email }}</div>
                                                    @endif
                                                </button>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                                @error('selectedBidder') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <!-- Bid Item Search -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Bid Item</label>
                                <div class="relative">
                                    <select 
                                        wire:model.live="selectedBidItem" 
                                        wire:change="selectBidItem($event.target.value)"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    >
                                        <option value="">Select an item...</option>
                                        @foreach($this->searchBidItems() as $item)
                                            <option value="{{ $item->id }}">
                                                {{ $item->name }} - Current: ${{ number_format($item->current_bid, 2) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                @error('selectedBidItem') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <!-- Bid Type and Amount -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Bid Type</label>
                                    <select 
                                        wire:model.live="bidType" 
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    >
                                        <option value="bid">Regular Bid</option>
                                        <option value="buy_now">Buy Now</option>
                                        <option value="raffle_entry">Raffle Entry</option>
                                        <option value="donation">Donation</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Amount</label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <span class="text-gray-500 sm:text-sm">$</span>
                                        </div>
                                        <input 
                                            type="number" 
                                            step="0.01" 
                                            wire:model="bidAmount"
                                            class="w-full pl-8 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                            placeholder="0.00"
                                        >
                                    </div>
                                    @error('bidAmount') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <div>
                                <button 
                                    type="submit" 
                                    class="w-full bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors duration-200"
                                    wire:loading.attr="disabled"
                                    wire:loading.class="opacity-50 cursor-not-allowed"
                                >
                                    <span wire:loading.remove>Record Bid</span>
                                    <span wire:loading>Recording...</span>
                                </button>
                            </div>

                            @error('general') 
                                <div class="p-4 bg-red-50 border border-red-200 rounded-lg">
                                    <p class="text-sm text-red-600">{{ $message }}</p>
                                </div>
                            @enderror
                        </form>
                    </div>
                </div>
            </div>

            <!-- Recent Bids -->
            <div>
                <div class="bg-white rounded-lg shadow">
                    <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                        <h3 class="text-lg font-medium text-gray-900">Recent Bids</h3>
                        <button 
                            wire:click="refreshStats" 
                            class="text-blue-600 hover:text-blue-700 text-sm font-medium"
                            wire:loading.attr="disabled"
                        >
                            <span wire:loading.remove>Refresh</span>
                            <span wire:loading>...</span>
                        </button>
                    </div>
                    <div class="divide-y divide-gray-200 max-h-96 overflow-y-auto">
                        @forelse($recentBids as $bid)
                            <div class="p-4">
                                <div class="flex justify-between items-start">
                                    <div class="flex-1">
                                        <p class="text-sm font-medium text-gray-900">
                                            {{ $bid['bidder']['display_name'] ?? 'Unknown Bidder' }}
                                        </p>
                                        <p class="text-sm text-gray-600">
                                            {{ $bid['bid_item']['name'] ?? 'Unknown Item' }}
                                        </p>
                                        <p class="text-xs text-gray-500">
                                            {{ \Carbon\Carbon::parse($bid['created_at'])->diffForHumans() }}
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm font-semibold text-green-600">
                                            ${{ number_format($bid['amount'], 2) }}
                                        </p>
                                        @if($bid['is_winning'])
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                Winning
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="p-8 text-center text-gray-500">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                                </svg>
                                <p class="mt-2 text-sm">No bids recorded yet</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@script
<script>
    // Auto-refresh stats every 30 seconds
    setInterval(() => {
        $wire.dispatch('refresh-stats');
    }, 30000);

    // Listen for bid recorded events
    $wire.on('bid-recorded', (event) => {
        // Show success notification
        const notification = document.createElement('div');
        notification.className = 'fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50 transform transition-transform duration-300 translate-x-full';
        notification.innerHTML = `
            <div class="flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                <span>Bid recorded: ${event.bidder} - ${event.item} - $${event.amount}</span>
            </div>
        `;
        document.body.appendChild(notification);
        
        // Animate in
        setTimeout(() => {
            notification.classList.remove('translate-x-full');
        }, 100);
        
        // Remove after 5 seconds
        setTimeout(() => {
            notification.classList.add('translate-x-full');
            setTimeout(() => {
                document.body.removeChild(notification);
            }, 300);
        }, 5000);
    });
</script>
@endscript

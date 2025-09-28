<?php

use App\Models\Event;
use App\Models\Bidder;
use App\Models\BidItem;
use App\Models\Bid;
use App\Helpers\TenantHelper;
use Livewire\Volt\Component;

new class extends Component {
    public Event $event;
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
        $this->loadData();
    }
    
    public function loadData()
    {
        $this->stats = [
            'total_bidders' => $this->event->bidders()->count(),
            'total_bids' => $this->event->bids()->count(),
            'current_bid_total' => $this->event->bids()->sum('amount'),
            'total_revenue' => $this->event->getTotalRevenue(),
        ];
        
        $this->recentBids = $this->event->bids()
            ->with(['bidder', 'bidItem'])
            ->latest()
            ->take(10)
            ->get()
            ->toArray();
    }
    
    public function searchBidders()
    {
        if (strlen($this->searchQuery) < 2) return [];
        
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
        return $this->event->bidItems()->where('active', true)->take(20)->get();
    }
    
    public function selectBidItem($itemId)
    {
        $this->selectedBidItem = $this->event->bidItems()->find($itemId);
        if ($this->selectedBidItem && $this->bidType === 'bid') {
            $this->bidAmount = $this->selectedBidItem->getNextMinimumBid();
        }
    }
    
    public function recordBid()
    {
        $this->validate([
            'selectedBidder' => 'required',
            'selectedBidItem' => 'required',
            'bidAmount' => 'required|numeric|min:0',
        ]);
        
        try {
            $bid = Bid::create([
                'tenant_id' => $this->event->tenant_id,
                'event_id' => $this->event->id,
                'bidder_id' => $this->selectedBidder->id,
                'bid_item_id' => $this->selectedBidItem->id,
                'amount' => $this->bidAmount,
                'type' => $this->bidType,
                'created_by' => auth()->id() ?? 1,
            ]);
            
            if (in_array($this->bidType, ['bid', 'buy_now'])) {
                $bid->markAsWinning();
            }
            
            $this->reset(['selectedBidder', 'selectedBidItem', 'bidAmount', 'searchQuery']);
            $this->bidType = 'bid';
            $this->loadData();
            
            session()->flash('success', 'Bid recorded successfully!');
            
        } catch (\Exception $e) {
            $this->addError('general', 'Failed to record bid.');
        }
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

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Quick Bid Form -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow">
                    <div class="px-6 py-4 border-b">
                        <h3 class="text-lg font-medium">Quick Bid Entry</h3>
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
                                        placeholder="Search by name or number..."
                                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
                                    >
                                    @if($searchQuery && !$selectedBidder)
                                        <div class="absolute z-10 w-full mt-1 bg-white border rounded-lg shadow-lg max-h-60 overflow-auto">
                                            @foreach($this->searchBidders() as $bidder)
                                                <button 
                                                    type="button"
                                                    wire:click="selectBidder({{ $bidder->id }})"
                                                    class="w-full px-4 py-2 text-left hover:bg-gray-50"
                                                >
                                                    <div class="font-medium">{{ $bidder->display_name }}</div>
                                                </button>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <!-- Bid Item -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Bid Item</label>
                                <select 
                                    wire:model.live="selectedBidItem" 
                                    wire:change="selectBidItem($event.target.value)"
                                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
                                >
                                    <option value="">Select an item...</option>
                                    @foreach($this->searchBidItems() as $item)
                                        <option value="{{ $item->id }}">
                                            {{ $item->name }} - Current: ${{ number_format($item->current_bid, 2) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Amount -->
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Type</label>
                                    <select wire:model.live="bidType" class="w-full px-4 py-2 border rounded-lg">
                                        <option value="bid">Regular Bid</option>
                                        <option value="buy_now">Buy Now</option>
                                        <option value="raffle_entry">Raffle Entry</option>
                                        <option value="donation">Donation</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Amount</label>
                                    <input 
                                        type="number" 
                                        step="0.01" 
                                        wire:model="bidAmount"
                                        class="w-full px-4 py-2 border rounded-lg"
                                        placeholder="0.00"
                                    >
                                </div>
                            </div>

                            <button 
                                type="submit" 
                                class="w-full bg-blue-600 text-white py-3 px-4 rounded-lg hover:bg-blue-700 text-lg font-medium"
                                wire:loading.attr="disabled"
                            >
                                <span wire:loading.remove>Record Bid</span>
                                <span wire:loading>Recording...</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Recent Bids -->
            <div>
                <div class="bg-white rounded-lg shadow">
                    <div class="px-6 py-4 border-b flex justify-between items-center">
                        <h3 class="text-lg font-medium">Recent Bids</h3>
                        <button wire:click="loadData" class="text-blue-600 hover:text-blue-700 text-sm">
                            Refresh
                        </button>
                    </div>
                    <div class="divide-y max-h-96 overflow-y-auto">
                        @forelse($recentBids as $bid)
                            <div class="p-4">
                                <div class="flex justify-between">
                                    <div>
                                        <p class="font-medium">{{ $bid['bidder']['display_name'] ?? 'Unknown' }}</p>
                                        <p class="text-sm text-gray-600">{{ $bid['bid_item']['name'] ?? 'Unknown Item' }}</p>
                                        <p class="text-xs text-gray-500">{{ \Carbon\Carbon::parse($bid['created_at'])->diffForHumans() }}</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-semibold text-green-600">${{ number_format($bid['amount'], 2) }}</p>
                                        @if($bid['is_winning'])
                                            <span class="text-xs bg-green-100 text-green-800 px-2 py-1 rounded-full">Winning</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="p-8 text-center text-gray-500">
                                <p>No bids recorded yet</p>
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
    // Auto-refresh every 30 seconds
    setInterval(() => {
        $wire.loadData();
    }, 30000);
</script>
@endscript

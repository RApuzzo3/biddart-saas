<?php

use App\Models\Event;
use App\Models\Bidder;
use App\Helpers\TenantHelper;
use Livewire\Volt\Component;

new class extends Component {
    public Event $event;
    public $firstName = '';
    public $lastName = '';
    public $email = '';
    public $phone = '';
    public $showForm = false;
    public $recentBidders = [];
    
    public function mount(Event $event)
    {
        $this->event = $event;
        $this->loadRecentBidders();
    }
    
    public function loadRecentBidders()
    {
        $this->recentBidders = $this->event->bidders()
            ->latest()
            ->take(10)
            ->get()
            ->toArray();
    }
    
    public function toggleForm()
    {
        $this->showForm = !$this->showForm;
        if (!$this->showForm) {
            $this->reset(['firstName', 'lastName', 'email', 'phone']);
        }
    }
    
    public function registerBidder()
    {
        $this->validate([
            'firstName' => 'required|string|max:255',
            'lastName' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
        ]);
        
        try {
            $bidder = Bidder::create([
                'tenant_id' => $this->event->tenant_id,
                'event_id' => $this->event->id,
                'bidder_number' => TenantHelper::generateBidderNumber($this->event),
                'first_name' => $this->firstName,
                'last_name' => $this->lastName,
                'email' => $this->email,
                'phone' => $this->phone,
                'active' => true,
            ]);
            
            // Reset form
            $this->reset(['firstName', 'lastName', 'email', 'phone']);
            $this->showForm = false;
            
            // Refresh recent bidders
            $this->loadRecentBidders();
            
            // Dispatch event
            $this->dispatch('bidder-registered', [
                'bidder' => $bidder->display_name,
                'number' => $bidder->bidder_number,
            ]);
            
            session()->flash('success', "Bidder registered successfully! Bidder Number: {$bidder->bidder_number}");
            
        } catch (\Exception $e) {
            $this->addError('general', 'Failed to register bidder. Please try again.');
        }
    }
}; ?>

<div class="bg-white rounded-lg shadow">
    <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
        <h3 class="text-lg font-medium text-gray-900">Bidder Registration</h3>
        <button 
            wire:click="toggleForm"
            class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors duration-200"
        >
            @if($showForm)
                Cancel
            @else
                + New Bidder
            @endif
        </button>
    </div>
    
    @if($showForm)
        <div class="p-6 border-b border-gray-200">
            <form wire:submit="registerBidder" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">First Name *</label>
                        <input 
                            type="text" 
                            wire:model="firstName"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            placeholder="Enter first name"
                        >
                        @error('firstName') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Last Name *</label>
                        <input 
                            type="text" 
                            wire:model="lastName"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            placeholder="Enter last name"
                        >
                        @error('lastName') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input 
                            type="email" 
                            wire:model="email"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            placeholder="Enter email address"
                        >
                        @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                        <input 
                            type="tel" 
                            wire:model="phone"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            placeholder="Enter phone number"
                        >
                        @error('phone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button 
                        type="button"
                        wire:click="toggleForm"
                        class="px-4 py-2 text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300 focus:ring-2 focus:ring-gray-500 focus:ring-offset-2"
                    >
                        Cancel
                    </button>
                    <button 
                        type="submit" 
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                        wire:loading.attr="disabled"
                        wire:loading.class="opacity-50 cursor-not-allowed"
                    >
                        <span wire:loading.remove>Register Bidder</span>
                        <span wire:loading>Registering...</span>
                    </button>
                </div>
                
                @error('general') 
                    <div class="p-3 bg-red-50 border border-red-200 rounded-lg">
                        <p class="text-sm text-red-600">{{ $message }}</p>
                    </div>
                @enderror
            </form>
        </div>
    @endif
    
    <!-- Recent Bidders List -->
    <div class="divide-y divide-gray-200 max-h-80 overflow-y-auto">
        @forelse($recentBidders as $bidder)
            <div class="p-4 hover:bg-gray-50">
                <div class="flex justify-between items-center">
                    <div>
                        <p class="font-medium text-gray-900">{{ $bidder['display_name'] }}</p>
                        @if($bidder['email'])
                            <p class="text-sm text-gray-600">{{ $bidder['email'] }}</p>
                        @endif
                        @if($bidder['phone'])
                            <p class="text-sm text-gray-600">{{ $bidder['phone'] }}</p>
                        @endif
                    </div>
                    <div class="text-right">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                            #{{ $bidder['bidder_number'] }}
                        </span>
                        @if($bidder['active'])
                            <p class="text-xs text-green-600 mt-1">Active</p>
                        @else
                            <p class="text-xs text-gray-500 mt-1">Inactive</p>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="p-8 text-center text-gray-500">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
                <p class="mt-2 text-sm">No bidders registered yet</p>
                <p class="text-xs text-gray-400">Click "New Bidder" to get started</p>
            </div>
        @endforelse
    </div>
</div>

@script
<script>
    $wire.on('bidder-registered', (event) => {
        // Show success notification
        const notification = document.createElement('div');
        notification.className = 'fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50 transform transition-transform duration-300 translate-x-full';
        notification.innerHTML = `
            <div class="flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                <span>Bidder registered: ${event.bidder} (#${event.number})</span>
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

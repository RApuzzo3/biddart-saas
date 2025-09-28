<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Biddart') }} - Phone Verification</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-gray-50">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            <div>
                <div class="mx-auto h-12 w-12 flex items-center justify-center bg-green-600 rounded-lg">
                    <svg class="h-8 w-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                    </svg>
                </div>
                <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                    Phone Verification
                </h2>
                <p class="mt-2 text-center text-sm text-gray-600">
                    Enter your phone number to receive a verification code
                </p>
            </div>
            
            <div x-data="{ 
                step: 'phone', 
                phone: '', 
                code: '', 
                loading: false,
                alias: '',
                async sendCode() {
                    this.loading = true;
                    try {
                        const response = await fetch('{{ route('shared.verify.post') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                            },
                            body: JSON.stringify({
                                phone_number: this.phone,
                                action: 'send_code'
                            })
                        });
                        const data = await response.json();
                        if (data.success) {
                            this.alias = data.alias_name;
                            this.step = 'verify';
                        } else {
                            alert(data.message || 'Failed to send code');
                        }
                    } catch (error) {
                        alert('Network error. Please try again.');
                    }
                    this.loading = false;
                },
                async verifyCode() {
                    this.loading = true;
                    try {
                        const response = await fetch('{{ route('shared.verify.post') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                            },
                            body: JSON.stringify({
                                phone_number: this.phone,
                                verification_code: this.code,
                                action: 'verify_code'
                            })
                        });
                        const data = await response.json();
                        if (data.success) {
                            window.location.href = data.redirect;
                        } else {
                            alert(data.message || 'Invalid verification code');
                        }
                    } catch (error) {
                        alert('Network error. Please try again.');
                    }
                    this.loading = false;
                }
            }">
                <!-- Phone Number Step -->
                <div x-show="step === 'phone'" class="space-y-6">
                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700">Phone Number</label>
                        <input 
                            type="tel" 
                            x-model="phone"
                            class="mt-1 appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" 
                            placeholder="(555) 123-4567"
                            required
                        >
                    </div>
                    
                    <button 
                        @click="sendCode()"
                        :disabled="loading || !phone"
                        class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <span x-show="!loading">Send Verification Code</span>
                        <span x-show="loading">Sending...</span>
                    </button>
                </div>

                <!-- Verification Code Step -->
                <div x-show="step === 'verify'" class="space-y-6">
                    <div class="text-center">
                        <div class="inline-flex items-center px-4 py-2 bg-blue-50 rounded-lg">
                            <svg class="h-5 w-5 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                            <span class="text-sm text-blue-800">Your alias: <strong x-text="alias"></strong></span>
                        </div>
                        <p class="mt-2 text-sm text-gray-600">
                            Enter the 6-digit code sent to <span x-text="phone" class="font-medium"></span>
                        </p>
                    </div>
                    
                    <div>
                        <label for="code" class="block text-sm font-medium text-gray-700">Verification Code</label>
                        <input 
                            type="text" 
                            x-model="code"
                            maxlength="6"
                            class="mt-1 appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm text-center text-2xl tracking-widest" 
                            placeholder="000000"
                            required
                        >
                    </div>
                    
                    <div class="flex space-x-3">
                        <button 
                            @click="step = 'phone'; code = ''"
                            class="flex-1 py-2 px-4 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                        >
                            Back
                        </button>
                        <button 
                            @click="verifyCode()"
                            :disabled="loading || code.length !== 6"
                            class="flex-1 py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <span x-show="!loading">Verify & Continue</span>
                            <span x-show="loading">Verifying...</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

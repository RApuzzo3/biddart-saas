<?php

namespace App\Http\Controllers;

use App\Models\SharedCredential;
use App\Models\PhoneVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;

class SharedCredentialController extends Controller
{
    /**
     * Show the shared login form.
     */
    public function showLoginForm()
    {
        return view('shared.login');
    }

    /**
     * Process shared credential login.
     */
    public function login(Request $request)
    {
        $validated = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $credential = SharedCredential::where('username', $validated['username'])
            ->where('active', true)
            ->first();

        if (!$credential || !Hash::check($validated['password'], $credential->password)) {
            return back()->withErrors([
                'username' => 'Invalid credentials.',
            ])->withInput($request->only('username'));
        }

        // Store credential in session for phone verification
        Session::put('shared_credential_id', $credential->id);
        Session::put('tenant_id', $credential->tenant_id);
        Session::put('event_id', $credential->event_id);

        return redirect()->route('shared.verify');
    }

    /**
     * Show the phone verification form.
     */
    public function showVerifyForm()
    {
        if (!Session::has('shared_credential_id')) {
            return redirect()->route('shared.login');
        }

        return view('shared.verify');
    }

    /**
     * Process phone verification.
     */
    public function verify(Request $request)
    {
        if (!Session::has('shared_credential_id')) {
            return redirect()->route('shared.login');
        }

        $validated = $request->validate([
            'phone_number' => 'required|string|regex:/^[\+]?[1-9][\d]{0,15}$/',
            'verification_code' => 'nullable|string|size:6',
            'action' => 'required|in:send_code,verify_code',
        ]);

        $credentialId = Session::get('shared_credential_id');
        $tenantId = Session::get('tenant_id');

        if ($validated['action'] === 'send_code') {
            return $this->sendVerificationCode($tenantId, $credentialId, $validated['phone_number']);
        } else {
            return $this->verifyCode($tenantId, $credentialId, $validated['phone_number'], $validated['verification_code']);
        }
    }

    /**
     * Send verification code to phone number.
     */
    private function sendVerificationCode(int $tenantId, int $credentialId, string $phoneNumber)
    {
        // Clean up old verifications for this phone/credential
        PhoneVerification::where('tenant_id', $tenantId)
            ->where('shared_credential_id', $credentialId)
            ->where('phone_number', $phoneNumber)
            ->delete();

        // Create new verification
        $verification = PhoneVerification::createVerification($tenantId, $credentialId, $phoneNumber);

        // TODO: Send SMS using Twilio or other SMS service
        $this->sendSMS($phoneNumber, $verification->verification_code);

        return response()->json([
            'success' => true,
            'message' => 'Verification code sent to ' . $this->formatPhoneNumber($phoneNumber),
            'alias_name' => $verification->alias_name,
        ]);
    }

    /**
     * Verify the code and complete login.
     */
    private function verifyCode(int $tenantId, int $credentialId, string $phoneNumber, string $code)
    {
        $verification = PhoneVerification::where('tenant_id', $tenantId)
            ->where('shared_credential_id', $credentialId)
            ->where('phone_number', $phoneNumber)
            ->where('verification_code', $code)
            ->first();

        if (!$verification) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid verification code.',
            ], 400);
        }

        if ($verification->isExpired()) {
            return response()->json([
                'success' => false,
                'message' => 'Verification code has expired. Please request a new one.',
            ], 400);
        }

        // Mark as verified
        $verification->markAsVerified();

        // Create session for shared credential user
        $sessionId = 'shared_' . uniqid();
        $verification->update(['session_id' => $sessionId]);

        // Store in session
        Session::put('shared_session_id', $sessionId);
        Session::put('shared_user_alias', $verification->alias_name);
        Session::put('shared_user_phone', $verification->formatted_phone);
        Session::forget(['shared_credential_id']); // Clean up temp session data

        return response()->json([
            'success' => true,
            'message' => 'Welcome, ' . $verification->alias_name . '!',
            'redirect' => route('events.dashboard', Session::get('event_id')),
        ]);
    }

    /**
     * Logout shared credential user.
     */
    public function logout()
    {
        $sessionId = Session::get('shared_session_id');
        
        if ($sessionId) {
            // Invalidate the phone verification session
            PhoneVerification::where('session_id', $sessionId)->update(['session_id' => null]);
        }

        // Clear all shared session data
        Session::forget([
            'shared_session_id',
            'shared_user_alias',
            'shared_user_phone',
            'tenant_id',
            'event_id',
        ]);

        return redirect()->route('shared.login')->with('success', 'Logged out successfully.');
    }

    /**
     * Send SMS verification code (placeholder).
     */
    private function sendSMS(string $phoneNumber, string $code): bool
    {
        // TODO: Implement actual SMS sending using Twilio
        // For now, we'll just log it or return true
        
        \Log::info("SMS Code for {$phoneNumber}: {$code}");
        
        // In production, this would be:
        // $twilio = new Client($sid, $token);
        // $twilio->messages->create($phoneNumber, [
        //     'from' => config('services.twilio.phone'),
        //     'body' => "Your Biddart verification code is: {$code}"
        // ]);
        
        return true;
    }

    /**
     * Format phone number for display.
     */
    private function formatPhoneNumber(string $phone): string
    {
        $phone = preg_replace('/\D/', '', $phone);
        
        if (strlen($phone) === 10) {
            return sprintf('(%s) %s-%s',
                substr($phone, 0, 3),
                substr($phone, 3, 3),
                substr($phone, 6, 4)
            );
        }
        
        return $phone;
    }

    /**
     * Check if user is authenticated via shared credentials.
     */
    public static function isSharedAuthenticated(): bool
    {
        return Session::has('shared_session_id') && Session::has('shared_user_alias');
    }

    /**
     * Get current shared user info.
     */
    public static function getSharedUser(): ?array
    {
        if (!self::isSharedAuthenticated()) {
            return null;
        }

        return [
            'alias' => Session::get('shared_user_alias'),
            'phone' => Session::get('shared_user_phone'),
            'session_id' => Session::get('shared_session_id'),
            'tenant_id' => Session::get('tenant_id'),
            'event_id' => Session::get('event_id'),
        ];
    }
}

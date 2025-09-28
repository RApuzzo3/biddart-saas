<?php

namespace App\Http\Controllers;

use App\Helpers\TenantHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TenantConfigController extends Controller
{
    /**
     * Show tenant configuration page.
     */
    public function index()
    {
        $tenant = TenantHelper::current();
        
        return view('tenant.config.index', compact('tenant'));
    }

    /**
     * Update Square configuration.
     */
    public function updateSquareConfig(Request $request)
    {
        $tenant = TenantHelper::current();

        $validator = Validator::make($request->all(), [
            'square_application_id' => 'required|string|max:255',
            'square_access_token' => 'required|string|max:500',
            'square_environment' => 'required|in:sandbox,production',
            'square_webhook_signature_key' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Test Square credentials by making a simple API call
            $testResult = $this->testSquareCredentials(
                $request->square_access_token,
                $request->square_environment
            );

            if (!$testResult['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid Square credentials: ' . $testResult['error'],
                ], 400);
            }

            // Update tenant configuration
            $tenant->setSquareCredentials(
                $request->square_access_token,
                $request->square_application_id,
                $request->square_environment
            );

            // Update webhook signature key if provided
            if ($request->square_webhook_signature_key) {
                $config = $tenant->config ?? [];
                $config['square_webhook_signature_key'] = $request->square_webhook_signature_key;
                $tenant->update(['config' => $config]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Square configuration updated successfully!',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update Square configuration.',
            ], 500);
        }
    }

    /**
     * Test Square credentials.
     */
    private function testSquareCredentials(string $accessToken, string $environment): array
    {
        try {
            $client = new \Square\SquareClient([
                'accessToken' => $accessToken,
                'environment' => $environment,
            ]);

            // Test with a simple locations API call
            $locationsApi = $client->getLocationsApi();
            $response = $locationsApi->listLocations();

            if ($response->isSuccess()) {
                return ['success' => true];
            } else {
                $errors = $response->getErrors();
                return [
                    'success' => false,
                    'error' => !empty($errors) ? $errors[0]->getDetail() : 'Invalid credentials',
                ];
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Connection failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Update general tenant settings.
     */
    public function updateGeneralSettings(Request $request)
    {
        $tenant = TenantHelper::current();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'theme_color' => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'logo_url' => 'nullable|url|max:500',
            'transaction_fee_percentage' => 'nullable|numeric|min:0|max:10',
            'fixed_transaction_fee' => 'nullable|numeric|min:0|max:5',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $config = $tenant->config ?? [];
            
            // Update general settings
            $tenant->update([
                'name' => $request->name,
            ]);

            // Update configuration
            if ($request->has('contact_email')) {
                $config['contact_email'] = $request->contact_email;
            }
            if ($request->has('theme_color')) {
                $config['theme_color'] = $request->theme_color;
            }
            if ($request->has('logo_url')) {
                $config['logo_url'] = $request->logo_url;
            }
            if ($request->has('transaction_fee_percentage')) {
                $config['transaction_fee_percentage'] = $request->transaction_fee_percentage;
            }
            if ($request->has('fixed_transaction_fee')) {
                $config['fixed_transaction_fee'] = $request->fixed_transaction_fee;
            }

            $tenant->update(['config' => $config]);

            return response()->json([
                'success' => true,
                'message' => 'Settings updated successfully!',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update settings.',
            ], 500);
        }
    }

    /**
     * Get Square connection status.
     */
    public function getSquareStatus()
    {
        $tenant = TenantHelper::current();

        return response()->json([
            'configured' => $tenant->hasSquareConfigured(),
            'environment' => $tenant->getSquareEnvironment(),
            'application_id' => $tenant->getSquareApplicationId() ? 
                substr($tenant->getSquareApplicationId(), 0, 8) . '...' : null,
        ]);
    }
}

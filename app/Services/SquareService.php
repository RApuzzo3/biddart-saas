<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\CheckoutSession;
use Square\SquareClient;
use Square\Models\CreatePaymentRequest;
use Square\Models\Money;
use Square\Exceptions\ApiException;
use Illuminate\Support\Facades\Log;

class SquareService
{
    private SquareClient $client;
    private Tenant $tenant;

    public function __construct(Tenant $tenant)
    {
        $this->tenant = $tenant;
        $this->initializeClient();
    }

    /**
     * Initialize Square client with tenant-specific credentials
     */
    private function initializeClient(): void
    {
        $accessToken = $this->tenant->getSquareAccessToken();
        $environment = $this->tenant->getSquareEnvironment();

        if (!$accessToken) {
            throw new \Exception('Square access token not configured for tenant: ' . $this->tenant->name);
        }

        $this->client = new SquareClient([
            'accessToken' => $accessToken,
            'environment' => $environment,
        ]);
    }

    /**
     * Process a payment through Square
     */
    public function processPayment(CheckoutSession $checkoutSession, string $sourceId, array $options = []): array
    {
        try {
            $paymentsApi = $this->client->getPaymentsApi();

            // Convert amount to cents (Square requires amounts in smallest currency unit)
            $amountInCents = (int) round($checkoutSession->total_amount * 100);

            // Create payment request
            $amountMoney = new Money();
            $amountMoney->setAmount($amountInCents);
            $amountMoney->setCurrency('USD');

            $createPaymentRequest = new CreatePaymentRequest($sourceId, uniqid('biddart_'));
            $createPaymentRequest->setAmountMoney($amountMoney);
            
            // Add order reference
            $createPaymentRequest->setNote("Biddart Event: {$checkoutSession->event->name} - Bidder: {$checkoutSession->bidder->display_name}");
            
            // Add reference ID for tracking
            $createPaymentRequest->setReferenceId($checkoutSession->session_id);

            // Add buyer email if available
            if ($checkoutSession->bidder->email) {
                $createPaymentRequest->setBuyerEmailAddress($checkoutSession->bidder->email);
            }

            // Process the payment
            $response = $paymentsApi->createPayment($createPaymentRequest);

            if ($response->isSuccess()) {
                $payment = $response->getResult()->getPayment();
                
                return [
                    'success' => true,
                    'payment_id' => $payment->getId(),
                    'receipt_number' => $payment->getReceiptNumber(),
                    'receipt_url' => $payment->getReceiptUrl(),
                    'status' => $payment->getStatus(),
                    'amount_paid' => $payment->getAmountMoney()->getAmount() / 100, // Convert back to dollars
                    'currency' => $payment->getAmountMoney()->getCurrency(),
                    'created_at' => $payment->getCreatedAt(),
                    'details' => [
                        'source_type' => $payment->getSourceType(),
                        'card_details' => $payment->getCardDetails() ? [
                            'brand' => $payment->getCardDetails()->getCard()->getCardBrand(),
                            'last_4' => $payment->getCardDetails()->getCard()->getLast4(),
                            'exp_month' => $payment->getCardDetails()->getCard()->getExpMonth(),
                            'exp_year' => $payment->getCardDetails()->getCard()->getExpYear(),
                        ] : null,
                        'processing_fee' => $payment->getProcessingFee() ? $payment->getProcessingFee()[0]->getAmountMoney()->getAmount() / 100 : null,
                    ],
                ];
            } else {
                $errors = $response->getErrors();
                $errorMessage = 'Payment failed';
                
                if (!empty($errors)) {
                    $errorMessage = $errors[0]->getDetail() ?? $errorMessage;
                }

                Log::error('Square payment failed', [
                    'tenant_id' => $this->tenant->id,
                    'checkout_session_id' => $checkoutSession->id,
                    'errors' => $errors,
                ]);

                return [
                    'success' => false,
                    'error' => $errorMessage,
                    'error_code' => !empty($errors) ? $errors[0]->getCode() : null,
                ];
            }

        } catch (ApiException $e) {
            Log::error('Square API exception', [
                'tenant_id' => $this->tenant->id,
                'checkout_session_id' => $checkoutSession->id,
                'message' => $e->getMessage(),
                'response_body' => $e->getResponseBody(),
            ]);

            return [
                'success' => false,
                'error' => 'Payment processing failed. Please try again.',
                'error_code' => $e->getCode(),
            ];

        } catch (\Exception $e) {
            Log::error('Square payment exception', [
                'tenant_id' => $this->tenant->id,
                'checkout_session_id' => $checkoutSession->id,
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Payment processing failed. Please try again.',
            ];
        }
    }

    /**
     * Refund a payment
     */
    public function refundPayment(string $paymentId, int $amountInCents, string $reason = ''): array
    {
        try {
            $refundsApi = $this->client->getRefundsApi();

            $amountMoney = new Money();
            $amountMoney->setAmount($amountInCents);
            $amountMoney->setCurrency('USD');

            $createRefundRequest = new \Square\Models\RefundPaymentRequest(
                uniqid('refund_'),
                $amountMoney,
                $paymentId
            );

            if ($reason) {
                $createRefundRequest->setReason($reason);
            }

            $response = $refundsApi->refundPayment($createRefundRequest);

            if ($response->isSuccess()) {
                $refund = $response->getResult()->getRefund();
                
                return [
                    'success' => true,
                    'refund_id' => $refund->getId(),
                    'status' => $refund->getStatus(),
                    'amount_refunded' => $refund->getAmountMoney()->getAmount() / 100,
                    'created_at' => $refund->getCreatedAt(),
                ];
            } else {
                $errors = $response->getErrors();
                $errorMessage = 'Refund failed';
                
                if (!empty($errors)) {
                    $errorMessage = $errors[0]->getDetail() ?? $errorMessage;
                }

                return [
                    'success' => false,
                    'error' => $errorMessage,
                ];
            }

        } catch (ApiException $e) {
            Log::error('Square refund API exception', [
                'tenant_id' => $this->tenant->id,
                'payment_id' => $paymentId,
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Refund processing failed. Please try again.',
            ];

        } catch (\Exception $e) {
            Log::error('Square refund exception', [
                'tenant_id' => $this->tenant->id,
                'payment_id' => $paymentId,
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Refund processing failed. Please try again.',
            ];
        }
    }

    /**
     * Get payment details
     */
    public function getPayment(string $paymentId): array
    {
        try {
            $paymentsApi = $this->client->getPaymentsApi();
            $response = $paymentsApi->getPayment($paymentId);

            if ($response->isSuccess()) {
                $payment = $response->getResult()->getPayment();
                
                return [
                    'success' => true,
                    'payment' => [
                        'id' => $payment->getId(),
                        'status' => $payment->getStatus(),
                        'amount' => $payment->getAmountMoney()->getAmount() / 100,
                        'currency' => $payment->getAmountMoney()->getCurrency(),
                        'receipt_number' => $payment->getReceiptNumber(),
                        'receipt_url' => $payment->getReceiptUrl(),
                        'created_at' => $payment->getCreatedAt(),
                    ],
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Payment not found',
                ];
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to retrieve payment details',
            ];
        }
    }

    /**
     * Verify webhook signature (for webhook endpoints)
     */
    public static function verifyWebhookSignature(string $body, string $signature, string $signatureKey): bool
    {
        $expectedSignature = base64_encode(hash_hmac('sha1', $body, $signatureKey, true));
        return hash_equals($expectedSignature, $signature);
    }
}

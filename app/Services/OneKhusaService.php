<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Thin wrapper around the OneKhusa Payment Gateway API.
 *
 * Supports two flows:
 *  - Hosted Checkout  (/checkout/rtp/initiate) — redirect the customer to a
 *    managed checkout page.
 *  - Request To Pay   (/collections/requestToPay/initiate) — generate a Timed
 *    Account Number (TAN) the customer uses to pay via bank or MNO.
 */
class OneKhusaService
{
    /**
     * Access tokens live for 5 minutes; cache them for 4 to stay safe.
     */
    private const TOKEN_CACHE_TTL = 240;

    /**
     * Obtain a valid access token (JWT) used as the `Authorization: Bearer`
     * header on every authenticated API request.
     */
    public function getAccessToken(): string
    {
        return Cache::remember('onekhusa_access_token', self::TOKEN_CACHE_TTL, function () {
            $response = Http::withHeaders(['Accept-Language' => 'en'])
                ->post(config('onekhusa.endpoints.get_access_token'), [
                    'apiKey' => config('onekhusa.api_key'),
                    'apiSecret' => config('onekhusa.api_secret'),
                    'organisationId' => config('onekhusa.organisation_id'),
                    'merchantAccountNumber' => config('onekhusa.merchant_account_number'),
                ]);

            $this->throwIfFailed($response, 'token');

            $token = data_get($response->json(), 'accessToken');

            throw_if(empty($token), RuntimeException::class, 'OneKhusa token error: no accessToken returned.');

            return $token;
        });
    }

    /**
     * Hosted Checkout — initiate a payment request.
     *
     * POST /checkout/rtp/initiate
     * Returns the initiation response containing `paymentTransactionId`, which
     * is used to redirect the customer to the hosted checkout page.
     */
    public function initiateCheckout(float $amount, string $reference, ?string $description = null): array
    {
        $callbackBase = config('onekhusa.public_callback_url');

        $payload = [
            'authentication' => $this->authentication(),
            'merchant' => $this->merchant(),
            'payment' => [
                'sourceReferenceNumber' => $reference,
                'description' => $description ?: 'OneTicket Laravel Purchase',
                'amount' => $amount,
            ],
            'route' => [
                'successRedirectionUrl' => "{$callbackBase}/?ref={$reference}",
                'failureRedirectionUrl' => "{$callbackBase}/?ref={$reference}&failed=1",
                'callbackApiUrl' => "{$callbackBase}/api/webhooks/payments",
            ],
        ];

        return $this->post(config('onekhusa.endpoints.checkout_initiate'), $payload, 'checkout');
    }

    /**
     * Hosted Checkout — build the redirect URL for a given payment transaction.
     */
    public function checkoutRedirectUrl(string $paymentTransactionId): string
    {
        return config('onekhusa.endpoints.checkout_redirect')
            . '?ptid=' . urlencode($paymentTransactionId);
    }

    /**
     * Request To Pay — initiate a transaction and generate a TAN.
     *
     * POST /collections/requestToPay/initiate
     * The returned `timedAccountNumber` (TAN) expires after 15 minutes.
     */
    public function requestToPayInitiate(
        float $amount,
        string $reference,
        string $description,
        ?string $capturedBy = null
    ): array {
        $payload = [
            'merchantAccountNumber' => config('onekhusa.merchant_account_number'),
            'transactionAmount' => $amount,
            'transactionDescription' => $description,
            'referenceNumber' => $reference,
            'capturedBy' => $capturedBy ?: config('onekhusa.captured_by'),
        ];

        return $this->post(config('onekhusa.endpoints.rtp_initiate'), $payload, 'request-to-pay');
    }

    /**
     * Credentials block required by the hosted checkout endpoints.
     */
    private function authentication(): array
    {
        return [
            'apiKey' => config('onekhusa.api_key'),
            'apiSecret' => config('onekhusa.api_secret'),
        ];
    }

    /**
     * Merchant block required by the hosted checkout endpoints.
     */
    private function merchant(): array
    {
        return [
            'organisationId' => config('onekhusa.organisation_id'),
            'merchantAccountNumber' => config('onekhusa.merchant_account_number'),
        ];
    }

    /**
     * Send an authenticated POST request and return the decoded JSON payload.
     */
    private function post(string $url, array $payload, string $context): array
    {
        $response = Http::withHeaders($this->headers())
            ->withHeaders(['X-Idempotency-Key' => 'PHP-' . $context . '-' . Str::random(8)])
            ->post($url, $payload);

        $this->throwIfFailed($response, $context);

        return $response->json() ?? [];
    }

    /**
     * Throw a descriptive exception when an API request fails.
     */
    private function throwIfFailed($response, string $context): void
    {
        throw_if($response->failed(), RuntimeException::class, "OneKhusa {$context} error: {$response->body()}");
    }

    /**
     * Common headers shared by every authenticated request.
     */
    private function headers(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->getAccessToken(),
            'Accept-Language' => 'en',
        ];
    }
}
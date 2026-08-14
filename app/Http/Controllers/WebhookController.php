<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    /**
     * Handle payment webhooks from OneKhusa.
     *
     * Events handled:
     *  - payrequest.success -> marks the transaction as Paid
     *
     * Must ALWAYS respond with HTTP 200-OK as acknowledgement of payload receipt.
     */
    public function handle(Request $request)
    {
        $payload = $request->all();

        Log::info("OneKhusa webhook received", $payload);

        // Resolve our reference number. OneKhusa may nest it in metaData with
        // TitleCase keys, or expose it at the top level (event payloads use
        // referenceNumber).
        $myRef = $payload['metaData']['ReferenceNumber'] ??
                 $payload['metaData']['referenceNumber'] ??
                 $payload['sourceReferenceNumber'] ??
                 $payload['referenceNumber'] ??
                 $payload['payment']['sourceReferenceNumber'] ??
                 null;

        if (!$myRef) {
            // Cannot correlate this payload to a known transaction, but still
            // acknowledge so OneKhusa does not retry forever.
            Log::warning("OneKhusa webhook without resolvable reference", $payload);
            return $this->ack();
        }

        $event = $this->resolveEvent($payload);

        if ($this->isSuccess($payload, $event)) {
            $this->markAsPaid($myRef, $payload);
        } else {
            $this->markAsFailed($myRef, $payload);
        }

        return $this->ack();
    }

    /**
     * Resolve the webhook event name from common payload fields.
     * e.g. "payrequest.success".
     */
    protected function resolveEvent(array $payload): string
    {
        return strtolower((string) ($payload['eventType']
            ?? $payload['event']
            ?? $payload['type']
            ?? $payload['eventName']
            ?? $payload['notificationType']
            ?? ''));
    }

    /**
     * Detect a successful payment payload.
     */
    protected function isSuccess(array $payload, string $event = ''): bool
    {
        return str_contains($event, 'success')
            || $payload['responseCode'] === "S100"
            || strtoupper((string) ($payload['transactionStatusCode'] ?? '')) === "S"
            || str_starts_with((string) ($payload['responseCode'] ?? ''), 'S');
    }

    protected function markAsPaid($ref, array $payload): void
    {
        Cache::put("status_$ref", "Paid", 1800);
        Log::info("OneKhusa: status updated to PAID for " . $ref, $payload);
    }

    protected function markAsFailed($ref, array $payload): void
    {
        Cache::put("status_$ref", "Failed", 1800);
        Log::info("OneKhusa: status updated to FAILED for " . $ref, $payload);
    }

    /**
     * Acknowledge receipt of the webhook payload (HTTP 200-OK).
     */
    protected function ack()
    {
        return response("acknowledged", 200)->header('Content-Type', 'text/plain');
    }
}
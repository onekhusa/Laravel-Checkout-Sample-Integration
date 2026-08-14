<?php

namespace App\Http\Controllers;

use App\Services\OneKhusaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class TicketController extends Controller
{
    private const DEFAULT_AMOUNT = 2500;
    private const STATUS_TTL = 1800;

    /**
     * Initiate a hosted checkout payment and return the redirect URL for the
     * OneKhusa managed checkout page.
     */
    public function buy(Request $request, OneKhusaService $oneKhusa): JsonResponse
    {
        $reference = $request->input('reference', 'OT-PHP' . time());
        $amount = (float) $request->input('amount', self::DEFAULT_AMOUNT);
        $description = $request->input('description', 'OneTicket Laravel Purchase');

        try {
            $data = $oneKhusa->initiateCheckout($amount, $reference, $description);

            // Persist the pending status so the frontend can poll for the result.
            Cache::put("status_{$reference}", 'Pending', self::STATUS_TTL);

            return response()->json([
                'status' => 'success',
                'reference' => $reference,
                'paymentTransactionId' => $data['paymentTransactionId'],
                'redirectUrl' => $oneKhusa->checkoutRedirectUrl($data['paymentTransactionId']),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Return the recorded status for a reference.
     * Statuses: Pending | Paid | Failed | NotFound
     */
    public function status(string $reference): JsonResponse
    {
        return response()->json([
            'status' => Cache::get("status_{$reference}", 'NotFound'),
        ]);
    }
}
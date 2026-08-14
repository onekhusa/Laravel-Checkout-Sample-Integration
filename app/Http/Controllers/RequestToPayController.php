<?php

namespace App\Http\Controllers;

use App\Services\OneKhusaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class RequestToPayController extends Controller
{
    private const STATUS_TTL = 1800;

    /**
     * Show the Request To Pay page where a reference number can be entered.
     */
    public function show()
    {
        return view('rtp');
    }

    /**
     * Initiate a Request To Pay (collections) transaction and return the
     * generated Timed Account Number (TAN).
     *
     * POST /api/requestToPay/initiate
     */
    public function initiate(Request $request, OneKhusaService $oneKhusa): JsonResponse
    {
        $validated = $request->validate([
            'reference' => ['required', 'string', 'min:5', 'max:25', 'regex:/^[A-Za-z0-9]+$/'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['required', 'string', 'max:255'],
            'capturedBy' => ['nullable', 'email'],
        ]);

        $reference = $validated['reference'];

        try {
            $data = $oneKhusa->requestToPayInitiate(
                (float) $validated['amount'],
                $reference,
                $validated['description'],
                $validated['capturedBy'] ?? null,
            );

            Cache::put("status_{$reference}", 'Pending', self::STATUS_TTL);

            return response()->json([
                'status' => 'success',
                'reference' => $reference,
                'timedAccountNumber' => $data['timedAccountNumber'] ?? null,
                'expiryDate' => $data['expiryDate'] ?? null,
                'expiryInMinutes' => $data['expiryInMinutes'] ?? 15,
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

<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RequestToPayController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\WebhookController;

// Hosted checkout flow
Route::post('Tickets/buy/{eventId}', [TicketController::class, 'buy']);
Route::get('Tickets/status/{reference}', [TicketController::class, 'status']);

// Request To Pay (collections) — TAN generation + status polling
Route::post('requestToPay/initiate', [RequestToPayController::class, 'initiate']);
Route::get('requestToPay/status/{reference}', [RequestToPayController::class, 'status']);

// OneKhusa webhook (payrequest.success)
Route::post('webhooks/payments', [WebhookController::class, 'handle']);
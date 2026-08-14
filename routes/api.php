<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\WebhookController;

// Hosted checkout flow
Route::post('Tickets/buy/{eventId}', [TicketController::class, 'buy']);
Route::get('Tickets/status/{reference}', [TicketController::class, 'status']);

// OneKhusa webhook (payrequest.success)
Route::post('webhooks/payments', [WebhookController::class, 'handle']);
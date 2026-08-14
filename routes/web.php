<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RequestToPayController;

Route::get('/', function () {
    return view('welcome');
});

// Request To Pay - page for entering a reference number and generating a TAN
Route::get('/rtp', [RequestToPayController::class, 'show']);
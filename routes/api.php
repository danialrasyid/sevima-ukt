<?php

use App\Http\Controllers\Webhook\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

// Tanpa sesi/CSRF: diautentikasi lewat signature HMAC per institusi (header X-Signature).
// URL lengkap: POST /api/webhooks/payment
Route::post('/webhooks/payment', PaymentWebhookController::class)
    ->middleware('throttle:300,1')
    ->name('webhooks.payment');

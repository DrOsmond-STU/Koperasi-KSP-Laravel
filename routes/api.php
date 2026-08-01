<?php

use App\Http\Controllers\Api\XenditWebhookController;
use Illuminate\Support\Facades\Route;

/**
 * Server-to-server callbacks only — no session/CSRF, verified via a shared
 * token instead (App\Http\Controllers\Api\XenditWebhookController).
 */
Route::post('/webhooks/xendit', [XenditWebhookController::class, 'handle'])->name('api.webhooks.xendit');

<?php

use AhmadChebbo\AreebaPayment\Http\Controllers\WebhookController;
use AhmadChebbo\AreebaPayment\Http\Middleware\VerifyNotificationSecret;
use Illuminate\Support\Facades\Route;

// Server-to-server notifications. Registered without the `web` group so there is no
// session or CSRF check; authenticity comes from the notification secret.
Route::post(config('areeba.webhook.path'), WebhookController::class)
    ->middleware(VerifyNotificationSecret::class)
    ->name('areeba.webhook');

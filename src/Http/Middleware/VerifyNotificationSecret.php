<?php

declare(strict_types=1);

namespace AhmadChebbo\AreebaPayment\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * The gateway authenticates each notification by sending the merchant's notification
 * secret in X-Notification-Secret. Fails closed: with no secret configured, nothing passes.
 */
class VerifyNotificationSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('areeba.webhook.secret');

        if ($secret === '') {
            Log::warning('Areeba webhook rejected: AREEBA_WEBHOOK_SECRET is not set.');

            abort(403);
        }

        if (! hash_equals($secret, (string) $request->header('X-Notification-Secret'))) {
            abort(403);
        }

        return $next($request);
    }
}

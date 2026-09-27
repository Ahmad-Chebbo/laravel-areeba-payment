<?php

declare(strict_types=1);

namespace AhmadChebbo\AreebaPayment\Http\Controllers;

use AhmadChebbo\AreebaPayment\Data\Notification;
use AhmadChebbo\AreebaPayment\Events\NotificationEvent;
use AhmadChebbo\AreebaPayment\Events\NotificationReceived;
use AhmadChebbo\AreebaPayment\Events\PaymentFailed;
use AhmadChebbo\AreebaPayment\Events\PaymentRefunded;
use AhmadChebbo\AreebaPayment\Events\PaymentSucceeded;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

class WebhookController
{
    /** How long a notification ID is remembered; the gateway stops retrying well before this. */
    private const DEDUPLICATION_HOURS = 72;

    public function __invoke(Request $request, Dispatcher $events, Cache $cache): Response
    {
        $notification = Notification::fromPayload(
            $request->json()->all(),
            $request->header('X-Notification-Id'),
            (int) $request->header('X-Notification-Attempt', '1'),
        );

        $key = $notification->id ? "areeba:notification:{$notification->id}" : null;

        // A retry of a notification already handled: acknowledge it so the gateway stops sending it.
        if ($key && ! $cache->add($key, true, now()->addHours(self::DEDUPLICATION_HOURS))) {
            return new Response(status: 200);
        }

        try {
            $events->dispatch(new NotificationReceived($notification));

            if ($outcome = $this->outcomeEvent($notification)) {
                $events->dispatch($outcome);
            }
        } catch (Throwable $e) {
            // Let the gateway's retry reach the listeners again.
            if ($key) {
                $cache->forget($key);
            }

            throw $e;
        }

        return new Response(status: 200);
    }

    private function outcomeEvent(Notification $notification): ?NotificationEvent
    {
        return match (true) {
            $notification->isRefund() => new PaymentRefunded($notification),
            $notification->isPaymentSuccess() => new PaymentSucceeded($notification),
            $notification->isPaymentFailure() => new PaymentFailed($notification),
            default => null,
        };
    }
}

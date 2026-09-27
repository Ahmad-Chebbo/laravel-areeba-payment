<?php

declare(strict_types=1);

namespace AhmadChebbo\AreebaPayment\Tests;

use AhmadChebbo\AreebaPayment\Events\NotificationReceived;
use AhmadChebbo\AreebaPayment\Events\PaymentFailed;
use AhmadChebbo\AreebaPayment\Events\PaymentRefunded;
use AhmadChebbo\AreebaPayment\Events\PaymentSucceeded;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use RuntimeException;

class WebhookTest extends TestCase
{
    private function notification(string $type = 'PAYMENT', string $result = 'SUCCESS', string $status = 'CAPTURED'): array
    {
        return [
            'result' => $result,
            'order' => ['id' => '1001', 'status' => $status, 'amount' => 25.5, 'currency' => 'USD'],
            'response' => ['gatewayCode' => $result === 'SUCCESS' ? 'APPROVED' : 'DECLINED'],
            'transaction' => ['id' => 'txn-1', 'type' => $type, 'amount' => 25.5, 'currency' => 'USD'],
        ];
    }

    private function postNotification(array $payload, array $headers = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/areeba/webhook', $payload, $headers + [
            'X-Notification-Secret' => 'notification-secret',
            'X-Notification-Id' => 'notif-1',
            'X-Notification-Attempt' => '1',
        ]);
    }

    public function test_a_successful_payment_dispatches_payment_succeeded(): void
    {
        Event::fake();

        $this->postNotification($this->notification())->assertOk();

        Event::assertDispatched(NotificationReceived::class, fn ($e) => $e->notification->id === 'notif-1');
        Event::assertDispatched(PaymentSucceeded::class, fn ($e) => $e->notification->order->id === '1001'
            && $e->notification->order->isPaid()
            && $e->notification->transaction->transactionId === 'txn-1');
        Event::assertNotDispatched(PaymentFailed::class);
    }

    public function test_a_declined_payment_dispatches_payment_failed(): void
    {
        Event::fake();

        $this->postNotification($this->notification(result: 'FAILURE', status: 'FAILED'))->assertOk();

        Event::assertDispatched(PaymentFailed::class);
        Event::assertNotDispatched(PaymentSucceeded::class);
    }

    public function test_a_refund_dispatches_payment_refunded(): void
    {
        Event::fake();

        $this->postNotification($this->notification(type: 'REFUND', status: 'REFUNDED'))->assertOk();

        Event::assertDispatched(PaymentRefunded::class);
        Event::assertNotDispatched(PaymentSucceeded::class);
    }

    public function test_a_wrong_secret_is_rejected(): void
    {
        Event::fake();

        $this->postNotification($this->notification(), ['X-Notification-Secret' => 'guess'])->assertForbidden();

        Event::assertNothingDispatched();
    }

    public function test_a_missing_secret_header_is_rejected(): void
    {
        Event::fake();

        $this->postJson('/areeba/webhook', $this->notification())->assertForbidden();

        Event::assertNothingDispatched();
    }

    public function test_notifications_are_rejected_when_no_secret_is_configured(): void
    {
        config(['areeba.webhook.secret' => null]);
        Event::fake();

        $this->postNotification($this->notification(), ['X-Notification-Secret' => ''])->assertForbidden();

        Event::assertNothingDispatched();
    }

    public function test_a_redelivered_notification_is_acknowledged_but_handled_once(): void
    {
        Event::fake();

        $this->postNotification($this->notification())->assertOk();
        $this->postNotification($this->notification(), ['X-Notification-Attempt' => '2'])->assertOk();

        Event::assertDispatchedTimes(PaymentSucceeded::class, 1);
    }

    public function test_a_failing_listener_lets_the_gateway_retry_the_notification(): void
    {
        $this->withoutExceptionHandling();
        Event::listen(PaymentSucceeded::class, function () {
            static $calls = 0;

            if (++$calls === 1) {
                throw new RuntimeException('database down');
            }
        });

        try {
            $this->postNotification($this->notification());
            $this->fail('Expected the listener exception.');
        } catch (RuntimeException) {
        }

        $this->postNotification($this->notification(), ['X-Notification-Attempt' => '2'])->assertOk();
    }

    public function test_the_webhook_route_has_no_session_or_csrf_middleware(): void
    {
        $middleware = Route::getRoutes()->getByName('areeba.webhook')->gatherMiddleware();

        $this->assertNotContains('web', $middleware);
    }
}

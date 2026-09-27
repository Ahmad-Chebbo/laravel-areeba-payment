# Laravel Areeba Payment Gateway

A small Laravel package for [Areeba](https://www.areeba.com) online payments. Areeba runs on the Mastercard Gateway; this package uses its REST API (version 100).

It covers hosted checkout, payment verification, refunds, captures, stored cards and webhook notifications. The package creates **no tables, models or pages**. Your application keeps its own orders, the package returns data, and it fires events when the gateway reports a change.

> **Areeba Iraq is not supported yet.** This package works with Areeba's ePayment gateway (`epayment.areeba.com`). Areeba Iraq uses a different platform (IXOPAY, `gateway.areebapayment.com`) with its own API and credentials, so it won't work with this package.

## Requirements

- PHP 8.2+
- Laravel 11, 12 or 13

## Installation

```bash
composer require ahmad-chebbo/laravel-areeba-payment
```

Add your credentials to `.env`:

```env
AREEBA_MERCHANT_ID=TEST1234567
AREEBA_API_PASSWORD=your-api-password
AREEBA_WEBHOOK_SECRET=your-notification-secret
```

- **API password:** create it in Merchant Administration under *Admin → Integration Settings*.
- **Test mode:** use your test merchant ID (usually prefixed `TEST`). It uses the same gateway URL and moves no real money.
- **Webhook secret:** enable notifications in Merchant Administration and copy the notification secret.

To change anything else, publish the config file:

```bash
php artisan vendor:publish --tag=areeba-config
```

| Variable | Default |
|---|---|
| `AREEBA_GATEWAY_URL` | `https://epayment.areeba.com` |
| `AREEBA_API_VERSION` | `100` |
| `AREEBA_MERCHANT_NAME` | `APP_NAME` (shown on the payment page) |
| `AREEBA_CURRENCY` | `USD` |
| `AREEBA_TIMEOUT` / `AREEBA_CONNECT_TIMEOUT` | `30` / `10` seconds |
| `AREEBA_WEBHOOK_ENABLED` | `true` |
| `AREEBA_WEBHOOK_PATH` | `areeba/webhook` |

## Taking a payment

Pass **your own order ID** to every call. It must be unique per merchant and 1–40 characters long. It is URL-encoded for you, so IDs like `INV/2026/01` are safe. Amounts are decimal strings in major units, for example `'25.50'`.

### 1. Start the checkout

```php
use AhmadChebbo\AreebaPayment\Facades\Areeba;

public function pay(Order $order)
{
    $session = Areeba::checkout(
        orderId: (string) $order->id,
        amount: $order->total,                        // '25.50'
        returnUrl: route('orders.return', $order),
        currency: 'USD',                              // optional, defaults to AREEBA_CURRENCY
        description: "Order #{$order->id}",           // optional
        cancelUrl: route('cart'),                     // optional
        timeoutUrl: route('checkout.expired'),        // optional, when the session expires
    );

    // The success indicator is the secret used to verify the payer's return. Keep it server side.
    $order->update(['areeba_success_indicator' => $session->successIndicator]);

    return view('orders.pay', ['session' => $session]);
}
```

```blade
{{-- resources/views/orders/pay.blade.php --}}
<x-areeba::checkout :session="$session" />

{{-- or keep the payer on your page --}}
<x-areeba::checkout :session="$session" mode="embedded" class="my-8" />
```

Without a view, for example from an API or a mobile app, redirect to the hosted payment page instead:

```php
return redirect($session->paymentUrl);
```

Client-side checkout errors are dispatched as a DOM event:

```js
document.addEventListener('areeba:error', (e) => console.error(e.detail));
```

### 2. Verify the return

The payer comes back to `returnUrl` with `?resultIndicator=...`. **Never mark an order paid from the query string alone.** `verify()` checks the indicator, then confirms the order with the gateway:

```php
use AhmadChebbo\AreebaPayment\Exceptions\PaymentVerificationException;

public function return(Request $request, Order $order)
{
    try {
        $payment = Areeba::verify(
            orderId: (string) $order->id,
            resultIndicator: (string) $request->query('resultIndicator'),
            successIndicator: (string) $order->areeba_success_indicator,
            expectedAmount: $order->total,      // optional, but recommended
            expectedCurrency: 'USD',            // optional
        );
    } catch (PaymentVerificationException) {
        return redirect()->route('cart')->with('error', 'Payment could not be verified.');
    }

    if ($payment->isPaid()) {
        $order->markPaid();

        return redirect()->route('orders.show', $order);
    }

    return redirect()->route('cart')->with('error', 'Payment was not completed.');
}
```

`$payment` is a `GatewayOrder` with `id`, `status` (an `OrderStatus` enum), `amount`, `currency`, `totalRefundedAmount`, the helper methods `isPaid()` and `isAuthorized()`, and `raw`.

### Authorize now, capture later

```php
use AhmadChebbo\AreebaPayment\Enums\CheckoutOperation;

$session = Areeba::checkout(..., operation: CheckoutOperation::Authorize);
// after verify() shows isAuthorized():
Areeba::capture(orderId: '1001', amount: '25.50');
```

### Extra checkout fields

Any other `INITIATE_CHECKOUT` field can be passed through `extra`, and is merged over the defaults:

```php
Areeba::checkout(..., extra: [
    'interaction' => ['displayControl' => ['billingAddress' => 'HIDE', 'shipping' => 'HIDE']],
    'customer' => ['email' => $user->email],
]);
```

## Webhook notifications

The package registers `POST /areeba/webhook`, named `areeba.webhook`.

- **No gateway setup needed for the URL.** When the URL is `https`, `checkout()` sends it with each order as `order.notificationUrl`. Local `http` setups skip it, because the gateway accepts only https.
- **Verified.** Requests without the correct `X-Notification-Secret` header are rejected with a 403. If `AREEBA_WEBHOOK_SECRET` is not set, every request is rejected.
- **Handled once.** A redelivered notification (same `X-Notification-Id`) is acknowledged without firing events again. If one of your listeners throws, the gateway's next retry is processed.

Listen for the events in your application:

```php
use AhmadChebbo\AreebaPayment\Events\PaymentSucceeded;
use AhmadChebbo\AreebaPayment\Events\PaymentFailed;
use AhmadChebbo\AreebaPayment\Events\PaymentRefunded;
use AhmadChebbo\AreebaPayment\Events\NotificationReceived;

Event::listen(function (PaymentSucceeded $event) {
    Order::find($event->notification->order->id)?->markPaid();
});
```

| Event | Fired when |
|---|---|
| `NotificationReceived` | every verified notification |
| `PaymentSucceeded` | a payment or capture succeeded and the order is paid |
| `PaymentFailed` | a payment was declined or failed |
| `PaymentRefunded` | a refund succeeded |

Each event has `$event->notification` with `id`, `attempt`, `order` (a `GatewayOrder`), `transaction` (a `TransactionResult`) and `raw`.

Listeners run during the gateway's request. Queue slow work: implement `ShouldQueue` on the listener.

To register the route yourself, set `AREEBA_WEBHOOK_ENABLED=false` and point a route at `AhmadChebbo\AreebaPayment\Http\Controllers\WebhookController`, behind the `VerifyNotificationSecret` middleware.

## Refunds

```php
$result = Areeba::refund(orderId: '1001', amount: '10.00');

if (! $result->isSuccessful()) {
    // $result->gatewayCode explains why, e.g. DECLINED
}
```

Declines are returned, not thrown. Refunds can be partial and repeated, up to the amount paid. Pass `transactionId` to set your own transaction reference; otherwise a UUID is generated.

## Stored cards

Save the card from a checkout session. Use `CheckoutOperation::Verify` to save it without charging:

```php
$card = Areeba::tokenize($session->sessionId);

$user->cards()->create([
    'token' => $card->token,
    'last_four' => $card->lastFour,
    'expiry' => "{$card->expiryMonth}/{$card->expiryYear}",
    'scheme' => $card->scheme,
]);
```

Charge it later, for example for a renewal:

```php
$result = Areeba::payWithToken(
    orderId: 'renewal-5501',
    amount: '9.99',
    token: $card->token,
    extra: ['agreement' => ['id' => 'plan-7', 'type' => 'RECURRING']],   // what your account requires
);
```

Also available: `Areeba::retrieveToken($token)` and `Areeba::deleteToken($token)`.

The gateway has no subscription API. Schedule renewals in your application and charge them with `payWithToken()`. Check with Areeba which `agreement` fields your merchant account needs for recurring charges.

## Errors

| Exception | When |
|---|---|
| `GatewayException` | the gateway rejected the request or could not be reached. `$e->context()` has `status`, `cause` and `field`. |
| `PaymentVerificationException` | `verify()` found a forged indicator or an amount/currency mismatch |
| `AreebaException` | invalid input (such as a bad amount) or missing credentials. It is the parent of the two above. |

Reads such as `retrieveOrder()` and `verify()` are retried on connection and 5xx errors. Writes (pay, capture, refund) are never retried, because a retry could move money twice.

## Testing your application

The package uses Laravel's HTTP client, so `Http::fake()` works:

```php
Http::fake([
    '*/session' => Http::response([
        'result' => 'SUCCESS',
        'session' => ['id' => 'SESSION0001'],
        'successIndicator' => 'abc',
    ]),
]);
```

Run the package's own tests with:

```bash
composer install
composer test
```

## Upgrading from 2.x

3.0 is a rewrite. The package no longer owns your data:

1. **Facade:** replace `AhmadChebbo\AreebaPayment\Facade\Areeba` with `AhmadChebbo\AreebaPayment\Facades\Areeba`.
2. **Environment:** replace `AREEBA_PAYMENT_URL=https://epayment.areeba.com/api/rest/version/79` with `AREEBA_GATEWAY_URL=https://epayment.areeba.com` and `AREEBA_API_VERSION=100`. Remove the unused 2.x variables.
3. **Checkout:** replace `Areeba::initCheckout($order)` with `Areeba::checkout(...)` and render `<x-areeba::checkout>`. Handle the return with `Areeba::verify()` in your own route, instead of `/payment/areeba/success`.
4. **Webhooks:** listen for the new events. Set `AREEBA_WEBHOOK_SECRET`. The webhook URL moved from `/payment/areeba/webhook` to `/areeba/webhook`.
5. **Your data:** the `areeba_*` tables are no longer used. Move the columns you need (such as the success indicator and card tokens) to your own tables before dropping them.
6. **Removed routes:** the orders, cards, refund, analytics and subscription endpoints are gone. Build the ones you need in your app, behind authentication.

## License

MIT. See [LICENSE](LICENSE).

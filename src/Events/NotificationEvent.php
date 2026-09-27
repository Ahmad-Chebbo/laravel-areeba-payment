<?php

declare(strict_types=1);

namespace AhmadChebbo\AreebaPayment\Events;

use AhmadChebbo\AreebaPayment\Data\Notification;

/**
 * Base for the events fired from a verified webhook notification. Match your order with
 * $notification->order->id (the order ID you passed to Areeba::checkout()).
 */
abstract class NotificationEvent
{
    public function __construct(public readonly Notification $notification) {}
}

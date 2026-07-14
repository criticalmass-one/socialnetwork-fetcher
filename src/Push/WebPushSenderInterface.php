<?php declare(strict_types=1);

namespace App\Push;

use App\Entity\PushSubscription;

interface WebPushSenderInterface
{
    /**
     * Send a notification payload to the given subscriptions. Subscriptions the
     * push service reports as gone (expired/unsubscribed) are pruned.
     *
     * @param iterable<PushSubscription> $subscriptions
     * @param array<string, mixed>       $payload       decoded into JSON for the service worker
     */
    public function send(iterable $subscriptions, array $payload): void;

    /** Web push is only usable when VAPID keys are configured. */
    public function isEnabled(): bool;
}

<?php declare(strict_types=1);

namespace App\Push;

use App\Entity\PushSubscription;
use Doctrine\ORM\EntityManagerInterface;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Psr\Log\LoggerInterface;

/**
 * Sends Web Push notifications via VAPID and prunes subscriptions the push
 * service reports as expired/gone. No-ops when no VAPID keys are configured,
 * so the app runs fine without push set up.
 */
class WebPushSender implements WebPushSenderInterface
{
    private readonly bool $enabled;
    private ?WebPush $webPush = null;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        string $vapidPublicKey,
        string $vapidPrivateKey,
        string $vapidSubject,
    ) {
        $this->enabled = $vapidPublicKey !== '' && $vapidPrivateKey !== '';

        if ($this->enabled) {
            $this->webPush = new WebPush([
                'VAPID' => [
                    'subject' => $vapidSubject !== '' ? $vapidSubject : 'mailto:admin@localhost',
                    'publicKey' => $vapidPublicKey,
                    'privateKey' => $vapidPrivateKey,
                ],
            ]);
        }
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function send(iterable $subscriptions, array $payload): void
    {
        if (!$this->enabled || $this->webPush === null) {
            return;
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        /** @var array<string, PushSubscription> $byEndpoint */
        $byEndpoint = [];
        $queued = 0;

        foreach ($subscriptions as $subscription) {
            $byEndpoint[$subscription->getEndpoint()] = $subscription;

            $this->webPush->queueNotification(
                Subscription::create([
                    'endpoint' => $subscription->getEndpoint(),
                    'keys' => [
                        'p256dh' => $subscription->getP256dh(),
                        'auth' => $subscription->getAuth(),
                    ],
                    'contentEncoding' => 'aes128gcm',
                ]),
                $json,
            );
            ++$queued;
        }

        if ($queued === 0) {
            return;
        }

        $pruned = false;

        foreach ($this->webPush->flush() as $report) {
            if ($report->isSuccess()) {
                continue;
            }

            $endpoint = $report->getEndpoint();

            if ($report->isSubscriptionExpired() && isset($byEndpoint[$endpoint])) {
                $this->entityManager->remove($byEndpoint[$endpoint]);
                $pruned = true;

                continue;
            }

            $this->logger->warning('Web push delivery failed for {endpoint}: {reason}', [
                'endpoint' => $endpoint,
                'reason' => $report->getReason(),
            ]);
        }

        if ($pruned) {
            $this->entityManager->flush();
        }
    }
}

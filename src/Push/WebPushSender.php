<?php declare(strict_types=1);

namespace App\Push;

use App\Entity\PushSubscription;
use Doctrine\ORM\EntityManagerInterface;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Sends Web Push notifications via VAPID and prunes subscriptions the push
 * service reports as expired/gone. No-ops when no VAPID keys are configured,
 * so the app runs fine without push set up.
 */
class WebPushSender implements WebPushSenderInterface
{
    /** Per-request timeout in seconds (web-push <= 10 used Guzzle with a 30 s default). */
    private const int TIMEOUT = 30;

    private readonly bool $enabled;
    private ?WebPush $webPush = null;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        HttpClientInterface $httpClient,
        string $vapidPublicKey,
        string $vapidPrivateKey,
        string $vapidSubject,
    ) {
        $this->enabled = $vapidPublicKey !== '' && $vapidPrivateKey !== '';

        if ($this->enabled) {
            // web-push >= 11 speaks PSR-18/PSR-17 instead of Guzzle. Hand it
            // Symfony's HttpClient (PSR-17 factories come from nyholm/psr7) so
            // nothing depends on php-http/discovery finding an implementation.
            $psr18Client = new Psr18Client($httpClient->withOptions(['timeout' => self::TIMEOUT]));

            $this->webPush = new WebPush(
                auth: [
                    'VAPID' => [
                        'subject' => $vapidSubject !== '' ? $vapidSubject : 'mailto:admin@localhost',
                        'publicKey' => $vapidPublicKey,
                        'privateKey' => $vapidPrivateKey,
                    ],
                ],
                client: $psr18Client,
                requestFactory: $psr18Client,
                streamFactory: $psr18Client,
                logger: $this->logger,
            );
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

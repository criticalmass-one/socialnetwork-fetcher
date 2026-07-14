<?php declare(strict_types=1);

namespace App\Tests\Functional\PublicPage;

use App\Entity\Client;
use App\Entity\Group;
use App\Entity\Profile;
use App\Entity\PushSubscription;
use App\Tests\Functional\AbstractApiTestCase;
use Doctrine\ORM\EntityManagerInterface;

class PublicGroupPushTest extends AbstractApiTestCase
{
    private const SUBSCRIPTION = [
        'endpoint' => 'https://push.example.com/endpoint/abc123',
        'keys' => ['p256dh' => 'BPublicKeyValue', 'auth' => 'authSecret'],
    ];

    public function testSubscribeThenUnsubscribe(): void
    {
        $client = static::createClient();
        $group = $this->makePublicGroup('pushslug01');

        // Subscribe.
        $client->request('POST', '/p/pushslug01/push/subscribe', ['json' => self::SUBSCRIPTION]);
        self::assertResponseStatusCodeSame(201);
        self::assertSame(1, $this->subscriptionCount($group));

        // Subscribing again with the same endpoint is idempotent (upsert).
        $client->request('POST', '/p/pushslug01/push/subscribe', ['json' => self::SUBSCRIPTION]);
        self::assertResponseStatusCodeSame(201);
        self::assertSame(1, $this->subscriptionCount($group));

        // Unsubscribe removes it.
        $client->request('POST', '/p/pushslug01/push/unsubscribe', ['json' => ['endpoint' => self::SUBSCRIPTION['endpoint']]]);
        self::assertResponseIsSuccessful();
        self::assertSame(0, $this->subscriptionCount($group));
    }

    public function testSubscribeRejectsInvalidPayload(): void
    {
        $client = static::createClient();
        $group = $this->makePublicGroup('pushslug02');

        $client->request('POST', '/p/pushslug02/push/subscribe', ['json' => ['endpoint' => '']]);
        self::assertResponseStatusCodeSame(400);
        self::assertSame(0, $this->subscriptionCount($group));
    }

    public function testSubscribeOnUnknownGroupIs404(): void
    {
        $client = static::createClient();
        $client->request('POST', '/p/does-not-exist/push/subscribe', ['json' => self::SUBSCRIPTION]);
        self::assertResponseStatusCodeSame(404);
    }

    private function makePublicGroup(string $slug): Group
    {
        $em = $this->em();
        $clientA = $em->getRepository(Client::class)->findOneBy(['name' => 'Client A']);
        $profile = $em->getRepository(Profile::class)->find(90001);

        $group = new Group();
        $group->setName('Push Group');
        $group->setClient($clientA);
        $group->addProfile($profile);
        $group->setPublicPageEnabled(true);
        $group->setPublicSlug($slug);
        $em->persist($group);
        $em->flush();

        return $group;
    }

    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get('doctrine.orm.entity_manager');
    }

    private function subscriptionCount(Group $group): int
    {
        $em = $this->em();
        $em->clear();

        return (int) $em->getRepository(PushSubscription::class)->count([
            'group' => $em->getRepository(Group::class)->find($group->getId()),
        ]);
    }
}

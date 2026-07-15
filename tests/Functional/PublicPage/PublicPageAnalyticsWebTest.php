<?php declare(strict_types=1);

namespace App\Tests\Functional\PublicPage;

use App\Entity\Client;
use App\Entity\Group;
use App\Entity\Profile;
use App\Entity\PublicPageEvent;
use App\PublicPage\OutboundLinkSigner;
use App\Tests\Functional\AbstractWebTestCase;

class PublicPageAnalyticsWebTest extends AbstractWebTestCase
{
    public function testPageViewIsRecorded(): void
    {
        $group = $this->makePublicGroup('statsview01');

        $this->client->request('GET', '/p/statsview01');
        self::assertResponseIsSuccessful();

        self::assertSame(1, $this->countEvents($group, PublicPageEvent::TYPE_VIEW));
    }

    public function testSignedOutboundClickIsRecordedAndRedirects(): void
    {
        $group = $this->makePublicGroup('statsclick01');
        $signer = static::getContainer()->get(OutboundLinkSigner::class);
        $target = 'https://www.instagram.com/p/Abc/';

        $this->client->request('GET', '/p/statsclick01/go?' . http_build_query(['u' => $target, 's' => $signer->sign($target)]));

        self::assertResponseRedirects($target);
        self::assertSame(1, $this->countEvents($group, PublicPageEvent::TYPE_CLICK));
    }

    public function testInvalidSignatureDoesNotOpenRedirectAndIsNotCounted(): void
    {
        $group = $this->makePublicGroup('statsclick02');

        $this->client->request('GET', '/p/statsclick02/go?' . http_build_query(['u' => 'https://evil.example.com/', 's' => 'forged']));

        // Redirects to the public page, NOT the unsigned target; nothing recorded.
        self::assertResponseRedirects('/p/statsclick02');
        self::assertSame(0, $this->countEvents($group, PublicPageEvent::TYPE_CLICK));
    }

    public function testAdminGroupPageShowsStats(): void
    {
        $group = $this->makePublicGroup('statsadmin01');
        $signer = static::getContainer()->get(OutboundLinkSigner::class);
        // Two views, one click.
        $this->client->request('GET', '/p/statsadmin01');
        $this->client->request('GET', '/p/statsadmin01');
        $target = 'https://example.com/x';
        $this->client->request('GET', '/p/statsadmin01/go?' . http_build_query(['u' => $target, 's' => $signer->sign($target)]));

        $this->loginAsAdmin();
        $crawler = $this->client->request('GET', sprintf('/groups/%d', $group->getId()));

        self::assertResponseIsSuccessful();
        $body = $crawler->filter('body')->text();
        self::assertStringContainsString('Statistik (nur Admin)', $body);
        self::assertStringContainsString('Aufrufe gesamt', $body);
    }

    private function makePublicGroup(string $slug): Group
    {
        $em = $this->entityManager();
        $clientA = $em->getRepository(Client::class)->findOneBy(['name' => 'Client A']);
        $profile = $em->getRepository(Profile::class)->find(90001);

        $group = new Group();
        $group->setName('Stats Group');
        $group->setClient($clientA);
        $group->addProfile($profile);
        $group->setPublicPageEnabled(true);
        $group->setPublicSlug($slug);
        $em->persist($group);
        $em->flush();

        return $group;
    }

    private function countEvents(Group $group, string $type): int
    {
        $em = $this->entityManager();
        $em->clear();

        return (int) $em->getRepository(PublicPageEvent::class)->count([
            'group' => $em->getRepository(Group::class)->find($group->getId()),
            'type' => $type,
        ]);
    }
}

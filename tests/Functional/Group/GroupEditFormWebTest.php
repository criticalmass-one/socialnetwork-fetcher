<?php declare(strict_types=1);

namespace App\Tests\Functional\Group;

use App\Entity\Client;
use App\Entity\Group;
use App\Entity\Profile;
use App\Tests\Functional\AbstractWebTestCase;

/**
 * The group edit form must wire the profiles select to the searchable-select
 * Stimulus controller (Tom Select) instead of rendering a plain oversized
 * multi-select.
 */
class GroupEditFormWebTest extends AbstractWebTestCase
{
    public function testProfilesFieldUsesSearchableSelect(): void
    {
        $group = $this->createGroup();

        $this->loginAsAdmin();
        $crawler = $this->client->request('GET', sprintf('/groups/%d/edit', $group->getId()));

        self::assertResponseIsSuccessful();

        $profilesSelect = $crawler->filter('select[name="group[profiles][]"]');
        self::assertCount(1, $profilesSelect, 'profiles select is rendered');
        self::assertSame('searchable-select', $profilesSelect->attr('data-controller'));
        self::assertNotNull($profilesSelect->attr('multiple'));

        // Search must cover the label plus the profile title and identifier.
        $searchFields = $profilesSelect->attr('data-searchable-select-search-fields-value');
        self::assertNotNull($searchFields);
        self::assertStringContainsString('title', $searchFields);
        self::assertStringContainsString('identifier', $searchFields);
    }

    public function testProfileOptionsExposeTitleAndIdentifierForSearch(): void
    {
        $group = $this->createGroup();

        $this->loginAsAdmin();
        $crawler = $this->client->request('GET', sprintf('/groups/%d/edit', $group->getId()));

        self::assertResponseIsSuccessful();

        // Profile 90001 (identifier https://mastodon.social/@shared, no title)
        // must carry data attributes so Tom Select can match on them.
        $option = $crawler->filter('select[name="group[profiles][]"] option[value="90001"]');
        self::assertCount(1, $option);
        self::assertSame('https://mastodon.social/@shared', $option->attr('data-identifier'));
        self::assertNotNull($option->attr('data-title'));
    }

    private function createGroup(): Group
    {
        $em = $this->entityManager();

        /** @var Client $client */
        $client = $em->getRepository(Client::class)->findOneBy(['name' => 'Client A']);
        /** @var Profile $profile */
        $profile = $em->getRepository(Profile::class)->find(90001);

        $group = new Group();
        $group->setName('Editable Group');
        $group->setClient($client);
        $group->setCreatedAt(new \DateTimeImmutable());
        $group->addProfile($profile);
        $em->persist($group);
        $em->flush();

        return $group;
    }
}

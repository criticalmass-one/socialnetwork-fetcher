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

<?php declare(strict_types=1);

namespace App\Tests\Functional\Group;

use App\Entity\Client;
use App\Entity\Group;
use App\Entity\Item;
use App\Entity\Profile;
use App\Tests\Functional\AbstractWebTestCase;

/**
 * Regression test: the group show page renders a pagination bar once a group has
 * more than one page of items. The shared _pagination partial regenerates the
 * current route from the query string only, so it must also carry the route's
 * path parameters (the group id) — otherwise app_group_show cannot be generated
 * and the whole page 500s with MissingMandatoryParametersException.
 */
class GroupShowPaginationWebTest extends AbstractWebTestCase
{
    public function testShowRendersPaginationForMultiPageGroup(): void
    {
        $group = $this->createGroupWithItems(60);

        $this->loginAsAdmin();
        $this->client->request('GET', sprintf('/groups/%d', $group->getId()));

        // Before the fix this returned 500 (missing "id" for app_group_show).
        self::assertResponseIsSuccessful();
        // Pagination links must keep the group id in the path, not drop it.
        self::assertSelectorExists(
            sprintf('.pagination a[href*="/groups/%d?"][href*="page=2"]', $group->getId()),
        );
    }

    private function createGroupWithItems(int $itemCount): Group
    {
        $em = $this->entityManager();

        /** @var Client $client */
        $client = $em->getRepository(Client::class)->findOneBy(['name' => 'Client A']);
        /** @var Profile $profile */
        $profile = $em->getRepository(Profile::class)->find(90001);

        $group = new Group();
        $group->setName('Pagination Group');
        $group->setClient($client);
        $group->setCreatedAt(new \DateTimeImmutable());
        $group->addProfile($profile);
        $em->persist($group);

        $now = new \DateTimeImmutable();
        for ($i = 0; $i < $itemCount; ++$i) {
            $item = new Item();
            $item->setProfile($profile);
            $item->setUniqueIdentifier(sprintf('pagination-item-%d', $i));
            $item->setPermalink(sprintf('https://mastodon.social/@shared/pagination-%d', $i));
            $item->setText(sprintf('Pagination item %d', $i));
            $item->setDateTime($now->modify(sprintf('-%d minutes', $i)));
            $em->persist($item);
        }

        $em->flush();

        return $group;
    }
}

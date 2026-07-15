<?php declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Entity\Network;
use App\Entity\Profile;
use App\Repository\ProfileRepository;
use App\Tests\Functional\AbstractWebTestCase;

class ProfileSortTest extends AbstractWebTestCase
{
    public function testSortByCreatedAtAndLastFetch(): void
    {
        $em = $this->entityManager();
        $network = $em->getRepository(Network::class)->findOneBy(['identifier' => 'mastodon']);

        $this->makeProfile(97001, '2026-01-01', '2026-06-01'); // oldest created, mid fetch
        $this->makeProfile(97002, '2026-03-01', null);          // newest created, never fetched
        $this->makeProfile(97003, '2026-02-01', '2026-07-01');  // newest fetch
        $em->flush();

        /** @var ProfileRepository $repo */
        $repo = $em->getRepository(Profile::class);
        $ids = fn (string $sort): array => array_map(
            static fn (Profile $p): int => $p->getId(),
            $repo->findPaginated(1, 50, [], 'sorttest', '', $sort),
        );

        self::assertSame([97002, 97003, 97001], $ids('created_desc'));
        self::assertSame([97001, 97003, 97002], $ids('created_asc'));
        // Never-fetched (97002) is kept last in both directions.
        self::assertSame([97003, 97001, 97002], $ids('fetch_desc'));
        self::assertSame([97001, 97003, 97002], $ids('fetch_asc'));
    }

    private function makeProfile(int $id, string $created, ?string $fetched): void
    {
        $em = $this->entityManager();
        $network = $em->getRepository(Network::class)->findOneBy(['identifier' => 'mastodon']);

        $profile = new Profile();
        $profile->setId($id);
        $profile->setIdentifier('sorttest-' . $id);
        $profile->setNetwork($network);
        $profile->setCreatedAt(new \DateTimeImmutable($created));
        if ($fetched !== null) {
            $profile->setLastFetchSuccessDateTime(new \DateTimeImmutable($fetched));
        }
        $em->persist($profile);
    }
}

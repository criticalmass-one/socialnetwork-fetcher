<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Client;
use App\Entity\Item;
use App\Entity\Network;
use App\Entity\Profile;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class TestFixtures extends Fixture implements DependentFixtureInterface
{
    public function getDependencies(): array
    {
        return [NetworkFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        /** @var Network $networkMastodon */
        $networkMastodon = $this->getReference(NetworkFixtures::NETWORK_MASTODON, Network::class);
        /** @var Network $networkBluesky */
        $networkBluesky = $this->getReference(NetworkFixtures::NETWORK_BLUESKY_PROFILE, Network::class);

        // --- Clients ---
        $clientA = new Client();
        $clientA->setName('Client A');
        $clientA->setToken(str_repeat('a', 64));
        $clientA->setEnabled(true);
        $manager->persist($clientA);

        $clientB = new Client();
        $clientB->setName('Client B');
        $clientB->setToken(str_repeat('b', 64));
        $clientB->setEnabled(true);
        $manager->persist($clientB);

        $clientDisabled = new Client();
        $clientDisabled->setName('Client Disabled');
        $clientDisabled->setToken(str_repeat('c', 64));
        $clientDisabled->setEnabled(false);
        $manager->persist($clientDisabled);

        // --- Profiles ---
        $profileShared = new Profile();
        $profileShared->setId(90001);
        $profileShared->setIdentifier('https://mastodon.social/@shared');
        $profileShared->setNetwork($networkMastodon);
        $profileShared->setCreatedAt(new \DateTimeImmutable());
        $manager->persist($profileShared);
        $clientA->addProfile($profileShared);
        $clientB->addProfile($profileShared);

        $profileOnlyA = new Profile();
        $profileOnlyA->setId(90002);
        $profileOnlyA->setIdentifier('onlya.bsky.social');
        $profileOnlyA->setNetwork($networkBluesky);
        $profileOnlyA->setCreatedAt(new \DateTimeImmutable());
        $manager->persist($profileOnlyA);
        $clientA->addProfile($profileOnlyA);

        $profileOnlyB = new Profile();
        $profileOnlyB->setId(90003);
        $profileOnlyB->setIdentifier('https://mastodon.social/@onlyb');
        $profileOnlyB->setNetwork($networkMastodon);
        $profileOnlyB->setCreatedAt(new \DateTimeImmutable());
        $manager->persist($profileOnlyB);
        $clientB->addProfile($profileOnlyB);

        $profileDeleted = new Profile();
        $profileDeleted->setId(90004);
        $profileDeleted->setIdentifier('https://mastodon.social/@deleted');
        $profileDeleted->setNetwork($networkMastodon);
        $profileDeleted->setCreatedAt(new \DateTimeImmutable());
        $profileDeleted->setDeleted(true);
        $profileDeleted->setDeletedAt(new \DateTimeImmutable());
        $manager->persist($profileDeleted);

        // --- Items ---
        $now = new \DateTimeImmutable();

        // profileShared: 3 items (1h, 12h, 48h ago)
        foreach ([1, 12, 48] as $i => $hoursAgo) {
            $item = new Item();
            $item->setProfile($profileShared);
            $item->setUniqueIdentifier(sprintf('shared-item-%d', $i + 1));
            $item->setPermalink(sprintf('https://mastodon.social/@shared/%d', $i + 1));
            $item->setText(sprintf('Shared item %d', $i + 1));
            $item->setDateTime($now->modify(sprintf('-%d hours', $hoursAgo)));
            $manager->persist($item);
        }

        // profileOnlyA: 2 items (2h, 36h ago)
        foreach ([2, 36] as $i => $hoursAgo) {
            $item = new Item();
            $item->setProfile($profileOnlyA);
            $item->setUniqueIdentifier(sprintf('onlya-item-%d', $i + 1));
            $item->setPermalink(sprintf('https://bsky.app/profile/onlya.bsky.social/post/%d', $i + 1));
            $item->setText(sprintf('OnlyA item %d', $i + 1));
            $item->setDateTime($now->modify(sprintf('-%d hours', $hoursAgo)));
            $manager->persist($item);
        }

        // profileOnlyB: 2 items (3h, 6h ago)
        foreach ([3, 6] as $i => $hoursAgo) {
            $item = new Item();
            $item->setProfile($profileOnlyB);
            $item->setUniqueIdentifier(sprintf('onlyb-item-%d', $i + 1));
            $item->setPermalink(sprintf('https://mastodon.social/@onlyb/%d', $i + 1));
            $item->setText(sprintf('OnlyB item %d', $i + 1));
            $item->setDateTime($now->modify(sprintf('-%d hours', $hoursAgo)));
            $manager->persist($item);
        }

        $manager->flush();
    }
}

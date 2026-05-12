<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\Network;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Network> */
class NetworkRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Network::class);
    }

    public function findOneByName(string $name): ?Network
    {
        return $this->findOneBy(['name' => $name]);
    }

    public function findNetworkForProfileUrl(string $url): ?Network
    {
        $networks = $this->findAll();

        // Prefer specific patterns over catch-alls: longer patterns are tried first.
        // Without this, a network with a permissive pattern (e.g. homepage's
        // ^https?://.+$) can shadow a more specific match like instagram_profile.
        usort($networks, static fn(Network $a, Network $b): int =>
            strlen($b->getProfileUrlPattern() ?? '') <=> strlen($a->getProfileUrlPattern() ?? '')
        );

        foreach ($networks as $network) {
            if ($network->isValidProfileUrl($url)) {
                return $network;
            }
        }

        return null;
    }
}

<?php declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Client;
use App\Entity\Profile;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** @implements ProviderInterface<Profile> */
class ClientScopedProfileProvider implements ProviderInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array|Profile|null
    {
        $client = $this->security->getUser();

        if (!$client instanceof Client) {
            return $operation instanceof GetCollection ? [] : null;
        }

        if ($operation instanceof GetCollection) {
            return $this->getCollection($client);
        }

        return $this->getItem($client, (int) $uriVariables['id']);
    }

    private function getCollection(Client $client): array
    {
        return $this->em->createQueryBuilder()
            ->select('p')
            ->from(Profile::class, 'p')
            ->innerJoin('p.clients', 'c')
            ->where('c.id = :clientId')
            ->andWhere('p.deleted = false')
            ->setParameter('clientId', $client->getId())
            ->orderBy('p.identifier', 'ASC')
            ->getQuery()
            ->getResult();
    }

    private function getItem(Client $client, int $profileId): Profile
    {
        $profile = $this->em->createQueryBuilder()
            ->select('p')
            ->from(Profile::class, 'p')
            ->innerJoin('p.clients', 'c')
            ->where('c.id = :clientId')
            ->andWhere('p.id = :profileId')
            ->andWhere('p.deleted = false')
            ->setParameter('clientId', $client->getId())
            ->setParameter('profileId', $profileId)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$profile) {
            throw new NotFoundHttpException('Profile not found.');
        }

        return $profile;
    }
}

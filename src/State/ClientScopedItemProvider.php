<?php declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Client;
use App\Entity\Item;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** @implements ProviderInterface<Item> */
class ClientScopedItemProvider implements ProviderInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array|Item|null
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
            ->select('i')
            ->from(Item::class, 'i')
            ->innerJoin('i.profile', 'p')
            ->innerJoin('p.clients', 'c')
            ->where('c.id = :clientId')
            ->andWhere('p.deleted = false')
            ->setParameter('clientId', $client->getId())
            ->orderBy('i.dateTime', 'DESC')
            ->getQuery()
            ->getResult();
    }

    private function getItem(Client $client, int $itemId): Item
    {
        $item = $this->em->createQueryBuilder()
            ->select('i')
            ->from(Item::class, 'i')
            ->innerJoin('i.profile', 'p')
            ->innerJoin('p.clients', 'c')
            ->where('c.id = :clientId')
            ->andWhere('i.id = :itemId')
            ->andWhere('p.deleted = false')
            ->setParameter('clientId', $client->getId())
            ->setParameter('itemId', $itemId)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$item) {
            throw new NotFoundHttpException('Item not found.');
        }

        return $item;
    }
}

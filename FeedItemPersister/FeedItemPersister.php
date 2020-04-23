<?php declare(strict_types=1);

namespace App\Criticalmass\SocialNetwork\FeedItemPersister;

use Doctrine\Persistence\ManagerRegistry;

class FeedItemPersister implements FeedItemPersisterInterface
{
    protected ManagerRegistry $doctrine;

    public function __construct(ManagerRegistry $doctrine)
    {
        $this->doctrine = $doctrine;
    }

    public function persistFeedItemList(array $feedItemList): FeedItemPersisterInterface
    {
        $em = $this->doctrine->getManager();

        foreach ($feedItemList as $feedItem) {
            $em->persist($feedItem);
        }

        try {
            $em->flush();
        } catch (\Exception $exception) {

        }

        return $this;
    }
}
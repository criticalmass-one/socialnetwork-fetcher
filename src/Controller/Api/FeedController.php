<?php declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Client;
use App\Entity\Item;
use App\Entity\Profile;
use App\Repository\ProfileRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UrlHelper;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

class FeedController
{
    private const DEFAULT_LIMIT = 100;
    private const MAX_LIMIT = 200;
    private const DEFAULT_SINCE_HOURS = 168; // 7 days
    private const MEDIA_PATH_PREFIX = '/media/';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ProfileRepository $profileRepository,
        private readonly Security $security,
        private readonly UrlHelper $urlHelper,
    ) {
    }

    #[Route('/api/feeds/timeline.rss', name: 'app_api_feed_timeline', methods: ['GET'])]
    public function timeline(Request $request): Response
    {
        $client = $this->requireClient();

        $since = $request->query->has('since')
            ? new \DateTimeImmutable((string) $request->query->get('since'))
            : new \DateTimeImmutable(sprintf('-%d hours', self::DEFAULT_SINCE_HOURS));

        $limit = min(
            max(1, $request->query->getInt('limit', self::DEFAULT_LIMIT)),
            self::MAX_LIMIT,
        );

        $network = $request->query->get('network');

        $qb = $this->em->createQueryBuilder()
            ->select('i')
            ->from(Item::class, 'i')
            ->innerJoin('i.profile', 'p')
            ->innerJoin('p.clients', 'c')
            ->where('c.id = :clientId')
            ->andWhere('p.deleted = false')
            ->andWhere('i.hidden = false')
            ->andWhere('i.deleted = false')
            ->andWhere('i.dateTime >= :since')
            ->setParameter('clientId', $client->getId())
            ->setParameter('since', $since)
            ->orderBy('i.dateTime', 'DESC')
            ->setMaxResults($limit);

        if ($network !== null) {
            $qb->innerJoin('p.network', 'n')
                ->andWhere('n.identifier = :network')
                ->setParameter('network', $network);
        }

        $items = $qb->getQuery()->getResult();

        $title = sprintf('Social Network Timeline — %s', $client->getName());
        if ($network !== null) {
            $title .= sprintf(' (%s)', $network);
        }

        return $this->buildRssResponse(
            title: $title,
            description: 'Aggregated feed of items across all linked social-network profiles.',
            selfUrl: $this->urlHelper->getAbsoluteUrl($request->getRequestUri()),
            items: $items,
        );
    }

    #[Route('/api/feeds/profiles/{id}.rss', name: 'app_api_feed_profile', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function profileFeed(int $id, Request $request): Response
    {
        $client = $this->requireClient();

        $profile = $this->profileRepository->find($id);
        if ($profile === null || !$client->getProfiles()->contains($profile) || $profile->isDeleted()) {
            throw new NotFoundHttpException('Profile not found.');
        }

        $limit = min(
            max(1, $request->query->getInt('limit', self::DEFAULT_LIMIT)),
            self::MAX_LIMIT,
        );

        $items = $this->em->createQueryBuilder()
            ->select('i')
            ->from(Item::class, 'i')
            ->where('i.profile = :profile')
            ->andWhere('i.hidden = false')
            ->andWhere('i.deleted = false')
            ->setParameter('profile', $profile)
            ->orderBy('i.dateTime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()->getResult();

        $networkName = $profile->getNetwork()?->getName() ?? 'unknown';
        $title = sprintf('%s — %s', $profile->getDisplayName(), $networkName);

        return $this->buildRssResponse(
            title: $title,
            description: sprintf('Feed of items from %s on %s.', $profile->getDisplayName(), $networkName),
            selfUrl: $this->urlHelper->getAbsoluteUrl($request->getRequestUri()),
            items: $items,
        );
    }

    private function requireClient(): Client
    {
        $client = $this->security->getUser();
        if (!$client instanceof Client) {
            throw new AccessDeniedHttpException();
        }
        return $client;
    }

    /** @param list<Item> $items */
    private function buildRssResponse(string $title, string $description, string $selfUrl, array $items): Response
    {
        $rss = new \SimpleXMLElement(
            '<?xml version="1.0" encoding="UTF-8"?>'
            . '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:dc="http://purl.org/dc/elements/1.1/"></rss>'
        );

        $channel = $rss->addChild('channel');
        $this->addChildWithText($channel, 'title', $title);
        $this->addChildWithText($channel, 'description', $description);
        $this->addChildWithText($channel, 'link', $this->urlHelper->getAbsoluteUrl('/'));
        $this->addChildWithText($channel, 'language', 'de');
        $this->addChildWithText($channel, 'lastBuildDate', (new \DateTimeImmutable())->format(\DateTimeInterface::RFC2822));
        $this->addChildWithText($channel, 'generator', 'Social Network Fetcher');

        $atomLink = $channel->addChild('atom:link', null, 'http://www.w3.org/2005/Atom');
        $atomLink->addAttribute('href', $selfUrl);
        $atomLink->addAttribute('rel', 'self');
        $atomLink->addAttribute('type', 'application/rss+xml');

        foreach ($items as $item) {
            $this->appendItem($channel, $item);
        }

        $xml = $rss->asXML() ?: '';

        return new Response($xml, 200, [
            'Content-Type' => 'application/rss+xml; charset=UTF-8',
        ]);
    }

    private function appendItem(\SimpleXMLElement $channel, Item $item): void
    {
        $xmlItem = $channel->addChild('item');

        $title = $item->getTitle() ?: $this->buildTitleFallback($item->getText() ?? '');
        $this->addChildWithText($xmlItem, 'title', $title);

        if ($item->getPermalink() !== null) {
            $this->addChildWithText($xmlItem, 'link', $item->getPermalink());
        }

        $this->addChildWithText($xmlItem, 'description', $this->buildItemDescription($item));

        $dateTime = $item->getDateTime();
        if ($dateTime !== null) {
            $this->addChildWithText($xmlItem, 'pubDate', $dateTime->format(\DateTimeInterface::RFC2822));
        }

        $guid = $xmlItem->addChild('guid', htmlspecialchars((string) $item->getId()));
        $guid->addAttribute('isPermaLink', 'false');

        $networkName = $item->getProfile()?->getNetwork()?->getName();
        if ($networkName !== null) {
            $this->addChildWithText($xmlItem, 'category', $networkName);
        }

        $profileName = $item->getProfile()?->getDisplayName();
        if ($profileName !== null) {
            $xmlItem->addChild('dc:creator', htmlspecialchars($profileName), 'http://purl.org/dc/elements/1.1/');
        }

        $firstPhoto = $item->getPhotoPaths()[0] ?? null;
        if ($firstPhoto !== null) {
            $enclosure = $xmlItem->addChild('enclosure');
            $enclosure->addAttribute('url', $this->urlHelper->getAbsoluteUrl(self::MEDIA_PATH_PREFIX . ltrim($firstPhoto, '/')));
            $enclosure->addAttribute('type', $this->guessImageMimeType($firstPhoto));
        }
    }

    private function buildItemDescription(Item $item): string
    {
        $text = $item->getText() ?? '';

        $photoPaths = $item->getPhotoPaths();
        if ($photoPaths !== []) {
            $imgs = array_map(
                fn(string $path) => sprintf('<img src="%s" alt="" />', htmlspecialchars($this->urlHelper->getAbsoluteUrl(self::MEDIA_PATH_PREFIX . ltrim($path, '/')))),
                $photoPaths,
            );
            $text = trim($text . "\n\n" . implode("\n", $imgs));
        }

        return $text;
    }

    private function buildTitleFallback(string $text): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');
        if ($text === '') {
            return '(ohne Titel)';
        }
        return mb_strlen($text) > 80 ? mb_substr($text, 0, 77) . '…' : $text;
    }

    private function addChildWithText(\SimpleXMLElement $parent, string $name, string $value, ?string $namespace = null): \SimpleXMLElement
    {
        // SimpleXMLElement::addChild doesn't escape entities; build via DOM trick:
        // Add the child without value, then write the escaped value into the text node.
        $child = $parent->addChild($name, null, $namespace);
        $dom = dom_import_simplexml($child);
        if ($dom !== null && $dom->ownerDocument !== null) {
            $dom->appendChild($dom->ownerDocument->createTextNode($value));
        }
        return $child;
    }

    private function guessImageMimeType(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'application/octet-stream',
        };
    }
}

<?php declare(strict_types=1);

namespace App\Twig;

use App\Entity\Group;
use App\PublicPage\OutboundLinkSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * public_out(group, url): rewrites an outbound public-page link to the signed
 * click-tracking redirect (/p/{slug}/go), so every click is counted. Non-http
 * URLs are returned unchanged.
 */
class PublicOutboundExtension extends AbstractExtension
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly OutboundLinkSigner $signer,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('public_out', $this->outbound(...)),
        ];
    }

    public function outbound(Group $group, ?string $url): string
    {
        $url = (string) $url;
        if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
            return $url;
        }

        if ($group->getPublicSlug() === null) {
            return $url;
        }

        return $this->urlGenerator->generate('app_public_group_go', [
            'slug' => $group->getPublicSlug(),
            'u' => $url,
            's' => $this->signer->sign($url),
        ]);
    }
}

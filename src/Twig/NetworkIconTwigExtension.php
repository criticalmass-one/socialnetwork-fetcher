<?php declare(strict_types=1);

namespace App\Twig;

use App\Network\NetworkInterface;
use App\NetworkManager\NetworkManagerInterface;
use App\Model\SocialNetworkFeedItem;
use App\Model\SocialNetworkProfile;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class NetworkIconTwigExtension extends AbstractExtension
{
    protected NetworkManagerInterface $networkManager;

    public function __construct(NetworkManagerInterface $networkManager)
    {
        $this->networkManager = $networkManager;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('network_icon', [$this, 'networkIcon']),
        ];
    }

    public function networkIcon($param): string
    {
        if ($param instanceof SocialNetworkFeedItem) {
            $networkIdentifier = $param->getSocialNetworkProfile()->getNetwork();
        } elseif ($param instanceof SocialNetworkProfile) {
            $networkIdentifier = $param->getNetwork();
        } elseif (is_string($param)) {
            $networkIdentifier = $param;
        } else {
            throw new \InvalidArgumentException('Parameter must be instance of SocialNetworkFeedItem or SocialNetworkProfile or a string identifying the network.');
        }

        /** @var NetworkInterface $network */
        $network = $this->networkManager->getNetworkList()[$networkIdentifier];

        return $network->getIcon();
    }
}
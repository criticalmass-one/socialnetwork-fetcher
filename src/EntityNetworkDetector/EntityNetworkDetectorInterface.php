<?php declare(strict_types=1);

namespace App\EntityNetworkDetector;

use App\Network\NetworkInterface;
use App\Model\SocialNetworkProfile;

interface EntityNetworkDetectorInterface
{
    public function detect(SocialNetworkProfile $socialNetworkProfile): ?NetworkInterface;
}

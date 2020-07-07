<?php declare(strict_types=1);

namespace App\NetworkDetector;

use App\Criticalmass\SocialNetwork\Network\NetworkInterface;

interface NetworkDetectorInterface
{
    public function detect(string $url): ?NetworkInterface;

}

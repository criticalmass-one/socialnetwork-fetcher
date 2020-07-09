<?php declare(strict_types=1);

namespace App\ProfilePersister;

use App\Model\SocialNetworkProfile;

interface ProfilePersisterInterface
{
    public function persistProfile(SocialNetworkProfile $socialNetworkProfile): SocialNetworkProfile;

}
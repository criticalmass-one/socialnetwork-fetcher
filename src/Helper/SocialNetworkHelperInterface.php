<?php declare(strict_types=1);

namespace App\Helper;

use App\EntityInterface\SocialNetworkProfileAble;
use App\Model\City;
use App\Model\Ride;
use App\Model\SocialNetworkProfile;
use App\Model\Subride;
use App\Model\User;
use Symfony\Component\HttpFoundation\Request;

interface SocialNetworkHelperInterface
{
    public function getProfileAbleObject(Ride $ride = null, Subride $subride = null, City $city = null, User $user = null): SocialNetworkProfileAble;
    public function assignProfileAble(SocialNetworkProfile $socialNetworkProfile, Request $request): SocialNetworkProfile;
    public function getProfileAble(SocialNetworkProfile $socialNetworkProfile): ?SocialNetworkProfileAble;
    public function getProfileAbleShortname(SocialNetworkProfileAble $profileAble): string;
    public function getRouteName(SocialNetworkProfileAble $profileAble, string $actionName): string;
    public function getProfileList(SocialNetworkProfileAble $profileAble): array;
}

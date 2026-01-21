<?php declare(strict_types=1);

namespace App\ProfilePersister;

use App\Model\Profile;

interface ProfilePersisterInterface
{
    public function persistProfile(Profile $profile): Profile;
}
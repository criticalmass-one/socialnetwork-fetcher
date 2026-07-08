<?php declare(strict_types=1);

namespace App\Profile;

/**
 * Thrown by {@see IdentifierChanger} when the requested identifier cannot be
 * applied (empty, invalid for the network, or already used by another profile).
 * These are client errors — no destructive action has been performed yet.
 */
class IdentifierChangeException extends \RuntimeException
{
}

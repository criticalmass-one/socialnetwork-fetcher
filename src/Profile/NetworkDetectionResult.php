<?php declare(strict_types=1);

namespace App\Profile;

use App\Entity\Network;

/**
 * Outcome of resolving a network from a profile identifier: either a single
 * detected network, an ambiguous set of candidates, or nothing matched.
 */
final class NetworkDetectionResult
{
    /** @param list<Network> $candidates */
    private function __construct(
        public readonly ?Network $network,
        public readonly array $candidates,
    ) {
    }

    public static function detected(Network $network): self
    {
        return new self($network, [$network]);
    }

    /** @param list<Network> $candidates */
    public static function ambiguous(array $candidates): self
    {
        return new self(null, $candidates);
    }

    public static function none(): self
    {
        return new self(null, []);
    }

    public function isDetected(): bool
    {
        return $this->network !== null;
    }

    public function isAmbiguous(): bool
    {
        return $this->network === null && count($this->candidates) > 1;
    }
}

<?php declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Item;
use PHPUnit\Framework\TestCase;

class ItemTest extends TestCase
{
    public function testConstructorSetsCreatedAt(): void
    {
        $before = new \DateTimeImmutable();
        $item = new Item();
        $after = new \DateTimeImmutable();

        $this->assertGreaterThanOrEqual($before, $item->getCreatedAt());
        $this->assertLessThanOrEqual($after, $item->getCreatedAt());
    }

    public function testDefaultValues(): void
    {
        $item = new Item();

        $this->assertFalse($item->isHidden());
        $this->assertFalse($item->isDeleted());
        $this->assertNull($item->getId());
        $this->assertNull($item->getProfile());
    }
}

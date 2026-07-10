<?php declare(strict_types=1);

namespace App\Tests\Unit\Group;

use App\Entity\Group;
use App\Group\PublicSlugGenerator;
use App\Repository\GroupRepository;
use PHPUnit\Framework\TestCase;

class PublicSlugGeneratorTest extends TestCase
{
    public function testGeneratesUrlSafeSlug(): void
    {
        $repository = $this->createStub(GroupRepository::class);
        $repository->method('findOneBy')->willReturn(null);

        $slug = (new PublicSlugGenerator($repository))->generate();

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{16}$/', $slug);
    }

    public function testRetriesOnCollisionThenSucceeds(): void
    {
        $existing = new Group();
        $repository = $this->createStub(GroupRepository::class);
        // First lookup collides, second is free.
        $repository->method('findOneBy')->willReturnOnConsecutiveCalls($existing, null);

        $slug = (new PublicSlugGenerator($repository))->generate();

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{16}$/', $slug);
    }

    public function testThrowsWhenEveryCandidateCollides(): void
    {
        $repository = $this->createStub(GroupRepository::class);
        $repository->method('findOneBy')->willReturn(new Group());

        $this->expectException(\RuntimeException::class);
        (new PublicSlugGenerator($repository))->generate();
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Domain\ValueObjects;

use PHPUnit\Framework\TestCase;
use App\Contexts\Shared\Domain\ValueObjects\ProjectId;
use InvalidArgumentException;

class ProjectIdTest extends TestCase
{
    public function test_should_create_instance_sucessfully(): void
    {
        $projectId = new ProjectId('550e8400-e29b-41d4-a716-446655440000');

        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', $projectId->toString());
    }

    public function test_should_reject_invalid_uuid(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ProjectId('this-is-not-uuid');
    }

    public function test_should_consider_equal_uuid(): void
    {
        $projectIdAlpha = new ProjectId('550e8400-e29b-41d4-a716-446655440000');
        $projectIdBeta = new ProjectId('550e8400-e29b-41d4-a716-446655440000');

        $this->assertTrue($projectIdAlpha->equals($projectIdBeta));
    }

    public function test_should_consider_different_uuid(): void
    {
        $projectIdAlpha = new ProjectId('550e8400-e29b-41d4-a716-446655440000');
        $projectIdBeta = new ProjectId('845b5465-f54f-74d9-a896-231654654664');

        $this->assertFalse($projectIdAlpha->equals($projectIdBeta));
    }

    public function test_should_generate_a_valid_uuid(): void
    {
        $projectId = ProjectId::generate();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $projectId->toString()
        );
    }

    public function test_should_generate_unique_uuids(): void
    {
        $first  = ProjectId::generate();
        $second = ProjectId::generate();

        $this->assertFalse($first->equals($second));
    }
}

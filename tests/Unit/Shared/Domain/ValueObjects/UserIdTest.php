<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Domain\ValueObjects;

use PHPUnit\Framework\TestCase;
use App\Contexts\Shared\Domain\ValueObjects\UserId;
use InvalidArgumentException;

class UserIdTest extends TestCase
{
    private UserId $userId;

    protected function setUp(): void
    {
        $this->userId = new UserId('550e8400-e29b-41d4-a716-446655440000');
    }

    public function test_should_create_instance_successfully(): void
    {
        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', $this->userId->toString());
    }

    public function test_should_reject_invalid_uuid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new UserId('this-is-not-uuid');
    }

    public function test_should_consider_equal_uuid(): void
    {
        $userIdBeta = new UserId('550e8400-e29b-41d4-a716-446655440000');
        $this->assertTrue($this->userId->equals($userIdBeta));
    }

    public function test_should_consider_different_uuid(): void
    {
        $userIdBeta = new UserId('845b5465-f54f-74d9-a896-231654654664');
        $this->assertFalse($this->userId->equals($userIdBeta));
    }

    public function test_should_generate_a_valid_uuid(): void
    {
        $userId = UserId::generate();
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $userId->toString()
        );
    }

    public function test_should_generate_unique_uuids(): void
    {
        $first  = UserId::generate();
        $second = UserId::generate();

        $this->assertFalse($first->equals($second));
    }
}

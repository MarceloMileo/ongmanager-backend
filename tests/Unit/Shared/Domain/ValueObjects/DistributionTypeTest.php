<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Domain\ValueObjects;

use PHPUnit\Framework\TestCase;
use App\Contexts\Shared\Domain\ValueObjects\DistributionType;

class DistributionTypeTest extends TestCase
{
    public function test_should_have_correct_value_for_fixed_amount(): void
    {
        $this->assertSame('fixed_amount', DistributionType::FIXED_AMOUNT->value);
    }

    public function test_should_have_correct_value_for_percentage(): void
    {
        $this->assertSame('percentage', DistributionType::PERCENTAGE->value);
    }

    public function test_should_restore_from_string_value(): void
    {
        $status = DistributionType::from('fixed_amount');
        $this->assertSame(DistributionType::FIXED_AMOUNT, $status);
    }

    public function test_should_reject_invalid_value(): void
    {
        $this->expectException(\ValueError::class);
        DistributionType::from('popcorn');
    }
}

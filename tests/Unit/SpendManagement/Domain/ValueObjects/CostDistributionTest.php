<?php

declare(strict_types=1);

namespace Tests\Unit\SpendManagement\Domain\ValueObjects;

use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

use App\Contexts\Shared\Domain\ValueObjects\Money;
use App\Contexts\Shared\Domain\ValueObjects\ProjectId;
use App\Contexts\SpendManagement\Domain\ValueObjects\CostDistribution;

class CostDistributionTest extends TestCase
{
    private CostDistribution $costDistribution;

    protected function setUp(): void
    {
        $projectId = new ProjectId('550e8400-e29b-41d4-a716-446655440000');
        $allocatedAmount = new Money(1500, 'USD');
        $proportionInBasisPoints = 3333;

        $this->costDistribution = new CostDistribution(
            $projectId,
            $allocatedAmount,
            $proportionInBasisPoints
        );
    }
    public function test_should_create_instance_successfully(): void
    {
        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', $this->costDistribution->getProjectId()->toString());
        $this->assertSame(1500, $this->costDistribution->getAllocatedAmount()->getAmountInCents());
        $this->assertSame(3333, $this->costDistribution->getproportionInbasisPoints());
    }

    public function test_should_reject_zero_proportion_in_basis_points(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CostDistribution(
            new ProjectId('550e8400-e29b-41d4-a716-446655440000'),
            new Money(1500, 'USD'),
            0
        );
    }

    public function test_should_reject_negative_proportion_in_basis_points(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CostDistribution(
            new ProjectId('550e8400-e29b-41d4-a716-446655440000'),
            new Money(1500, 'USD'),
            -3333
        );
    }

    public function test_should_reject_above_100_percent_proportion_in_basis_points(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CostDistribution(
            new ProjectId('550e8400-e29b-41d4-a716-446655440000'),
            new Money(1500, 'USD'),
            10001
        );
    }

    public function test_should_reject_zero_allocated_amount(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CostDistribution(
            new ProjectId('550e8400-e29b-41d4-a716-446655440000'),
            new Money(0, 'USD'),
            3333
        );
    }

    public function test_should_reject_negative_allocated_amount(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CostDistribution(
            new ProjectId('550e8400-e29b-41d4-a716-446655440000'),
            new Money(-1500, 'USD'),
            3333
        );
    }
}

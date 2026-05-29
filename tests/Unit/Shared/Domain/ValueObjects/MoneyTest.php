<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Domain\ValueObjects;

use App\Contexts\Shared\Domain\ValueObjects\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_should_create_instance_successfully(): void
    {
        $money = new Money(1500, 'BRL');

        $this->assertSame(1500, $money->getAmountInCents());
        $this->assertSame('BRL', $money->getCurrency());
    }

    public function test_should_reject_invalid_currency_format(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Money(1500, 'INVALID');
    }

    public function test_addition_operations_must_be_immutable(): void
    {
        $m1 = new Money(1000, 'BRL'); // BRL 10.00
        $m2 = new Money(550, 'BRL');  // BRL 5.50

        $result = $m1->add($m2);

        // The result must contain the correct sum
        $this->assertSame(1550, $result->getAmountInCents());
        // Original objects must not be altered (Immutability)
        $this->assertSame(1000, $m1->getAmountInCents());
        $this->assertSame(550, $m2->getAmountInCents());
    }

    public function test_should_block_operations_between_different_currencies(): void
    {
        $m1 = new Money(1000, 'BRL');
        $m2 = new Money(1000, 'USD');

        $this->expectException(InvalidArgumentException::class);
        $m1->add($m2);
    }

    public function test_should_multiply_with_correct_rounding(): void
    {
        $money = new Money(100, 'BRL'); // BRL 1.00 (100 cents)

        // 100 * 1.155 = 115.5 -> Round up -> 116
        $result = $money->multiply(1.155);

        $this->assertSame(116, $result->getAmountInCents());
    }

    public function test_should_allocate_proportionally_without_losing_cents(): void
    {
        $money = new Money(100, 'BRL'); // BRL 1.00 (100 cents)

        // Try to divide BRL 1.00 equally into 3 parts (33.333... cents each)
        // The algorithm must return: 34 cents, 33 cents, and 33 cents.
        // Total sum of the shares must be exactly 100 cents.
        $shares = $money->allocate([1, 1, 1]);

        $this->assertCount(3, $shares);
        $this->assertSame(34, $shares[0]->getAmountInCents());
        $this->assertSame(33, $shares[1]->getAmountInCents());
        $this->assertSame(33, $shares[2]->getAmountInCents());

        // Consistency validation of the accumulated total
        $sum = $shares[0]->add($shares[1])->add($shares[2]);
        $this->assertSame(100, $sum->getAmountInCents());
    }
}

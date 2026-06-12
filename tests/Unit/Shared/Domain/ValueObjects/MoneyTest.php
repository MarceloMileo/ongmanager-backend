<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Domain\ValueObjects;

use App\Contexts\Shared\Domain\ValueObjects\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    // ─────────────────────────────────────────────
    // Criação válida
    // ─────────────────────────────────────────────

    public function test_should_create_instance_successfully(): void
    {
        $money = new Money(1500, 'BRL');

        $this->assertSame(1500, $money->getAmountInCents());
        $this->assertSame('BRL', $money->getCurrency());
    }

    public function test_should_normalize_currency_to_uppercase(): void
    {
        $money = new Money(1500, 'brl');

        $this->assertSame('BRL', $money->getCurrency());
    }

    public function test_should_allow_zero_amount(): void
    {
        $money = new Money(0, 'BRL');

        $this->assertSame(0, $money->getAmountInCents());
    }

    /**
     * Valores negativos são válidos no domínio — representam estornos e ajustes contábeis.
     * Ex: uma linha de despesa estornada pode resultar em Money(-500, 'BRL').
     */
    public function test_should_allow_negative_amount(): void
    {
        $money = new Money(-500, 'BRL');

        $this->assertSame(-500, $money->getAmountInCents());
    }

    // ─────────────────────────────────────────────
    // Validações do construtor
    // ─────────────────────────────────────────────

    public function test_should_reject_invalid_currency_format(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Money(1500, 'INVALID');
    }

    public function test_should_reject_empty_currency(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Money(1500, '');
    }

    public function test_should_reject_currency_with_two_characters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Money(1500, 'BR');
    }

    // ─────────────────────────────────────────────
    // Igualdade (equals)
    // ─────────────────────────────────────────────

    public function test_should_consider_equal_money_with_same_amount_and_currency(): void
    {
        $a = new Money(1000, 'BRL');
        $b = new Money(1000, 'BRL');

        $this->assertTrue($a->equals($b));
    }

    public function test_should_consider_different_money_with_different_amounts(): void
    {
        $a = new Money(1000, 'BRL');
        $b = new Money(1001, 'BRL');

        $this->assertFalse($a->equals($b));
    }

    public function test_should_consider_different_money_with_different_currencies(): void
    {
        $a = new Money(1000, 'BRL');
        $b = new Money(1000, 'USD');

        $this->assertFalse($a->equals($b));
    }

    // ─────────────────────────────────────────────
    // Adição
    // ─────────────────────────────────────────────

    public function test_addition_should_return_correct_sum(): void
    {
        $m1 = new Money(1000, 'BRL');
        $m2 = new Money(550, 'BRL');

        $result = $m1->add($m2);

        $this->assertSame(1550, $result->getAmountInCents());
    }

    public function test_addition_must_be_immutable(): void
    {
        $m1 = new Money(1000, 'BRL');
        $m2 = new Money(550, 'BRL');

        $m1->add($m2);

        // Os originais não devem ser alterados
        $this->assertSame(1000, $m1->getAmountInCents());
        $this->assertSame(550, $m2->getAmountInCents());
    }

    public function test_addition_should_block_different_currencies(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $m1 = new Money(1000, 'BRL');
        $m2 = new Money(1000, 'USD');
        $m1->add($m2);
    }

    // ─────────────────────────────────────────────
    // Subtração
    // ─────────────────────────────────────────────

    public function test_subtraction_should_return_correct_result(): void
    {
        $m1 = new Money(1000, 'BRL');
        $m2 = new Money(400, 'BRL');

        $result = $m1->subtract($m2);

        $this->assertSame(600, $result->getAmountInCents());
    }

    /**
     * Subtração resultando em negativo é válida — representa saldo devedor,
     * estorno ou ajuste contábil no domínio das ONGs.
     */
    public function test_subtraction_resulting_in_negative_should_be_allowed(): void
    {
        $m1 = new Money(50, 'BRL');
        $m2 = new Money(100, 'BRL');

        $result = $m1->subtract($m2);

        $this->assertSame(-50, $result->getAmountInCents());
    }

    public function test_subtraction_should_block_different_currencies(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $m1 = new Money(1000, 'BRL');
        $m2 = new Money(1000, 'USD');
        $m1->subtract($m2);
    }

    // ─────────────────────────────────────────────
    // Multiplicação
    // ─────────────────────────────────────────────

    public function test_should_multiply_with_correct_rounding(): void
    {
        // 100 * 1.155 = 115.5 → HALF_UP → 116
        $money  = new Money(100, 'BRL');
        $result = $money->multiply(1.155);

        $this->assertSame(116, $result->getAmountInCents());
    }

    public function test_multiply_must_be_immutable(): void
    {
        $money = new Money(100, 'BRL');
        $money->multiply(1.5);

        $this->assertSame(100, $money->getAmountInCents());
    }

    // ─────────────────────────────────────────────
    // Alocação (algoritmo de Fowler)
    // ─────────────────────────────────────────────

    public function test_should_allocate_proportionally_without_losing_cents(): void
    {
        // BRL 1,00 dividido em 3 partes iguais:
        // 100 / 3 = 33.333... → 34 + 33 + 33 = 100 (sem perda de centavos)
        $money  = new Money(100, 'BRL');
        $shares = $money->allocate([1, 1, 1]);

        $this->assertCount(3, $shares);
        $this->assertSame(34, $shares[0]->getAmountInCents());
        $this->assertSame(33, $shares[1]->getAmountInCents());
        $this->assertSame(33, $shares[2]->getAmountInCents());
    }

    public function test_allocate_sum_must_equal_original_amount(): void
    {
        $money  = new Money(100, 'BRL');
        $shares = $money->allocate([1, 1, 1]);

        $sum = $shares[0]->add($shares[1])->add($shares[2]);

        $this->assertSame(100, $sum->getAmountInCents());
    }

    public function test_should_allocate_with_asymmetric_ratios(): void
    {
        // BRL 1,00 dividido em proporção 70/30:
        // 70% = 70 cents, 30% = 30 cents
        $money  = new Money(100, 'BRL');
        $shares = $money->allocate([70, 30]);

        $this->assertSame(70, $shares[0]->getAmountInCents());
        $this->assertSame(30, $shares[1]->getAmountInCents());
    }

    public function test_allocate_should_reject_empty_ratios(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $money = new Money(100, 'BRL');
        $money->allocate([]);
    }

    public function test_allocate_should_reject_zero_sum_ratios(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $money = new Money(100, 'BRL');
        $money->allocate([0, 0, 0]);
    }

    // ─────────────────────────────────────────────
    // Comparações
    // ─────────────────────────────────────────────

    public function test_should_identify_greater_amount(): void
    {
        $m1 = new Money(1000, 'BRL');
        $m2 = new Money(500, 'BRL');

        $this->assertTrue($m1->isGreaterThan($m2));
        $this->assertFalse($m2->isGreaterThan($m1));
    }

    public function test_should_identify_lesser_amount(): void
    {
        $m1 = new Money(500, 'BRL');
        $m2 = new Money(1000, 'BRL');

        $this->assertTrue($m1->isLessThan($m2));
        $this->assertFalse($m2->isLessThan($m1));
    }

    public function test_comparison_should_block_different_currencies(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $m1 = new Money(1000, 'BRL');
        $m2 = new Money(1000, 'USD');
        $m1->isGreaterThan($m2);
    }
}

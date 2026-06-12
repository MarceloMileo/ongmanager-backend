<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Domain\ValueObjects;

use App\Contexts\Shared\Domain\ValueObjects\ExchangeRate;
use App\Contexts\Shared\Domain\ValueObjects\Money;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ExchangeRateTest extends TestCase
{
    // ─────────────────────────────────────────────
    // Helper: retorna uma data UTC válida para os testes
    // ─────────────────────────────────────────────
    private function utcDate(string $date = '2026-05-19'): DateTimeImmutable
    {
        return new DateTimeImmutable($date, new DateTimeZone('UTC'));
    }

    // ─────────────────────────────────────────────
    // Criação válida
    // ─────────────────────────────────────────────

    public function test_should_create_exchange_rate_successfully(): void
    {
        $date = $this->utcDate();
        $exchange = new ExchangeRate('USD', 'CLP', '900.50', $date);

        $this->assertSame('USD', $exchange->getSourceCurrency());
        $this->assertSame('CLP', $exchange->getTargetCurrency());
        $this->assertSame('900.50', $exchange->getRate());
        $this->assertSame($date, $exchange->getDate());
    }

    // ─────────────────────────────────────────────
    // Validações do construtor
    // ─────────────────────────────────────────────

    public function test_should_reject_invalid_source_currency(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ExchangeRate('INVALID', 'CLP', '900', $this->utcDate());
    }

    public function test_should_reject_invalid_target_currency(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ExchangeRate('USD', 'INVALID', '900', $this->utcDate());
    }

    public function test_should_reject_identical_currencies(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ExchangeRate('USD', 'USD', '1.0', $this->utcDate());
    }

    public function test_should_reject_zero_rate(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ExchangeRate('USD', 'CLP', '0', $this->utcDate());
    }

    public function test_should_reject_negative_rate(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ExchangeRate('USD', 'CLP', '-1.0', $this->utcDate());
    }

    /**
     * ADR-003: A data da taxa de câmbio deve estar em UTC.
     * Uma data em timezone local deve ser rejeitada pelo construtor.
     */
    public function test_should_reject_non_utc_date(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $localDate = new DateTimeImmutable('2026-05-19', new DateTimeZone('America/Sao_Paulo'));
        new ExchangeRate('USD', 'BRL', '5.70', $localDate);
    }

    public function test_should_reject_non_utc_date_from_santiago(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $localDate = new DateTimeImmutable('2026-05-19', new DateTimeZone('America/Santiago'));
        new ExchangeRate('USD', 'CLP', '900', $localDate);
    }

    // ─────────────────────────────────────────────
    // Conversão
    // ─────────────────────────────────────────────

    public function test_should_convert_money_successfully(): void
    {
        // 1 USD = 900.50 CLP
        // $10.00 USD = 1000 cents
        // 1000 * 900.50 = 900.500 cents CLP = 9.005,00 CLP
        $exchange = new ExchangeRate('USD', 'CLP', '900.50', $this->utcDate());
        $usdMoney  = new Money(1000, 'USD');

        $clpMoney = $exchange->convert($usdMoney);

        $this->assertSame('CLP', $clpMoney->getCurrency());
        $this->assertSame(900500, $clpMoney->getAmountInCents());
    }

    public function test_should_apply_correct_rounding_on_conversion(): void
    {
        // 1 USD = 0.9155 EUR
        // $1.00 USD = 100 cents
        // 100 * 0.9155 = 91.55 cents → arredonda HALF_UP → 92 cents
        $exchange = new ExchangeRate('USD', 'EUR', '0.9155', $this->utcDate());
        $usdMoney  = new Money(100, 'USD');

        $eurMoney = $exchange->convert($usdMoney);

        $this->assertSame('EUR', $eurMoney->getCurrency());
        $this->assertSame(92, $eurMoney->getAmountInCents());
    }

    public function test_should_prevent_conversion_with_mismatched_source_currency(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $exchange = new ExchangeRate('USD', 'CLP', '900', $this->utcDate());
        $brlMoney  = new Money(1000, 'BRL');

        $exchange->convert($brlMoney);
    }

    // ─────────────────────────────────────────────
    // Igualdade (equals)
    // ─────────────────────────────────────────────

    public function test_should_consider_equal_exchange_rates_with_same_values(): void
    {
        $date = $this->utcDate();
        $a    = new ExchangeRate('USD', 'BRL', '5.70', $date);
        $b    = new ExchangeRate('USD', 'BRL', '5.70', $date);

        $this->assertTrue($a->equals($b));
    }

    public function test_should_consider_different_exchange_rates_with_different_rate_values(): void
    {
        $date = $this->utcDate();
        $a    = new ExchangeRate('USD', 'BRL', '5.70', $date);
        $b    = new ExchangeRate('USD', 'BRL', '5.71', $date);

        $this->assertFalse($a->equals($b));
    }

    public function test_should_consider_different_exchange_rates_with_different_dates(): void
    {
        $a = new ExchangeRate('USD', 'BRL', '5.70', $this->utcDate('2026-05-19'));
        $b = new ExchangeRate('USD', 'BRL', '5.70', $this->utcDate('2026-05-20'));

        $this->assertFalse($a->equals($b));
    }

    public function test_should_consider_different_exchange_rates_with_different_currencies(): void
    {
        $date = $this->utcDate();
        $a    = new ExchangeRate('USD', 'BRL', '5.70', $date);
        $b    = new ExchangeRate('USD', 'CLP', '900', $date);

        $this->assertFalse($a->equals($b));
    }
}

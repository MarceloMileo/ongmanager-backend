<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Domain\ValueObjects;

use App\Contexts\Shared\Domain\ValueObjects\ExchangeRate;
use App\Contexts\Shared\Domain\ValueObjects\Money;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ExchangeRateTest extends TestCase
{
    public function test_should_create_exchage_rate_successfully(): void
    {
        $date = new DateTimeImmutable('2026-05-19');
        $exchange = new ExchangeRate('USD', 'CLP', '900.50', $date);

        $this->assertSame('USD', $exchange->getSourceCurrency());
        $this->assertSame('CLP', $exchange->getTargetCurrency());
        $this->assertSame('900.50', $exchange->getRate());
        $this->assertSame($date, $exchange->getDate());
    }

    public function test_should_reject_invalid_currencies(): void
    {
        $date = new DateTimeImmutable('2026-05-19');

        $this->expectException(InvalidArgumentException::class);
        new ExchangeRate('INVALID', 'CLP', 900, $date);
    }

    public function test_should_reject_identical_currencies(): void
    {
        $date = new DateTimeImmutable('2026-05-19');

        $this->expectException(InvalidArgumentException::class);
        new ExchangeRate('USD', 'USD', 1.0, $date);
    }

    public function test_should_reject_zero_or_negativ_rates(): void
    {
        $date = new DateTimeImmutable('2026-05-19');

        $this->expectException(InvalidArgumentException::class);
        new ExchangeRate('USD', 'CLP', 0, $date);
    }

    public function test_should_convert_money_successfully_with_correct_round(): void
    {
        $date = new DateTimeImmutable('2026-05-19');

        // 1 USD = 900.50 CLP
        $exchange = new ExchangeRate('USD', 'CLP', '900.50', $date);

        //$10.00 USD (1000 cents)
        $usdMoney = new Money(1000, 'USD');

        // Conversion: 1000 cents * 900.50 = 900500 cents (9,005.00 CLP)
        $clpMoney = $exchange->convert($usdMoney);

        $this->assertSame('CLP', $clpMoney->getCurrency());
        $this->assertSame(900500, $clpMoney->getAmountInCents());
    }

    public function test_should_apply_correct_decimal_rounding_on_conversion(): void
    {
        $date = new DateTimeImmutable('2026-05-19');

        // 1 USD = 0.9155 EUR
        $exchange = new ExchangeRate('USD', 'EUR', '0.9155', $date);

        // $1.00 USD (100 cents)
        $usdMoney = new Money(100, 'USD');

        // 100 * 0.9155 = 91.55 cents -> round half up -> 92 EUR cents
        $eurMoney = $exchange->convert($usdMoney);

        $this->assertSame('EUR', $eurMoney->getCurrency());
        $this->assertSame(92, $eurMoney->getAmountInCents());
    }

    public function test_should_prevent_conversion_with_mismatched_source_currency(): void
    {
        $date = new DateTimeImmutable('2026-05-19');
        $exchange = new ExchangeRate('USD', 'CLP', '900', $date);

        // Trying to convert BRL using a USD-CLP converter
        $brlMoney = new Money(1000, 'BRL');

        $this->expectException(InvalidArgumentException::class);
        $exchange->convert($brlMoney);
    }
}

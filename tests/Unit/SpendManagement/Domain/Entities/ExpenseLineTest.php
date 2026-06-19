<?php

declare(strict_types=1);

namespace Tests\Unit\SpendManagement\Domain\Entities;

use PHPUnit\Framework\TestCase;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

use App\Contexts\Shared\Domain\ValueObjects\DistributionType;
use App\Contexts\Shared\Domain\ValueObjects\ExchangeRate;
use App\Contexts\SpendManagement\Domain\Entities\ExpenseLine;
use App\Contexts\Shared\Domain\ValueObjects\ProjectId;
use App\Contexts\Shared\Domain\ValueObjects\Money;

class ExpenseLineTest extends TestCase
{
    // ─────────────────────────────────────────────
    // Helper: retorna uma data UTC válida para os testes
    // ─────────────────────────────────────────────
    private function utcDate(string $date = '2026-05-19'): DateTimeImmutable
    {
        return new DateTimeImmutable($date, new DateTimeZone('UTC'));
    }

    public function test_should_create_instance_successfully(): void
    {
        $date = $this->utcDate();

        $projectId = new ProjectId('550e8400-e29b-41d4-a716-446655440000');
        $money = new Money(1500, 'USD');
        $exchangeRate = new ExchangeRate('USD', 'CLP', '900.50', $date);
        $distributionType = DistributionType::FIXED_AMOUNT;

        $expenseLine = new ExpenseLine(
            '652e8745-d48b-41a4-b587-448955445854',
            $projectId,
            $money,
            $distributionType,
            $exchangeRate
        );

        $this->assertSame('652e8745-d48b-41a4-b587-448955445854', $expenseLine->getId());
        $this->assertSame(1500, $expenseLine->getAmount()->getAmountInCents());
        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', $expenseLine->getProjectId()->toString());
    }

    public function test_should_create_without_exchange_rate_successfully(): void
    {
        $projectId = new ProjectId('550e8400-e29b-41d4-a716-446655440000');
        $money = new Money(1500, 'USD');
        $distributionType = DistributionType::FIXED_AMOUNT;

        $expenseLine = new ExpenseLine(
            '652e8745-d48b-41a4-b587-448955445854',
            $projectId,
            $money,
            $distributionType
        );

        $this->assertSame('652e8745-d48b-41a4-b587-448955445854', $expenseLine->getId());
        $this->assertSame(1500, $expenseLine->getAmount()->getAmountInCents());
        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', $expenseLine->getProjectId()->toString());
    }

    public function test_should_consider_different_expense_lines(): void
    {
        $projectId = new ProjectId('550e8400-e29b-41d4-a716-446655440000');
        $money = new Money(1500, 'USD');
        $distributionType = DistributionType::FIXED_AMOUNT;

        $expenseLineAlpha = new ExpenseLine(
            '652e8745-d48b-41a4-b587-448955445854',
            $projectId,
            $money,
            $distributionType
        );

        $expenseLineBravo = new ExpenseLine(
            '652e8745-d48b-41a4-b587-448955445855',
            $projectId,
            $money,
            $distributionType
        );

        $this->assertFalse($expenseLineAlpha->equals($expenseLineBravo));
    }

    public function test_should_reject_invalid_uuid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $date = $this->utcDate();

        $projectId = new ProjectId('550e8400-e29b-41d4-a716-446655440000');
        $money = new Money(1500, 'USD');
        $exchangeRate = new ExchangeRate('USD', 'CLP', '900.50', $date);
        $distributionType = DistributionType::FIXED_AMOUNT;

        $expenseLine = new ExpenseLine(
            'not-a-valid-uuid',
            $projectId,
            $money,
            $distributionType,
            $exchangeRate
        );
    }

    public function test_should_change_amount(): void
    {
        $date = $this->utcDate();

        $projectId = new ProjectId('550e8400-e29b-41d4-a716-446655440000');
        $money = new Money(1500, 'USD');
        $exchangeRate = new ExchangeRate('USD', 'CLP', '900.50', $date);
        $distributionType = DistributionType::FIXED_AMOUNT;

        $expenseLine = new ExpenseLine(
            '652e8745-d48b-41a4-b587-448955445854',
            $projectId,
            $money,
            $distributionType,
            $exchangeRate
        );

        $expenseLine->changeAmount(new Money(2000, 'BRL'));

        $this->assertSame(2000, $expenseLine->getAmount()->getAmountInCents());
    }

    public function test_should_reject_change_invalid_amount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $date = $this->utcDate();

        $projectId = new ProjectId('550e8400-e29b-41d4-a716-446655440000');
        $money = new Money(1500, 'USD');
        $exchangeRate = new ExchangeRate('USD', 'CLP', '900.50', $date);
        $distributionType = DistributionType::FIXED_AMOUNT;

        $expenseLine = new ExpenseLine(
            '652e8745-d48b-41a4-b587-448955445854',
            $projectId,
            $money,
            $distributionType,
            $exchangeRate
        );

        $expenseLine->changeAmount(new Money(-2000, 'BRL'));
    }

    public function test_should_change_project(): void
    {
        $date = $this->utcDate();

        $projectId = new ProjectId('550e8400-e29b-41d4-a716-446655440000');
        $money = new Money(1500, 'USD');
        $exchangeRate = new ExchangeRate('USD', 'CLP', '900.50', $date);
        $distributionType = DistributionType::FIXED_AMOUNT;

        $expenseLine = new ExpenseLine(
            '652e8745-d48b-41a4-b587-448955445854',
            $projectId,
            $money,
            $distributionType,
            $exchangeRate
        );

        $expenseLine->changeProject(new ProjectId('550e8400-e29b-41d4-a716-446655440001'));

        $this->assertSame('550e8400-e29b-41d4-a716-446655440001', $expenseLine->getProjectId()->toString());
    }

    public function test_should_change_exchange_rate(): void
    {
        $date = $this->utcDate();

        $projectId = new ProjectId('550e8400-e29b-41d4-a716-446655440000');
        $money = new Money(1500, 'USD');
        $exchangeRate = new ExchangeRate('USD', 'CLP', '900.50', $date);
        $distributionType = DistributionType::FIXED_AMOUNT;

        $expenseLine = new ExpenseLine(
            '652e8745-d48b-41a4-b587-448955445854',
            $projectId,
            $money,
            $distributionType,
            $exchangeRate
        );

        $expenseLine->changeExchangeRate(new ExchangeRate('USD', 'BRL', '5.50', $date));

        $this->assertSame('USD', $expenseLine->getExchangeRate()->getSourceCurrency());
        $this->assertSame('BRL', $expenseLine->getExchangeRate()->getTargetCurrency());
        $this->assertSame('5.50', $expenseLine->getExchangeRate()->getRate());
    }
}

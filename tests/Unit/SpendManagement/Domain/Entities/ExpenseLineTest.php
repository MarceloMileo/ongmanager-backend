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
    private ExpenseLine $expenseLine;
    private ProjectId $projectId;

    // ─────────────────────────────────────────────
    // Helper: retorna uma data UTC válida para os testes
    // ─────────────────────────────────────────────
    private function utcDate(string $date = '2026-05-19'): DateTimeImmutable
    {
        return new DateTimeImmutable($date, new DateTimeZone('UTC'));
    }

    protected function setUp(): void
    {
        $date = $this->utcDate();

        $this->projectId = new ProjectId('550e8400-e29b-41d4-a716-446655440000');
        $money = new Money(1500, 'USD');
        $exchangeRate = new ExchangeRate('USD', 'CLP', '900.50', $date);
        $distributionType = DistributionType::FIXED_AMOUNT;

        $this->expenseLine = new ExpenseLine(
            '652e8745-d48b-41a4-b587-448955445854',
            $this->projectId,
            $money,
            $distributionType,
            $exchangeRate
        );
    }

    public function test_should_create_instance_successfully(): void
    {

        $this->assertSame('652e8745-d48b-41a4-b587-448955445854', $this->expenseLine->getId());
        $this->assertSame(1500, $this->expenseLine->getAmount()->getAmountInCents());
        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', $this->expenseLine->getProjectId()->toString());
    }

    public function test_should_create_without_exchange_rate_successfully(): void
    {
        $expenseLine = new ExpenseLine(
            '652e8745-d48b-41a4-b587-448955445854',
            new ProjectId('550e8400-e29b-41d4-a716-446655440000'),
            new Money(1500, 'USD'),
            DistributionType::FIXED_AMOUNT
        );

        $this->assertSame('652e8745-d48b-41a4-b587-448955445854', $expenseLine->getId());
        $this->assertSame(1500, $expenseLine->getAmount()->getAmountInCents());
        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', $expenseLine->getProjectId()->toString());
    }

    public function test_should_consider_different_expense_lines(): void
    {
        $expenseLineBravo = new ExpenseLine(
            '652e8745-d48b-41a4-b587-448955445855',
            new ProjectId('550e8400-e29b-41d4-a716-446655440000'),
            new Money(1500, 'USD'),
            DistributionType::FIXED_AMOUNT
        );

        $this->assertFalse($this->expenseLine->equals($expenseLineBravo));
    }

    public function test_should_reject_invalid_uuid(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ExpenseLine(
            'not-a-valid-uuid',
            $this->projectId,
            new Money(1500, 'USD'),
            DistributionType::FIXED_AMOUNT
        );
    }

    public function test_should_change_amount(): void
    {
        $this->expenseLine->changeAmount(new Money(2000, 'BRL'));
        $this->assertSame(2000, $this->expenseLine->getAmount()->getAmountInCents());
    }

    public function test_should_reject_change_invalid_amount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expenseLine->changeAmount(new Money(-2000, 'BRL'));
    }

    public function test_should_change_project(): void
    {
        $this->expenseLine->changeProject(new ProjectId('550e8400-e29b-41d4-a716-446655440001'));
        $this->assertSame('550e8400-e29b-41d4-a716-446655440001', $this->expenseLine->getProjectId()->toString());
    }

    public function test_should_change_exchange_rate(): void
    {
        $date = $this->utcDate();

        $this->expenseLine->changeExchangeRate(new ExchangeRate('USD', 'BRL', '5.50', $date));

        $this->assertSame('USD', $this->expenseLine->getExchangeRate()->getSourceCurrency());
        $this->assertSame('BRL', $this->expenseLine->getExchangeRate()->getTargetCurrency());
        $this->assertSame('5.50', $this->expenseLine->getExchangeRate()->getRate());
    }
}

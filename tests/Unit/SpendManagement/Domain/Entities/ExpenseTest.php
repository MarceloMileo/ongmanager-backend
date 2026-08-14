<?php

declare(strict_types=1);

namespace Tests\Unit\SpendManagement\Domain\Entities;

use PHPUnit\Framework\TestCase;
use InvalidArgumentException;
use DateTimeImmutable;
use DateTimeZone;

use App\Contexts\Shared\Domain\ValueObjects\ExpenseStatus;
use App\Contexts\Shared\Domain\ValueObjects\Receipt;
use App\Contexts\Shared\Domain\ValueObjects\Money;
use App\Contexts\Shared\Domain\ValueObjects\UserId;
use App\Contexts\Shared\Domain\ValueObjects\ProjectId;
use App\Contexts\Shared\Domain\ValueObjects\DistributionType;

use App\Contexts\SpendManagement\Domain\Entities\ExpenseLine;
use App\Contexts\SpendManagement\Domain\Entities\Expense;

class ExpenseTest extends TestCase
{
    private Expense $expense;
    private Receipt $receipt;
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

        $this->receipt = new Receipt('caminho/arquivo', new Money(1500, 'USD'), '123456', '19694231896', $date);
        $totalAmount = new Money(1500, 'USD');
        $userId = new UserId('652e8745-d48b-41a4-b587-448955445854');

        $this->expense = new Expense(
            '550e8400-e29b-41d4-a716-446655440000',
            $this->receipt,
            $totalAmount,
            $userId
        );
    }

    public function test_should_create_instance_successfully(): void
    {

        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', $this->expense->getId());
        $this->assertSame(1500, $this->expense->getTotalAmount()->getAmountInCents());
        $this->assertSame('652e8745-d48b-41a4-b587-448955445854', $this->expense->getSubmitterId()->toString());
        $this->assertSame(ExpenseStatus::DRAFT, $this->expense->getStatus());
    }

    public function test_should_reject_zero_total_amount(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Expense(
            '550e8400-e29b-41d4-a716-446655440000',
            $this->receipt,
            new Money(0, 'USD'),
            new UserId('652e8745-d48b-41a4-b587-448955445854')
        );
    }

    public function test_should_reject_negative_total_amount(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Expense(
            '550e8400-e29b-41d4-a716-446655440000',
            $this->receipt,
            new Money(-1500, 'USD'),
            new UserId('652e8745-d48b-41a4-b587-448955445854')
        );
    }

    public function test_should_reject_invalid_uuid(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Expense(
            'not-a-valid-uuid',
            $this->receipt,
            new Money(1500, 'USD'),
            new UserId('652e8745-d48b-41a4-b587-448955445854')
        );
    }

    public function test_should_add_expense_line_successfully(): void
    {
        $line = new ExpenseLine(
            '652e8745-d48b-41a4-b587-448955445854',
            new ProjectId('550e8400-e29b-41d4-a716-446655440000'),
            new Money(1500, 'USD'),
            DistributionType::FIXED_AMOUNT
        );

        $this->expense->addLine($line);

        $this->assertCount(1, $this->expense->getLines());
    }

    public function test_should_reject_add_line_when_not_draft(): void
    {
        $line = new ExpenseLine(
            '652e8745-d48b-41a4-b587-448955445854',
            new ProjectId('550e8400-e29b-41d4-a716-446655440000'),
            new Money(1500, 'USD'),
            DistributionType::FIXED_AMOUNT
        );

        $this->expense->addLine($line);
        $this->expense->submit();

        $this->expectException(InvalidArgumentException::class);

        $this->expense->addLine(new ExpenseLine(
            '845b5465-f54f-74d9-a896-231654654664',
            new ProjectId('550e8400-e29b-41d4-a716-446655440000'),
            new Money(1500, 'USD'),
            DistributionType::FIXED_AMOUNT
        ));
    }

    public function test_should_reject_submit_without_lines(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expense->submit();
    }

    public function test_should_reject_approve_when_approver_is_submitter(): void
    {
        $approverId = new UserId('652e8745-d48b-41a4-b587-448955445854');
        $this->expectException(InvalidArgumentException::class);

        $this->expense->approve($approverId);
    }

    public function test_should_reject_mismatched_currency_between_receipt_and_total_amount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $date = $this->utcDate();

        $this->receipt = new Receipt('caminho/arquivo', new Money(1500, 'CLP'), '123456', '19694231896', $date);
        $totalAmount = new Money(1500, 'USD');
        $userId = new UserId('652e8745-d48b-41a4-b587-448955445854');

        $this->expense = new Expense(
            '550e8400-e29b-41d4-a716-446655440000',
            $this->receipt,
            $totalAmount,
            $userId
        );
    }
}

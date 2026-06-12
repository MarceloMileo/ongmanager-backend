<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Domain\ValueObjects;

use PHPUnit\Framework\TestCase;
use App\Contexts\Shared\Domain\ValueObjects\ExpenseStatus;

class ExpenseStatusTest extends TestCase
{
    public function test_should_have_correct_value_for_draft(): void
    {
        $this->assertSame('draft', ExpenseStatus::DRAFT->value);
    }

    public function test_should_have_correct_value_for_submitted(): void
    {
        $this->assertSame('submitted', ExpenseStatus::SUBMITTED->value);
    }

    public function test_should_have_correct_value_for_approved(): void
    {
        $this->assertSame('approved', ExpenseStatus::APPROVED->value);
    }

    public function test_should_have_correct_value_for_rejected(): void
    {
        $this->assertSame('rejected', ExpenseStatus::REJECTED->value);
    }

    public function test_should_have_correct_value_for_paid(): void
    {
        $this->assertSame('paid', ExpenseStatus::PAID->value);
    }

    public function test_should_restore_from_string_value(): void
    {
        $status = ExpenseStatus::from('draft');

        $this->assertSame(ExpenseStatus::DRAFT, $status);
    }

    public function test_should_reject_invalid_value(): void
    {
        $this->expectException(\ValueError::class);
        ExpenseStatus::from('popcorn');
    }
}

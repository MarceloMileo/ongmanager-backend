<?php

declare(strict_types=1);

namespace App\Contexts\SpendManagement\Application\UseCases\ApproveExpense;

final readonly class ApproveExpenseCommand
{
    public function __construct(
        public string $expenseId,
        public string $approverId
    ) {}
}

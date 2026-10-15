<?php

declare(strict_types=1);

namespace App\Contexts\SpendManagement\Application\UseCases\RejectExpense;

final readonly class RejectExpenseCommand
{
    public function __construct(
        public string $expenseId,
        public string $approverId,
        public string $reason
    ) {}
}

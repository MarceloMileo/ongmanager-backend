<?php

declare(strict_types=1);

namespace App\Contexts\SpendManagement\Application\UseCases\PayExpense;

final readonly class PayExpenseCommand
{
    public function __construct(
        public string $expenseId,
        public string $payerId,
    ) {}
}

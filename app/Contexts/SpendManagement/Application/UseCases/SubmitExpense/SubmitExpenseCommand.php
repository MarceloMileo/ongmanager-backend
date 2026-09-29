<?php

declare(strict_types=1);

namespace App\Contexts\SpendManagement\Application\UseCases\SubmitExpense;

final readonly class SubmitExpenseCommand
{
    public function __construct(
        public string $expenseId,
        public string $submitterId
    ) {}
}

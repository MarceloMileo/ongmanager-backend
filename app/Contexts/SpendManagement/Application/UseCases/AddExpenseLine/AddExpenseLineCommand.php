<?php

declare(strict_types=1);

namespace App\Contexts\SpendManagement\Application\UseCases\AddExpenseLine;

final readonly class AddExpenseLineCommand
{
    public function __construct(
        public string $expenseId,
        public string $projectId,
        public int $amountCents,
        public string $amountCurrency,
        public string $distributionType,
        public string $description,
        public ?string $exchangeRate = null,
        public ?string $exchangeRateSourceCurrency = null,
        public ?string $exchangeRateTargetCurrency = null,
        public ?string $exchangeRateDate = null
    ) {}
}

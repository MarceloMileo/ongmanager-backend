<?php

declare(strict_types=1);

namespace App\Contexts\SpendManagement\Application\UseCases\CreateExpense;

final readonly class CreateExpenseCommand
{
    public function __construct(
        public string $fileReference,
        public int $documentValueCents,
        public string $documentValueCurrency,
        public int $totalAmountCents,
        public string $totalAmountCurrency,
        public string $submitterId,
        public ?string $documentNumber = null,
        public ?string $issuerIdentifier = null,
        public ?string $issuedAt = null,
    ) {}
}

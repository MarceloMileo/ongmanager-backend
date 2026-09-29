<?php

declare(strict_types=1);

namespace App\Contexts\SpendManagement\Domain\Events;

use DateTimeImmutable;

final readonly class ExpenseSubmitted
{
    public function __construct(
        public string $expenseId,
        public string $submitterId,
        public DateTimeImmutable $occurredAt,
    ) {}
}

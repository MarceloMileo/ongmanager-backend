<?php

declare(strict_types=1);

namespace App\Contexts\SpendManagement\Domain\Events;

use DateTimeImmutable;

final readonly class ExpenseRejected
{
    public function __construct(
        public string $expenseId,
        public string $approverId,
        public string $reason,
        public DateTimeImmutable $occurredAt,
    ) {}
}

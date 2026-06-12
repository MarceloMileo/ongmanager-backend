<?php

declare(strict_types=1);

namespace App\Contexts\Shared\Domain\ValueObjects;

enum ExpenseStatus: string
{
    case DRAFT     = 'draft';
    case SUBMITTED = 'submitted';
    case APPROVED  = 'approved';
    case REJECTED  = 'rejected';
    case PAID      = 'paid';
}

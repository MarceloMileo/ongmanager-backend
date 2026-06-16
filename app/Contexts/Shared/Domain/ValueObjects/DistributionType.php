<?php

declare(strict_types=1);

namespace App\Contexts\Shared\Domain\ValueObjects;

enum DistributionType: string
{
    case FIXED_AMOUNT = 'fixed_amount';
    case PERCENTAGE = 'percentage';
}

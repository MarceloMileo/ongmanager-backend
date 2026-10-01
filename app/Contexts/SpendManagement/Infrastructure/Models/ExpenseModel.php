<?php

declare(strict_types=1);

namespace App\Contexts\SpendManagement\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ExpenseModel extends Model
{
    protected $table = 'expenses';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'submitter_id',
        'status',
        'total_amount_cents',
        'total_amount_currency',
    ];

    /** @phpstan-return HasOne<ReceiptModel, ExpenseModel> */
    public function receipt(): HasOne
    {
        return $this->hasOne(ReceiptModel::class, 'expense_id');
    }
}

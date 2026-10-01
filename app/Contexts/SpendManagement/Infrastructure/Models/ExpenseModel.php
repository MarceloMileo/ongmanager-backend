<?php

declare(strict_types=1);

namespace App\Contexts\SpendManagement\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

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
        'total_amount_currency'
    ];

    public function receipt(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ReceiptModel::class, 'expense_id');
    }
}

<?php

declare(strict_types=1);

namespace App\Contexts\SpendManagement\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseLineModel extends Model
{
    protected $table = 'expense_lines';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'expense_id',
        'project_id',
        'amount_cents',
        'amount_currency',
        'description',
        'exchange_rate',
        'exchange_rate_source_currency',
        'exchange_rate_target_currency',
        'exchange_rate_date',
        'distribution_type',
    ];

    /** @return BelongsTo<ExpenseModel, ExpenseLineModel> */
    public function expense(): BelongsTo
    {
        // @phpstan-ignore-next-line
        return $this->belongsTo(ExpenseModel::class, 'expense_id');
    }
}

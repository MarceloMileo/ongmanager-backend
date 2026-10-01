<?php

declare(strict_types=1);

namespace App\Contexts\SpendManagement\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class ReceiptModel extends Model
{
    protected $table = 'expense_receipts';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'expense_id',
        'file_reference',
        'document_value_cents',
        'document_value_currency',
        'document_number',
        'issuer_identifier',
        'issued_at',
    ];
}

<?php

declare(strict_types=1);

namespace App\Contexts\SpendManagement\Infrastructure\Repositories;

use App\Contexts\Shared\Domain\ValueObjects\ExpenseStatus;
use App\Contexts\Shared\Domain\ValueObjects\Money;
use App\Contexts\Shared\Domain\ValueObjects\Receipt;
use App\Contexts\Shared\Domain\ValueObjects\UserId;
use App\Contexts\SpendManagement\Domain\Entities\Expense;
use App\Contexts\SpendManagement\Domain\Repositories\IExpenseRepository;
use App\Contexts\SpendManagement\Infrastructure\Models\ExpenseModel;
use DateTimeImmutable;

class EloquentExpenseRepository implements IExpenseRepository
{
    public function save(Expense $expense): void
    {
        ExpenseModel::updateOrCreate(
            ['id' => $expense->getId()],
            [
                'submitter_id' => $expense->getSubmitterId()->toString(),
                'status' => $expense->getStatus()->value,
                'total_amount_cents' => $expense->getTotalAmount()->getAmountInCents(),
                'total_amount_currency' => $expense->getTotalAmount()->getCurrency(),
            ]
        );
    }

    public function findById(string $id): ?Expense
    {
        $model = ExpenseModel::find($id);

        if (! $model) {
            return null;
        }

        return $this->toDomain($model);
    }

    public function delete(Expense $expense): void
    {
        ExpenseModel::destroy($expense->getId());
    }

    /** @return array<Expense> */
    public function findByStatus(ExpenseStatus $status): array
    {
        return ExpenseModel::where('status', $status->value)
            ->get()
            ->map(fn ($model) => $this->toDomain($model))
            ->toArray();
    }

    /** @return array<Expense> */
    public function findBySubmitter(UserId $id): array
    {
        return ExpenseModel::where('submitter_id', $id->toString())
            ->get()
            ->map(fn ($model) => $this->toDomain($model))
            ->toArray();
    }

    private function toDomain(ExpenseModel $model): Expense
    {
        $receipt = $model->receipt;

        if (! $receipt) {
            throw new \RuntimeException('Expense sem receipt: '.$model->id);
        }

        $domainReceipt = new Receipt(
            $receipt->file_reference,
            new Money($receipt->document_value_cents, $receipt->document_value_currency),
            $receipt->document_number,
            $receipt->issuer_identifier,
            $receipt->issued_at ? new DateTimeImmutable($receipt->issued_at) : null,
        );

        return new Expense(
            $model->id,
            $domainReceipt,
            new Money($model->total_amount_cents, $model->total_amount_currency),
            new UserId($model->submitter_id),
        );
    }
}

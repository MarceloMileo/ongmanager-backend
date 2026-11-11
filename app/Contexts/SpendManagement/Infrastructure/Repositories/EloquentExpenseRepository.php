<?php

declare(strict_types=1);

namespace App\Contexts\SpendManagement\Infrastructure\Repositories;

use App\Contexts\Shared\Domain\ValueObjects\DistributionType;
use App\Contexts\Shared\Domain\ValueObjects\ExpenseStatus;
use App\Contexts\Shared\Domain\ValueObjects\Money;
use App\Contexts\Shared\Domain\ValueObjects\ProjectId;
use App\Contexts\Shared\Domain\ValueObjects\Receipt;
use App\Contexts\Shared\Domain\ValueObjects\UserId;
use App\Contexts\SpendManagement\Domain\Entities\Expense;
use App\Contexts\SpendManagement\Domain\Entities\ExpenseLine;
use App\Contexts\SpendManagement\Domain\Repositories\IExpenseRepository;
use App\Contexts\SpendManagement\Infrastructure\Models\ExpenseLineModel;
use App\Contexts\SpendManagement\Infrastructure\Models\ExpenseModel;
use App\Contexts\SpendManagement\Infrastructure\Models\ReceiptModel;
use DateTimeImmutable;
use Ramsey\Uuid\Uuid;

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

        ReceiptModel::updateOrCreate(
            ['expense_id' => $expense->getId()],
            [
                'id' => Uuid::uuid4()->toString(),
                'file_reference' => $expense->getReceipt()->getFileReference(),
                'document_value_cents' => $expense->getReceipt()->getDocumentValue()->getAmountInCents(),
                'document_value_currency' => $expense->getReceipt()->getDocumentValue()->getCurrency(),
                'document_number' => $expense->getReceipt()->getDocumentNumber(),
                'issuer_identifier' => $expense->getReceipt()->getIssuerIdentifier(),
                'issued_at' => $expense->getReceipt()->getIssuedAt()?->format('Y-m-d H:i:s'),
            ],
        );

        ExpenseLineModel::where('expense_id', $expense->getId())->delete();

        foreach ($expense->getLines() as $line) {
            ExpenseLineModel::create([
                'id' => $line->getId(),
                'expense_id' => $expense->getId(),
                'project_id' => $line->getProjectId()->toString(),
                'amount_cents' => $line->getAmount()->getAmountInCents(),
                'amount_currency' => $line->getAmount()->getCurrency(),
                'description' => $line->getDescription(),
                'exchange_rate' => $line->getExchangeRate()?->getRate(),
                'distribution_type' => $line->getDistributionType()->value,
                'exchange_rate_source_currency' => $line->getExchangeRate()?->getSourceCurrency(),
                'exchange_rate_target_currency' => $line->getExchangeRate()?->getTargetCurrency(),
                'exchange_rate_date' => $line->getExchangeRate()?->getDate()?->format('Y-m-d H:i:s'),
            ]);
        }
    }

    public function findById(string $id): ?Expense
    {
        $model = ExpenseModel::with(['receipt', 'lines'])->find($id);

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
            ->map(fn($model) => $this->toDomain($model))
            ->toArray();
    }

    /** @return array<Expense> */
    public function findBySubmitter(UserId $id): array
    {
        return ExpenseModel::where('submitter_id', $id->toString())
            ->get()
            ->map(fn($model) => $this->toDomain($model))
            ->toArray();
    }

    private function toDomain(ExpenseModel $model): Expense
    {
        $receipt = $model->receipt;

        if (! $receipt) {
            throw new \RuntimeException('Expense sem receipt: ' . $model->id);
        }

        $domainReceipt = new Receipt(
            $receipt->file_reference,
            new Money($receipt->document_value_cents, $receipt->document_value_currency),
            $receipt->document_number,
            $receipt->issuer_identifier,
            $receipt->issued_at ? new DateTimeImmutable($receipt->issued_at) : null,
        );

        $lines = $model->lines->map(function (ExpenseLineModel $lineModel) {
            return new ExpenseLine(
                $lineModel->id,
                $lineModel->description ?? '',
                new ProjectId($lineModel->project_id),
                new Money($lineModel->amount_cents, $lineModel->amount_currency),
                DistributionType::from($lineModel->distribution_type),
            );
        })->all();

        return Expense::reconstitute(
            $model->id,
            $domainReceipt,
            new Money($model->total_amount_cents, $model->total_amount_currency),
            new UserId($model->submitter_id),
            ExpenseStatus::from($model->status),
            $lines,
        );
    }
}

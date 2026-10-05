<?php

declare(strict_types=1);

namespace App\Contexts\SpendManagement\Application\UseCases\CreateExpense;

use App\Contexts\Shared\Domain\ValueObjects\Money;
use App\Contexts\Shared\Domain\ValueObjects\Receipt;
use App\Contexts\Shared\Domain\ValueObjects\UserId;
use App\Contexts\SpendManagement\Domain\Entities\Expense;
use App\Contexts\SpendManagement\Domain\Repositories\IExpenseRepository;
use DateTimeImmutable;
use Ramsey\Uuid\Uuid;

final readonly class CreateExpenseHandler
{
    public function __construct(
        private IExpenseRepository $repository,
    ) {}

    public function handle(CreateExpenseCommand $command): void
    {
        $receipt = new Receipt(
            $command->fileReference,
            new Money($command->documentValueCents, $command->documentValueCurrency),
            $command->documentNumber,
            $command->issuerIdentifier,
            $command->issuedAt ? new DateTimeImmutable($command->issuedAt) : null,
        );

        $expense = new Expense(
            Uuid::uuid4()->toString(),
            $receipt,
            new Money($command->totalAmountCents, $command->totalAmountCurrency),
            new UserId($command->submitterId),
        );

        $this->repository->save($expense);
    }
}

<?php

declare(strict_types=1);

namespace App\Contexts\SpendManagement\Application\UseCases\RejectExpense;

use App\Contexts\Shared\Domain\ValueObjects\UserId;
use App\Contexts\SpendManagement\Domain\Repositories\IExpenseRepository;
use RuntimeException;

final readonly class RejectExpenseHandler
{
    public function __construct(
        private IExpenseRepository $repository,
    ) {}

    public function handle(RejectExpenseCommand $command): void
    {
        $expense = $this->repository->findById($command->expenseId);

        if (! $expense) {
            throw new RuntimeException('Despesa não encontrada: '.$command->expenseId);
        }

        $expense->reject(
            new UserId($command->approverId),
            $command->reason,
        );

        $this->repository->save($expense);

        $domainEvents = $expense->pullDomainEvents();
    }
}

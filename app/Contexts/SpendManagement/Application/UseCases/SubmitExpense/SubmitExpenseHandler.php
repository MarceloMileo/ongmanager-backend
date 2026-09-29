<?php

declare(strict_types=1);

namespace App\Contexts\SpendManagement\Application\UseCases\SubmitExpense;

use App\Contexts\SpendManagement\Domain\Repositories\IExpenseRepository;
use RuntimeException;

final readonly class SubmitExpenseHandler
{
    public function __construct(
        private IExpenseRepository $repository,
    ) {}

    public function handle(SubmitExpenseCommand $command): void
    {
        $expense = $this->repository->findById($command->expenseId);

        if (! $expense) {
            throw new RuntimeException('Despesa não encontrada: '.$command->expenseId);
        }

        $expense->submit();
        $this->repository->save($expense);
        $domainEvents = $expense->pullDomainEvents();
    }
}

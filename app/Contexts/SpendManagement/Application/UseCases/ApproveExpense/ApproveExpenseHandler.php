<?php

declare(strict_types=1);

namespace App\Contexts\SpendManagement\Application\UseCases\ApproveExpense;

use App\Contexts\Shared\Domain\ValueObjects\UserId;
use App\Contexts\SpendManagement\Domain\Repositories\IExpenseRepository;
use RuntimeException;

final readonly class ApproveExpenseHandler
{
    public function __construct(
        private IExpenseRepository $repository,
    ) {}

    public function handle(ApproveExpenseCommand $command): void
    {
        $expense = $this->repository->findById($command->expenseId);

        if (! $expense) {
            throw new RuntimeException('Despensa não encontrada: '.$command->expenseId);
        }

        $expense->approve(new UserId($command->approverId));

        $this->repository->save($expense);

        $domainEvents = $expense->pullDomainEvents();
    }
}

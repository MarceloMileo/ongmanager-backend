<?php

declare(strict_types=1);

namespace App\Contexts\SpendManagement\Application\UseCases\PayExpense;

use App\Contexts\Shared\Domain\ValueObjects\UserId;
use App\Contexts\SpendManagement\Domain\Repositories\IExpenseRepository;
use RuntimeException;

final readonly class PayExpenseHandler
{
    public function __construct(
        private IExpenseRepository $repository,
    ) {}

    public function handle(PayExpenseCommand $command): void
    {
        $expense = $this->repository->findById($command->expenseId);

        if (! $expense) {
            throw new RuntimeException('Despesa não encontrada: '.$command->expenseId);
        }

        $expense->pay(new UserId($command->payerId));

        $this->repository->save($expense);

        $domainEvents = $expense->pullDomainEvents();
        // TODO: publicar $domainEvents no message broker
    }
}

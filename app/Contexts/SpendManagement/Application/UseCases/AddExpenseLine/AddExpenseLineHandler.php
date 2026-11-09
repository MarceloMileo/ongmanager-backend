<?php

declare(strict_types=1);

namespace App\Contexts\SpendManagement\Application\UseCases\AddExpenseLine;

use App\Contexts\Shared\Domain\ValueObjects\DistributionType;
use App\Contexts\Shared\Domain\ValueObjects\ExchangeRate;
use App\Contexts\Shared\Domain\ValueObjects\Money;
use App\Contexts\Shared\Domain\ValueObjects\ProjectId;
use App\Contexts\SpendManagement\Domain\Entities\ExpenseLine;
use App\Contexts\SpendManagement\Domain\Repositories\IExpenseRepository;
use DateTimeImmutable;
use Ramsey\Uuid\Uuid;
use RuntimeException;

final readonly class AddExpenseLineHandler
{
    public function __construct(
        private IExpenseRepository $repository,
    ) {}

    public function handle(AddExpenseLineCommand $command): void
    {
        $expense = $this->repository->findById($command->expenseId);

        if (! $expense) {
            throw new RuntimeException('Despesa não encontrada: '.$command->expenseId);
        }

        $exchangeRate = null;

        if ($command->exchangeRate !== null) {
            $exchangeRate = new ExchangeRate(
                $command->exchangeRateSourceCurrency ?? '',
                $command->exchangeRateTargetCurrency ?? '',
                $command->exchangeRate,
                new DateTimeImmutable($command->exchangeRateDate ?? 'now'),
            );
        }

        $line = new ExpenseLine(
            Uuid::uuid4()->toString(),
            $command->description,
            new ProjectId($command->projectId),
            new Money($command->amountCents, $command->amountCurrency),
            DistributionType::from($command->distributionType),
            $exchangeRate,
        );

        $expense->addLine($line);
        $this->repository->save($expense);
    }
}

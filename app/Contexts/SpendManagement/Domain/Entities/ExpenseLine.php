<?php

declare(strict_types=1);

namespace App\Contexts\SpendManagement\Domain\Entities;

use InvalidArgumentException;

use App\Contexts\Shared\Domain\ValueObjects\DistributionType;
use App\Contexts\Shared\Domain\ValueObjects\ExchangeRate;
use App\Contexts\Shared\Domain\ValueObjects\ProjectId;
use App\Contexts\Shared\Domain\ValueObjects\Money;
use Ramsey\Uuid\Uuid;

class ExpenseLine
{
    private string $id;
    private ProjectId $projectId;
    private Money $amount;
    private ?ExchangeRate $exchangeRate;
    private DistributionType $distributionType;

    private function assertValidAmount(Money $amount): void
    {
        if ($amount->getAmountInCents() <= 0) {
            throw new InvalidArgumentException("O valor da linha de despesa deve ser maior que zero.");
        }
    }

    public function __construct(
        string $id,
        ProjectId $projectId,
        Money $amount,
        DistributionType $distributionType,
        ?ExchangeRate $exchangeRate = null
    ) {
        if (!Uuid::isValid($id)) {
            throw new InvalidArgumentException(sprintf('O valor "%s" não é um UUID válido.', $id));
        }

        $this->assertValidAmount($amount);

        $this->id = strtolower($id);
        $this->projectId = $projectId;
        $this->amount = $amount;
        $this->distributionType = $distributionType;
        $this->exchangeRate = $exchangeRate;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getProjectId(): ProjectId
    {
        return $this->projectId;
    }

    public function getAmount(): Money
    {
        return $this->amount;
    }

    public function getDistributionType(): DistributionType
    {
        return $this->distributionType;
    }

    public function getExchangeRate(): ?ExchangeRate
    {
        return $this->exchangeRate;
    }

    public function equals(ExpenseLine $other): bool
    {
        return $this->id === $other->getId();
    }

    public function changeAmount(Money $amount): void
    {
        $this->assertValidAmount($amount);
        $this->amount = $amount;
    }

    public function changeProject(ProjectId $projectId): void
    {
        $this->projectId = $projectId;
    }

    public function changeExchangeRate(?ExchangeRate $exchangeRate): void
    {
        $this->exchangeRate = $exchangeRate;
    }
}

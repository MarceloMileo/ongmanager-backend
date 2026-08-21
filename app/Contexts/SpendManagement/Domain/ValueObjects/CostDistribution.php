<?php

declare(strict_types=1);

namespace App\Contexts\SpendManagement\Domain\ValueObjects;

use InvalidArgumentException;
use App\Contexts\Shared\Domain\ValueObjects\Money;
use App\Contexts\Shared\Domain\ValueObjects\ProjectId;

/**
 * Value Object imutável que representa a distribuição de custo de uma despesa
 * para um projeto específico, após aplicação do rateio e da Penny Rounding Rule.
 *
 * A proporção é representada em basis points (bps):
 * 10000 bps = 100%, 5000 bps = 50%, 3333 bps ≈ 33.33%
 *
 * Essa representação evita erros de ponto flutuante em cálculos percentuais,
 * seguindo o mesmo princípio do Money em centavos.
 */
final readonly class CostDistribution
{
    private ProjectId $projectId;
    private Money $allocatedAmount;
    private int $proportionInBasisPoints;

    public function __construct(
        ProjectId $projectId,
        Money $allocatedAmount,
        int $proportionInBasisPoints
    ) {
        if ($allocatedAmount->getAmountInCents() <= 0) {
            throw new InvalidArgumentException(
                'O valor alocado na distribuição de custo deve ser maior que zero.'
            );
        }

        if ($proportionInBasisPoints <= 0) {
            throw new InvalidArgumentException(
                'A proporção em basis points deve ser maior que zero.'
            );
        }

        if ($proportionInBasisPoints > 10000) {
            throw new InvalidArgumentException(
                'A proporção em basis points não pode ultrapassar 10000 (100%).'
            );
        }

        $this->projectId               = $projectId;
        $this->allocatedAmount         = $allocatedAmount;
        $this->proportionInBasisPoints = $proportionInBasisPoints;
    }

    public function getProjectId(): ProjectId
    {
        return $this->projectId;
    }

    public function getAllocatedAmount(): Money
    {
        return $this->allocatedAmount;
    }

    public function getProportionInBasisPoints(): int
    {
        return $this->proportionInBasisPoints;
    }

    /**
     * Retorna a proporção como percentual legível (ex: 3333 bps → 33.33%)
     * Útil para exibição em relatórios e APIs — não usado em cálculos.
     */
    public function getProportionAsPercentage(): string
    {
        return number_format($this->proportionInBasisPoints / 100, 2) . '%';
    }

    /**
     * Compara duas distribuições de custo por valor — comportamento de Value Object.
     */
    public function equals(CostDistribution $other): bool
    {
        return $this->projectId->equals($other->projectId)
            && $this->allocatedAmount->equals($other->allocatedAmount)
            && $this->proportionInBasisPoints === $other->proportionInBasisPoints;
    }
}

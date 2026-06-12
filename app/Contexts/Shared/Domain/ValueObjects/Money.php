<?php

declare(strict_types=1);

namespace App\Contexts\Shared\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Value Object imutável que representa valores monetários com precisão absoluta.
 * Utiliza inteiros para centavos e a biblioteca bcmath para operações complexas.
 */
final readonly class Money
{
    private int $amountInCents;
    private string $currency;

    public function __construct(int $amountInCents, string $currency)
    {
        if (empty($currency) || strlen($currency) !== 3) {
            throw new InvalidArgumentException('A moeda deve ser um codigo ISO 4217 de 3 caracteres');
        }

        $this->amountInCents = $amountInCents;
        // Normaliza sempre para maiúsculas (ex: "brl" -> "BRL")
        $this->currency = strtoupper($currency);
    }

    public function getAmountInCents(): int
    {
        return $this->amountInCents;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    /**
     * Compara se duas instancias de Money são identicas
     */
    public function equals(Money $other): bool
    {
        return $this->amountInCents === $other->amountInCents
            && $this->currency === $other->currency;
    }

    /**
     * Adiciona um valor monetário, garantindo consistência de moedas.
     */
    public function add(Money $other): self
    {
        $this->assertSameCurrency($other);

        $result = bcadd((string)$this->amountInCents, (string)$other->amountInCents, 0);

        return new self((int)$result, $this->currency);
    }

    /**
     * Subtrai um valor monetário, garantindo consistência de moedas.
     */
    public function subtract(Money $other): self
    {
        $this->assertSameCurrency($other);

        $result = bcsub((string)$this->amountInCents, (string)$other->amountInCents, 0);

        return new self((int)$result, $this->currency);
    }

    /**
     * Multiplica o valor por um fator decimal (ex: taxa de juros, conversão), aplicando arredondamento correto.
     */
    public function multiply(float|string $multiplier, int $roundingMode = PHP_ROUND_HALF_UP): self
    {
        //Multiplica usando bcmath com alta precisão temporária (4 casas decimais)
        $result = bcmul((string)$this->amountInCents, (string)$multiplier, 4);

        //Arredonda para o centavo mais próximo de acordo com o modo configurado
        $roundedAmount = (int) round((float) $result, 0, $roundingMode);

        return new self($roundedAmount, $this->currency);
    }

    /**
     * Distribui o dinheiro de forma proporcional entre várias fatias sem perder centavos.
     * (Algoritmo de Alocação Proporcional de Martin Fowler)
     *
     * @param array<int> $ratios Lista de pesos ou proporções (ex: [1, 1, 1] para partes iguais)
     * @return array<Money>
     */
    public function allocate(array $ratios): array
    {
        if (empty($ratios)) {
            throw new InvalidArgumentException('A lista de proporções para alocação não pode estar vazia');
        }

        $totalRatio = array_sum($ratios);
        if ($totalRatio <= 0) {
            throw new InvalidArgumentException('A soma das proporções de rateio deve ser maior que zero');
        }

        $remainder = $this->amountInCents;
        $results = [];

        // Primeira passada: Aloca a parte inteira de cada proporção
        foreach ($ratios as $ratio) {
            // share = (amount * ratio) / totalRatio
            $share = (int) floor(($this->amountInCents * $ratio) / $totalRatio);
            $results[] = $share;
            $remainder -= $share;
        }

        // Segunda passada: Distribui o resto (centavos restantes) um a um para as fatias de maior peso
        // Isso impede a perda de centavos residuais por arredondamento.
        for ($i = 0; $i < $remainder; $i++) {
            $results[$i]++;
        }

        // Mapeia os inteiros de volta para instâncias ricas de Money
        return array_map(fn($amount) => new self($amount, $this->currency), $results);
    }

    public function isGreaterThan(Money $other): bool
    {
        $this->assertSameCurrency($other);
        return $this->amountInCents > $other->amountInCents;
    }

    public function isLessThan(Money $other): bool
    {
        $this->assertSameCurrency($other);
        return $this->amountInCents < $other->amountInCents;
    }

    private function assertSameCurrency(Money $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                sprintf('Operação impossível: Moedas conflitantes (%s vs %s).', $this->currency, $other->currency)
            );
        }
    }
}

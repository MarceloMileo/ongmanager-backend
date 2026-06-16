<?php

declare(strict_types=1);

namespace App\Contexts\Shared\Domain\ValueObjects;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Value Object imutável que representa a taxa de conversão cambial entre duas moedas em uma data específica.
 *
 * Invariantes do domino:
 *  - As moeadas devem ser codigos ISO 4217 validos de 3 caracteres.
 *  - A moeada origem e dstino não podem ser iguais.
 *  - A taxa deve ser um  valor positivo maior que zero.
 *  - A data deve estar em UTC (conforme ADR-0003: Todos os timestamps são armazenados em UTC).
 */
final readonly class ExchangeRate
{
    private string $sourceCurrency;
    private string $targetCurrency;
    private string $rate;
    private DateTimeImmutable $date;

    public function __construct(
        string $sourceCurrency,
        string $targetCurrency,
        float|string $rate,
        DateTimeImmutable $date
    ) {
        $sourceCurrency = strtoupper(trim($sourceCurrency));
        $targetCurrency = strtoupper(trim($targetCurrency));

        if (empty($sourceCurrency) || strlen($sourceCurrency) !== 3) {
            throw new InvalidArgumentException('A moeda de origem deve ser um código ISO 4217 de 3 caracteres.');
        }

        if (empty($targetCurrency) || strlen($targetCurrency) !== 3) {
            throw new InvalidArgumentException('A moeda de destino deve ser um código ISO 4217 de 3 caracteres.');
        }

        if ($sourceCurrency === $targetCurrency) {
            throw new InvalidArgumentException('A moeda de origem e destino não podem ser iguais para a conversão');
        }

        //Armazena a taxa como string para manter a precisão decimal arbitrária via bcmath
        $rateStr = (string)$rate;
        if (bccomp($rateStr, '0', 4) <= 0) {
            throw new InvalidArgumentException('A taxa de cambio deve ser maior que zero');
        }


        // Toda data/hora gravada no sistema deve estar em UTC para garantir
        // consistência em fechamentos contábeis entre fusos horários distintos.
        if ($date->getTimezone()->getName() !== 'UTC') {
            throw new InvalidArgumentException('A data da taxa de cambio deve estar no fuso horario UTC (ADR-0003)');
        }

        $this->sourceCurrency = $sourceCurrency;
        $this->targetCurrency = $targetCurrency;
        $this->rate = $rateStr;
        $this->date = $date;
    }

    public function getSourceCurrency(): string
    {
        return $this->sourceCurrency;
    }

    public function getTargetCurrency(): string
    {
        return $this->targetCurrency;
    }

    public function getRate(): string
    {
        return $this->rate;
    }

    public function getDate(): DateTimeImmutable
    {
        return $this->date;
    }

    /**
     * Converte um valor monetario da moeda de origem para a moeda de destino.
     * Aplica arredondamento matematico correto para o centavo mais próximo.
     */
    public function convert(Money $money): Money
    {
        if ($money->getCurrency() !== $this->sourceCurrency) {
            throw new InvalidArgumentException(sprintf(
                'Incompatibilidade de moeda: A taxa de cambio exige a moeda %s, mas foi fornecido %s',
                $this->sourceCurrency,
                $money->getCurrency()
            ));
        }

        // Executa a conversão: target_cents = source_cents * rate
        // Usado bcmath com precisão temporaria de 4 casas decimais
        $convertedAmount = bcmul((string)$money->getAmountInCents(), $this->rate, 4);

        // Arredonda para o inteiro (centavos) mais proximo de acordo com o padrão contabil
        $roundedCents = (int)round((float)$convertedAmount, 0, PHP_ROUND_HALF_UP);

        return new Money($roundedCents, $this->targetCurrency);
    }

    /**
     * Dois VOs são iguais se representam a mesma taxa, entre as mesmas moedas, na mesma data.
     * A comparação da taxa usa bcmath para evitar erros de ponto flutuante.
     */
    public function equals(ExchangeRate $other): bool
    {
        return $this->sourceCurrency === $other->sourceCurrency
            && $this->targetCurrency === $other->targetCurrency
            && bccomp($this->rate, $other->rate, 4) === 0
            && $this->date->getTimestamp() === $other->date->getTimestamp();
    }
}

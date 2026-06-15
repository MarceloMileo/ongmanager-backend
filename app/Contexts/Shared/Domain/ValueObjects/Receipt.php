<?php

declare(strict_types=1);

namespace App\Contexts\Shared\Domain\ValueObjects;

use InvalidArgumentException;
use DateTimeImmutable;

final readonly class Receipt
{

    private Money $documentValue;
    private string $fileReference;
    private ?string $documentNumber;
    private ?string $issuerIdentifier;
    private ?DateTimeImmutable $issuedAt;

    public function __construct(
        string $fileReference,
        Money $documentValue,
        ?string $documentNumber = null,
        ?string $issuerIdentifier = null,
        ?DateTimeImmutable $issuedAt = null
    ) {
        $fileReference = trim($fileReference);

        if (empty($fileReference)) {
            throw new InvalidArgumentException("Caminho do recibo não pode ser vazio");
        }

        if ($documentValue->getAmountInCents() <= 0) {
            throw new InvalidArgumentException("O valor do recibo deve ser maior que zero");
        }

        if ($issuedAt !== null && $issuedAt->getTimezone()->getName() !== 'UTC') {
            throw new InvalidArgumentException('A data da taxa de cambio deve estar no fuso horario UTC (ADR-0003)');
        }

        $this->fileReference = $fileReference;
        $this->documentValue = $documentValue;
        $this->documentNumber = $documentNumber;
        $this->issuerIdentifier = $issuerIdentifier;
        $this->issuedAt = $issuedAt;
    }

    public function getDocumentValue(): Money
    {
        return $this->documentValue;
    }

    public function getFileReference(): string
    {
        return $this->fileReference;
    }

    public function getDocumentNumber(): ?string
    {
        return $this->documentNumber;
    }

    public function getIssuerIdentifier(): ?string
    {
        return $this->issuerIdentifier;
    }

    public function getIssuedAt(): ?DateTimeImmutable
    {
        return $this->issuedAt;
    }
}

<?php

declare(strict_mode=1);

namespace App\Contexts\Shared\Domain\ValueObjects;

use InvalidArgumentException;
use Ramsey\Uuid\Uuid;

/**
 * Value Object imutável que representa a identidade única de um Projeto.
 * Utilizado como referência entre Bounded Contexts sem acoplamento direto.
 * (Conforme ADR-0004: referência por ID entre SpendManagement e ProjectDelivery)
 */
final readonly class ProjectId
{
    private string $value;

    public function __construct(string $value)
    {
        if (!Uuid::isValid($value)) {
            throw new InvalidArgumentException(sprintf('O valor "%s" não é um UUID válido.', $value));
        }

        $this->value = strtolower($value);
    }

    /**
     * Factory method que gera um novo ProjectId com UUID v4 aleatorio
     */
    public static function generate(): self
    {
        return new self(Uuid::uuid4()->toString());
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(ProjectId $other): bool
    {
        return $this->value === $other->toString();
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Domain\ValueObjects;

use PHPUnit\Framework\TestCase;
use App\Contexts\Shared\Domain\ValueObjects\Money;
use App\Contexts\Shared\Domain\ValueObjects\Receipt;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

class ReceiptTest extends TestCase
{
    // ─────────────────────────────────────────────
    // Helper
    // ─────────────────────────────────────────────

    private function utcDate(string $date = '2026-05-19'): DateTimeImmutable
    {
        return new DateTimeImmutable($date, new DateTimeZone('UTC'));
    }

    // ─────────────────────────────────────────────
    // Criação válida — atributos obrigatórios
    // ─────────────────────────────────────────────

    public function test_should_create_instance_successfully(): void
    {
        $money   = new Money(1500, 'BRL');
        $receipt = new Receipt('path/arquivo/recibo', $money);

        $this->assertSame(1500, $receipt->getDocumentValue()->getAmountInCents());
        $this->assertSame('path/arquivo/recibo', $receipt->getFileReference());
    }

    // ─────────────────────────────────────────────
    // Validações dos atributos obrigatórios
    // ─────────────────────────────────────────────

    public function test_should_reject_empty_file_reference(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Receipt('', new Money(1500, 'BRL'));
    }

    public function test_should_reject_negative_document_value(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Receipt('path/arquivo/recibo', new Money(-1500, 'BRL'));
    }

    public function test_should_reject_zero_document_value(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Receipt('path/arquivo/recibo', new Money(0, 'BRL'));
    }

    // ─────────────────────────────────────────────
    // Atributos opcionais — ausentes por padrão
    // ─────────────────────────────────────────────

    public function test_optional_fields_should_be_null_when_not_provided(): void
    {
        $receipt = new Receipt('path/arquivo/recibo', new Money(1500, 'BRL'));

        $this->assertNull($receipt->getDocumentNumber());
        $this->assertNull($receipt->getIssuerIdentifier());
        $this->assertNull($receipt->getIssuedAt());
    }

    // ─────────────────────────────────────────────
    // Atributos opcionais — fornecidos
    // ─────────────────────────────────────────────

    public function test_should_store_optional_fields_when_provided(): void
    {
        $date    = $this->utcDate();
        $receipt = new Receipt(
            fileReference: 'path/arquivo/recibo',
            documentValue: new Money(1500, 'BRL'),
            documentNumber: 'NF-001234',
            issuerIdentifier: '12.345.678/0001-99',
            issuedAt: $date,
        );

        $this->assertSame('NF-001234', $receipt->getDocumentNumber());
        $this->assertSame('12.345.678/0001-99', $receipt->getIssuerIdentifier());
        $this->assertSame($date, $receipt->getIssuedAt());
    }

    // ─────────────────────────────────────────────
    // Imutabilidade
    // ─────────────────────────────────────────────

    public function test_should_be_immutable(): void
    {
        $money    = new Money(1500, 'BRL');
        $receipt  = new Receipt('path/arquivo/recibo', $money);
        $receipt2 = new Receipt('path/arquivo/recibo', $money);

        $this->assertNotSame($receipt, $receipt2);
        $this->assertSame($receipt->getFileReference(), $receipt2->getFileReference());
    }
}

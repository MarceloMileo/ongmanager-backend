<?php

declare(strict_types=1);

namespace App\Contexts\SpendManagement\Domain\Entities;

use App\Contexts\Shared\Domain\ValueObjects\ExpenseStatus;
use App\Contexts\Shared\Domain\ValueObjects\Money;
use App\Contexts\Shared\Domain\ValueObjects\Receipt;
use App\Contexts\Shared\Domain\ValueObjects\UserId;
use App\Contexts\SpendManagement\Domain\Events\ExpenseApproved;
use App\Contexts\SpendManagement\Domain\Events\ExpensePaid;
use App\Contexts\SpendManagement\Domain\Events\ExpenseRejected;
use App\Contexts\SpendManagement\Domain\Events\ExpenseSubmitted;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;

class Expense
{
    private string $id;

    private Receipt $receipt;

    private Money $totalAmount;

    private UserId $submitterId;

    private ExpenseStatus $status;

    /** @var ExpenseLine[] */
    private array $lines = [];

    /** @var array<object> */
    private array $domainEvents = [];

    private function assertValidAmount(Money $totalAmount): void
    {
        if ($totalAmount->getAmountInCents() <= 0) {
            throw new InvalidArgumentException('O valor da despesa deve ser maior que zero.');
        }
    }

    public function __construct(
        string $id,
        Receipt $receipt,
        Money $totalAmount,
        UserId $submitterId
    ) {
        if (! Uuid::isValid($id)) {
            throw new InvalidArgumentException(sprintf('O valor "%s" não é um UUID válido.', $id));
        }

        if ($receipt->getDocumentValue()->getCurrency() !== $totalAmount->getCurrency()) {
            throw new InvalidArgumentException(
                'A moeda do comprovante fiscal deve ser a mesma do valor total da despesa.'
            );
        }

        $this->assertValidAmount($totalAmount);

        $this->id = strtolower($id);
        $this->receipt = $receipt;
        $this->totalAmount = $totalAmount;
        $this->submitterId = $submitterId;
        $this->status = ExpenseStatus::DRAFT;
    }

    private function recordEvent(object $event): void
    {
        $this->domainEvents[] = $event;
    }

    /** @return array<object> */
    public function pullDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];

        return $events;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getTotalAmount(): Money
    {
        return $this->totalAmount;
    }

    public function getSubmitterId(): UserId
    {
        return $this->submitterId;
    }

    public function getStatus(): ExpenseStatus
    {
        return $this->status;
    }

    /** @return array<ExpenseLine> */
    public function getLines(): array
    {
        return $this->lines;
    }

    public function getReceipt(): Receipt
    {
        return $this->receipt;
    }

    public function addLine(ExpenseLine $line): void
    {
        if ($this->status !== ExpenseStatus::DRAFT) {
            throw new InvalidArgumentException('Linhas só podem ser adicionadas enquanto a despesa estiver em rascunho.');
        }
        $this->lines[] = $line;
    }

    public function submit(): void
    {
        if (empty($this->lines)) {
            throw new InvalidArgumentException('A despesa só pode ser submetida com pelo menos uma linha adicionada.');
        }
        $this->status = ExpenseStatus::SUBMITTED;
        $this->recordEvent(new ExpenseSubmitted(
            $this->id,
            $this->submitterId->toString(),
            new DateTimeImmutable('now', new DateTimeZone('UTC'))
        ));
    }

    public function approve(UserId $approverId): void
    {
        if ($this->submitterId->equals($approverId)) {
            throw new InvalidArgumentException('O aprovador não pode ser o mesmo usuário que submeteu a despesa.');
        }
        $this->status = ExpenseStatus::APPROVED;
        $this->recordEvent(new ExpenseApproved(
            $this->id,
            $approverId->toString(),
            new DateTimeImmutable('now', new DateTimeZone('UTC')),
        ));
    }

    public function reject(UserId $approverId, string $reason): void
    {
        if ($this->status !== ExpenseStatus::SUBMITTED) {
            throw new InvalidArgumentException('Apenas despesas submetidas podem ser rejeitadas.');
        }

        if (empty(trim($reason))) {
            throw new InvalidArgumentException('O motivo da rejeição é obrigatório.');
        }

        if ($this->submitterId->equals($approverId)) {
            throw new InvalidArgumentException('O reprovador não pode ser o mesmo usuário que submeteu a despesa.');
        }

        $this->status = ExpenseStatus::REJECTED;

        $this->recordEvent(new ExpenseRejected(
            $this->id,
            $approverId->toString(),
            $reason,
            new DateTimeImmutable('now', new DateTimeZone('UTC')),
        ));
    }

    public function pay(UserId $payerId): void
    {
        if ($this->status !== ExpenseStatus::APPROVED) {
            throw new InvalidArgumentException('Apenas despesas aprovadas podem ser pagas.');
        }

        if ($this->submitterId->equals($payerId)) {
            throw new InvalidArgumentException('O pagador não pode ser o mesmo usuário que submeteu a despesa.');
        }

        $this->status = ExpenseStatus::PAID;

        $this->recordEvent(new ExpensePaid(
            $this->id,
            new DateTimeImmutable('now', new DateTimeZone('UTC')),
        ));
    }
}

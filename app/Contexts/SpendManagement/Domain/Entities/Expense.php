<?php

declare(strict_types=1);

namespace App\Contexts\SpendManagement\Domain\Entities;

use InvalidArgumentException;

use App\Contexts\Shared\Domain\ValueObjects\Receipt;
use App\Contexts\Shared\Domain\ValueObjects\Money;
use App\Contexts\Shared\Domain\ValueObjects\UserId;
use App\Contexts\Shared\Domain\ValueObjects\ExpenseStatus;
use App\Contexts\SpendManagement\Domain\Entities\ExpenseLine;
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

    private function assertValidAmount(Money $totalAmount): void
    {
        if ($totalAmount->getAmountInCents() <= 0) {
            throw new InvalidArgumentException("O valor da despesa deve ser maior que zero.");
        }
    }

    public function __construct(
        string $id,
        Receipt $receipt,
        Money $totalAmount,
        UserId $submitterId
    ) {
        if (!Uuid::isValid($id)) {
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
            throw new InvalidArgumentException("Linhas só podem ser adicionadas enquanto a despesa estiver em rascunho.");
        }
        $this->lines[] = $line;
    }

    public function submit(): void
    {
        if (empty($this->lines)) {
            throw new InvalidArgumentException("A despesa só pode ser submetida com pelo menos uma linha adicionada.");
        }
        $this->status = ExpenseStatus::SUBMITTED;
    }

    public function approve(UserId $approverId): void
    {
        if ($this->submitterId->equals($approverId)) {
            throw new InvalidArgumentException("O aprovador não pode ser o mesmo usuário que submeteu a despesa.");
        }
        $this->status = ExpenseStatus::APPROVED;
    }
}

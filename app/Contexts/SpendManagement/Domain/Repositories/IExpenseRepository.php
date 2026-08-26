<?php

declare(strict_types=1);

namespace App\Contexts\SpendManagement\Domain\Repositories;

use App\Contexts\Shared\Domain\ValueObjects\ExpenseStatus;
use App\Contexts\Shared\Domain\ValueObjects\UserId;
use App\Contexts\SpendManagement\Domain\Entities\Expense;

interface IExpenseRepository
{
    // Salva uma Expense (nova ou atualizada)
    public function save(Expense $expense): void;

    // Busca uma Expense pelo ID
    public function findById(string $id): ?Expense;

    // Delete uma Expense
    public function delete(Expense $expense): void;

    // Busca uma collection de Expenses pelo status
    public function findByStatus(ExpenseStatus $status): array;

    // Busca uma collection ex Expenses por usuario submissor
    public function findBySubmitter(UserId $id): array;
}

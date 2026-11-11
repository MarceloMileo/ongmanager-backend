<?php

declare(strict_types=1);

namespace App\Contexts\SpendManagement\Infrastructure\Http\Controllers;

use App\Contexts\SpendManagement\Application\UseCases\AddExpenseLine\AddExpenseLineCommand;
use App\Contexts\SpendManagement\Application\UseCases\AddExpenseLine\AddExpenseLineHandler;
use App\Contexts\SpendManagement\Application\UseCases\ApproveExpense\ApproveExpenseCommand;
use App\Contexts\SpendManagement\Application\UseCases\ApproveExpense\ApproveExpenseHandler;
use App\Contexts\SpendManagement\Application\UseCases\CreateExpense\CreateExpenseCommand;
use App\Contexts\SpendManagement\Application\UseCases\CreateExpense\CreateExpenseHandler;
use App\Contexts\SpendManagement\Application\UseCases\PayExpense\PayExpenseCommand;
use App\Contexts\SpendManagement\Application\UseCases\PayExpense\PayExpenseHandler;
use App\Contexts\SpendManagement\Application\UseCases\RejectExpense\RejectExpenseCommand;
use App\Contexts\SpendManagement\Application\UseCases\RejectExpense\RejectExpenseHandler;
use App\Contexts\SpendManagement\Application\UseCases\SubmitExpense\SubmitExpenseCommand;
use App\Contexts\SpendManagement\Application\UseCases\SubmitExpense\SubmitExpenseHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ExpenseController extends Controller
{
    public function store(Request $request, CreateExpenseHandler $handler): JsonResponse
    {
        $expenseId = $handler->handle(new CreateExpenseCommand(
            fileReference: $request->input('file_reference'),
            documentValueCents: $request->integer('document_value_cents'),
            documentValueCurrency: $request->input('document_value_currency'),
            totalAmountCents: $request->integer('total_amount_cents'),
            totalAmountCurrency: $request->input('total_amount_currency'),
            submitterId: $request->input('submitter_id'),
            documentNumber: $request->input('document_number'),
            issuerIdentifier: $request->input('issuer_identifier'),
            issuedAt: $request->input('issued_at'),
        ));

        return response()->json([
            'message' => 'Despesa criada com sucesso.',
            'id' => $expenseId,
        ], 201);
    }

    public function submit(
        string $id,
        Request $request,
        SubmitExpenseHandler $handler
    ): JsonResponse {
        $handler->handle(new SubmitExpenseCommand(
            expenseId: $id,
            submitterId: $request->input('submitter_id'),
        ));

        return response()->json(['message' => 'Despesa submetida com sucesso.']);
    }

    public function approve(
        string $id,
        Request $request,
        ApproveExpenseHandler $handler
    ): JsonResponse {
        $handler->handle(new ApproveExpenseCommand(
            expenseId: $id,
            approverId: $request->input('approver_id'),
        ));

        return response()->json(['message' => 'Despesa aprovada com sucesso.']);
    }

    public function reject(
        string $id,
        Request $request,
        RejectExpenseHandler $handler
    ): JsonResponse {
        $handler->handle(new RejectExpenseCommand(
            expenseId: $id,
            approverId: $request->input('approver_id'),
            reason: $request->input('reason'),
        ));

        return response()->json(['message' => 'Despesa rejeitada com sucesso.']);
    }

    public function pay(
        string $id,
        Request $request,
        PayExpenseHandler $handler
    ): JsonResponse {
        $handler->handle(new PayExpenseCommand(
            expenseId: $id,
            payerId: $request->input('payer_id'),
        ));

        return response()->json(['message' => 'Despesa paga com sucesso.']);
    }

    public function addLine(Request $request, string $id, AddExpenseLineHandler $handler): JsonResponse
    {
        $handler->handle(new AddExpenseLineCommand(
            expenseId: $id,
            projectId: $request->input('project_id'),
            amountCents: $request->integer('amount_cents'),
            amountCurrency: $request->input('amount_currency'),
            distributionType: $request->input('distribution_type'),
            description: $request->input('description'),
            exchangeRate: $request->input('exchange_rate'),
            exchangeRateSourceCurrency: $request->input('exchange_rate_source_currency'),
            exchangeRateTargetCurrency: $request->input('exchange_rate_target_currency'),
            exchangeRateDate: $request->input('exchange_rate_date'),
        ));

        return response()->json(['message' => 'Linha adicionada com sucesso.'], 201);
    }
}

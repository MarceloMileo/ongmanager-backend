<?php

use App\Contexts\SpendManagement\Infrastructure\Http\Controllers\ExpenseController;
use Illuminate\Support\Facades\Route;

Route::prefix('expenses')->group(function () {
    Route::post('/', [ExpenseController::class, 'store']);
    Route::post('/{id}/submit', [ExpenseController::class, 'submit']);
    Route::post('/{id}/approve', [ExpenseController::class, 'approve']);
    Route::post('/{id}/reject', [ExpenseController::class, 'reject']);
    Route::post('/{id}/pay', [ExpenseController::class, 'pay']);
    Route::post('/{id}/lines', [ExpenseController::class, 'addLine']);
});

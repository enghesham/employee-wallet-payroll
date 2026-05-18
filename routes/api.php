<?php

use App\Http\Controllers\Api\V1\BankPaymentCallbackController;
use App\Http\Controllers\Api\V1\EmployeeController;
use App\Http\Controllers\Api\V1\PayrollEventController;
use App\Http\Controllers\Api\V1\WalletController;
use App\Http\Controllers\Api\V1\WithdrawalRequestController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', fn () => response()->json([
        'status' => 'ok',
        'service' => 'employee-wallet-payroll',
    ]));

    Route::apiResource('employees', EmployeeController::class)->only(['index', 'store', 'show']);
    Route::get('employees/{employee}/wallets', [WalletController::class, 'employeeIndex']);
    Route::post('employees/{employee}/wallets', [WalletController::class, 'store']);
    Route::post('wallets/{wallet}/withdrawals', [WithdrawalRequestController::class, 'store']);
    Route::post('bank/payment-requests/{bankPaymentRequest}/callback', [BankPaymentCallbackController::class, 'store']);
    Route::post('payroll/events', [PayrollEventController::class, 'store']);
    Route::apiResource('wallets', WalletController::class)->only(['index', 'show']);
});

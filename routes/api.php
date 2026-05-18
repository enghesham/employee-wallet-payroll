<?php

use App\Http\Controllers\Api\V1\BankPaymentCallbackController;
use App\Http\Controllers\Api\V1\EmployeeController;
use App\Http\Controllers\Api\V1\PayrollEventController;
use App\Http\Controllers\Api\V1\WalletController;
use App\Http\Controllers\Api\V1\WalletLedgerEntryController;
use App\Http\Controllers\Api\V1\WalletTransferController;
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
    Route::get('wallets/{wallet}/ledger-entries', [WalletLedgerEntryController::class, 'index']);
    Route::post('wallets/{wallet}/transfers', [WalletTransferController::class, 'store']);
    Route::post('wallets/{wallet}/withdrawals', [WithdrawalRequestController::class, 'store']);
    Route::post('integrations/bank/callbacks', [BankPaymentCallbackController::class, 'store'])
        ->middleware('provider.token:bank');
    Route::post('payroll/events', [PayrollEventController::class, 'store'])
        ->middleware('provider.token:payroll');
    Route::post('integrations/payroll/events/{payrollEvent}/retry', [PayrollEventController::class, 'retry'])
        ->middleware('provider.token:payroll');
    Route::apiResource('wallets', WalletController::class)->only(['index', 'show']);
});

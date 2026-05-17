<?php

use App\Http\Controllers\Api\V1\EmployeeController;
use App\Http\Controllers\Api\V1\WalletController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', fn () => response()->json([
        'status' => 'ok',
        'service' => 'employee-wallet-payroll',
    ]));

    Route::apiResource('employees', EmployeeController::class)->only(['index', 'store', 'show']);
    Route::get('employees/{employee}/wallets', [WalletController::class, 'employeeIndex']);
    Route::post('employees/{employee}/wallets', [WalletController::class, 'store']);
    Route::apiResource('wallets', WalletController::class)->only(['index', 'show']);
});

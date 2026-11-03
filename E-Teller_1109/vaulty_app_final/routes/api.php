<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\PinController;
use App\Http\Controllers\Api\V1\CustomerTransactionApprovalController;
use App\Http\Controllers\Api\V1\TransactionHistoryController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Aplikasi Mobile Nasabah
|--------------------------------------------------------------------------
| Prefix otomatis: /api
*/

Route::prefix('v1')->group(function () {

    // Aktivasi akun nasabah sebelum login.
    Route::post('/auth/request-activation-code', [AuthController::class, 'requestActivationCode'])
        ->middleware('throttle:30,1');

    Route::post('/auth/activate', [AuthController::class, 'activate'])
        ->middleware('throttle:10,1');

    // Login khusus nasabah.
    Route::post('/auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:20,1');

    // Wajib token Sanctum dan akun nasabah aktif.
    Route::middleware(['auth:sanctum', 'customer.active'])->group(function () {

        // Boleh diakses walaupun PIN masih default.
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        Route::get('/me', [MeController::class, 'show']);

        Route::post('/pin/setup', [PinController::class, 'setup'])
            ->middleware('throttle:10,1');
        Route::post('/pin/change', [PinController::class, 'change'])
            ->middleware('throttle:10,1');

        // Fitur transaksi hanya dapat diakses setelah PIN diganti.
        Route::middleware('pin.ready')->group(function () {
            Route::get('/transactions', [TransactionHistoryController::class, 'index']);
            Route::get('/transactions/pending-approval', [CustomerTransactionApprovalController::class, 'index']);
            Route::post('/transactions/pending-approval/{approval}/authorize', [CustomerTransactionApprovalController::class, 'approve']);
            Route::post('/transactions/pending-approval/{approval}/reject', [CustomerTransactionApprovalController::class, 'reject']);
        });
    });
});
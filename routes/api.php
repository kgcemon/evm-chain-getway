<?php

use App\Http\Controllers\api\Auth\UserAuthController;
use App\Http\Controllers\api\Invoice\InvoiceCreateController;
use App\Http\Controllers\api\Invoice\PaymentJobController;
use App\Http\Controllers\api\Single\Deposit;
use App\Http\Controllers\api\Single\Withdrawal;
use App\Services\CreateWallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

//auth
Route::post('create-account', [UserAuthController::class, 'createAccount']);
Route::post('create-login', [UserAuthController::class, 'login']);

Route::post('/create_invoice',[InvoiceCreateController::class,'createInvoice']);
Route::get('/last-transactions', [PaymentJobController::class, 'Jobs']);
Route::post('/payout', [Withdrawal::class, 'payout']);
Route::post('/deposit', [Deposit::class, 'deposit']);
Route::post('create-wallet', [CreateWallet::class, 'createAddress']);
Route::middleware(['throttle:10,1'])->get('payments/{id}', [PaymentJobController::class, 'checkNewPayments']);


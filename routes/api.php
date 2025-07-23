<?php

use App\Http\Controllers\api\Invoice\InvoiceCreateController;
use App\Http\Controllers\api\Invoice\PaymentJobController;
use App\Http\Controllers\api\Single\Deposit;
use App\Http\Controllers\api\Single\Withdrawal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/create_invoice',[InvoiceCreateController::class,'createInvoice']);
Route::get('/last-transactions', [PaymentJobController::class, 'Jobs']);
Route::post('/payout', [Withdrawal::class, 'payout']);
Route::post('/deposit', [Deposit::class, 'deposit']);


<?php

use App\Http\Controllers\InvoiceCreateController;
use App\Http\Controllers\PaymentJobController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/create_invoice',[InvoiceCreateController::class,'createInvoice']);
Route::get('/last-transactions', [PaymentJobController::class, 'Jobs']);


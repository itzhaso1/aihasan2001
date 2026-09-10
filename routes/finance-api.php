<?php

use App\Http\Controllers\Api\Finance\V1\InvoiceController;
use App\Http\Controllers\Api\Finance\V1\NoteController;
use App\Http\Controllers\Api\Finance\V1\PosInvoiceController;
use Illuminate\Support\Facades\Route;

Route::get('/invoices', [InvoiceController::class, 'index']);
Route::get('/invoices/{invoice}', [InvoiceController::class, 'show']);
Route::post('/invoices/{invoice}/issue', [InvoiceController::class, 'issue'])->middleware('throttle:mobile-write');
Route::get('/invoices/{invoice}/xml', [InvoiceController::class, 'xml']);
Route::get('/invoices/{invoice}/qr', [InvoiceController::class, 'qr']);
Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'pdf']);
Route::post('/invoices/{invoice}/cryptographic-stamp', [InvoiceController::class, 'cryptographicStamp'])
    ->middleware('throttle:mobile-write');

Route::get('/notes', [NoteController::class, 'index']);
Route::get('/notes/{note}', [NoteController::class, 'show']);
Route::post('/notes/{note}/issue', [NoteController::class, 'issue'])->middleware('throttle:mobile-write');
Route::get('/notes/{note}/xml', [NoteController::class, 'xml']);
Route::get('/notes/{note}/qr', [NoteController::class, 'qr']);
Route::post('/notes/{note}/cryptographic-stamp', [NoteController::class, 'cryptographicStamp'])
    ->middleware('throttle:mobile-write');

Route::get('/pos-invoices', [PosInvoiceController::class, 'index']);
Route::get('/pos-invoices/{posInvoice}', [PosInvoiceController::class, 'show']);
Route::get('/pos-invoices/{posInvoice}/xml', [PosInvoiceController::class, 'xml']);
Route::get('/pos-invoices/{posInvoice}/qr', [PosInvoiceController::class, 'qr']);
Route::post('/pos-invoices/{posInvoice}/cryptographic-stamp', [PosInvoiceController::class, 'cryptographicStamp'])
    ->middleware('throttle:mobile-write');

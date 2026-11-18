<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
Route::prefix('billing/flow')->middleware('throttle:120,1')->group(function(){
    Route::post('card-return',[\App\Http\Controllers\FlowBillingCallbackController::class,'card']);
    Route::post('payment',[\App\Http\Controllers\FlowBillingCallbackController::class,'payment']);
    Route::post('payment-return',[\App\Http\Controllers\FlowBillingCallbackController::class,'returned']);
    Route::post('recurring/{agreement}',[\App\Http\Controllers\FlowBillingCallbackController::class,'recurring'])->whereUuid('agreement');
});

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

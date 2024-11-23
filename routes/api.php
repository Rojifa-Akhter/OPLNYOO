<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\backend\AuthController;
use App\Http\Controllers\backend\AdminController;
use App\Http\Controllers\backend\OwnerController;




// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');
Route::group(['prefix' => 'auth'], function ($router) {
    Route::post('signup', [AuthController::class, 'signup']);
    Route::post('socialLogin', [AuthController::class, 'socialLogin']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('verify', [AuthController::class, 'verify']);
    Route::post('logout', [AuthController::class, 'logout']);

});
Route::middleware(['auth:api'])->group(function () {
    Route::put('/profile/update', [AuthController::class, 'updateProfile']);
    Route::post('change_password', [AuthController::class, 'changePassword']);
    // Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);

});

Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password/{token}/{email}', [AuthController::class, 'resetPassword']);


// Admins
Route::middleware(['auth:api', 'ADMIN'])->group(function () {
    Route::post('/ownerCreate', [AdminController::class, 'ownerCreate']);
    Route::delete('/deleteUser', [AdminController::class, 'deleteUser']);
    Route::get('/showSoftDeletedUsers', [AdminController::class, 'SoftDeletedUsers']);
});
// owner
Route::middleware(['auth:api', 'OWNER'])->group(function () {
    Route::post('/formCreate', [OwnerController::class, 'formCreate']);
    Route::get('/formEdit', [OwnerController::class, 'formEdit']);
    Route::put('/formUpdate', [OwnerController::class, 'formUpdate']);
    Route::delete('/formDelete', [OwnerController::class, 'formDelete']);
});


Route::get('demo/email/{token}/{email}',[AuthController::class,'a']);

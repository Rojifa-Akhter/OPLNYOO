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
    Route::post('verify', [AuthController::class, 'verify']);
    Route::post('socialLogin', [AuthController::class, 'socialLogin']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::put('/profile/update', [AuthController::class, 'updateProfile']);
    Route::post('change_password', [AuthController::class, 'changePassword']);
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password/{token}/{email}', [AuthController::class, 'resetPassword']);

});

// Admins
Route::middleware(['auth:api', 'ADMIN'])->group(function () {
    Route::post('/ownerCreate', [AdminController::class, 'ownerCreate']);
    Route::delete('/deleteUser', [AdminController::class, 'deleteUser']);
    Route::get('/showSoftDeletedUsers', [AdminController::class, 'SoftDeletedUsers']);
    Route::get('/showUser', [AdminController::class, 'showUser']);
    Route::get('/notifications', [AdminController::class, 'getAdminNotifications']);
    Route::post('/updateStatus/{id}', [AdminController::class, 'updateStatus']);
    Route::delete('/deleteQuestion/{id}', [AdminController::class, 'deleteQuestion']);
    Route::get('/dashboard-statistics', [AdminController::class, 'getDashboardStatistics']);
    Route::get('/monthly-answer-statistics', [AdminController::class, 'getMonthlyAnswerStatistics']);



});
// owner
Route::middleware(['auth:api', 'OWNER'])->group(function () {
    Route::post('/questionCreate', [OwnerController::class, 'questionCreate']);
    Route::delete('/questionDelete/{id}', [OwnerController::class, 'questionDelete']);

    Route::get('/view-answers', [OwnerController::class, 'viewSubmittedAnswers']);
    Route::delete('/delete-answers/{id}', [OwnerController::class, 'deleteSubmittedAnswers']);

});
Route::middleware(['auth:api', 'USER'])->group(function () {
    Route::post('/submit-answers', [OwnerController::class, 'submitAnswers']);
    Route::get('/survey', [OwnerController::class, 'survey']);
    Route::get('/companylist', [OwnerController::class, 'companylist']);
    Route::get('/company-details/{ownerId}', [OwnerController::class, 'companyDetails']);
});



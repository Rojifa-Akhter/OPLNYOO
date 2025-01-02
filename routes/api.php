<?php

use App\Http\Controllers\backend\AboutController;
use App\Mail\OTPVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\backend\AuthController;
use App\Http\Controllers\backend\AdminController;
use App\Http\Controllers\backend\OwnerController;
use App\Http\Controllers\backend\UserController;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');
Route::group(['prefix' => 'auth'], function ($router) {
    Route::post('signup', [AuthController::class, 'signup']);
    Route::post('verify', [AuthController::class, 'verify']);
    Route::post('socialLogin', [AuthController::class, 'socialLogin']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('/profile/update', [AuthController::class, 'updateProfile'])->middleware('auth:api');
    Route::post('/change-password', [AuthController::class, 'changePassword'])->middleware('auth:api');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/verifyOtp', [AuthController::class, 'verifyOtp']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    Route::post('/resend-otp', [AuthController::class, 'resendOtp']);
    Route::get('/userData', [AuthController::class, 'userData']);

});

// Admins
Route::middleware(['auth:api', 'ADMIN'])->group(function () {
    Route::post('/ownerCreate', [AdminController::class, 'ownerCreate']);
    Route::delete('/deleteUser', [AdminController::class, 'deleteUser']);
    Route::get('/showSoftDeletedUsers', [AdminController::class, 'SoftDeletedUsers']);
    Route::get('/showUser', [AdminController::class, 'showUser']);
    Route::get('/notifications', [AdminController::class, 'getAdminNotifications']);
    Route::patch('/Notifications/{id}', [AdminController::class, 'markAdminNotificationAsRead']);
    Route::post('/updateStatus/{id}', [AdminController::class, 'updateStatus']);
    Route::delete('/questionDelete', [AdminController::class, 'questionDelete']);
    Route::get('/dashboard-statistics', [AdminController::class, 'getDashboardStatistics']);
    Route::get('/monthly-answer-statistics', [AdminController::class, 'getMonthlyAnswerStatistics']);



});
// owner
Route::middleware(['auth:api', 'OWNER'])->group(function () {
    Route::post('/questionCreate', [OwnerController::class, 'questionCreate']);
    Route::delete('/questions', [OwnerController::class, 'questionDelete']);

    Route::post('/privacy', [OwnerController::class, 'privacy']);
    Route::get('/privacyList', [OwnerController::class, 'privacyView']);

    Route::post('/termsCondition', [OwnerController::class, 'termsCondition']);
    Route::get('/termsConditionView', [OwnerController::class, 'termsConditionView']);

    Route::post('/about', [AboutController::class, 'about']);
    Route::get('/aboutView', [AboutController::class,'aboutView']);

    Route::get('/dashboard-owner', [OwnerController::class, 'getOwnerStatistics']);

    Route::get('/feedbackforms', [OwnerController::class, 'viewFeedbackAnswers']);

    Route::get('/getNotifications', [OwnerController::class, 'getNotifications']);
    Route::post('/notifications/{id}', [OwnerController::class, 'markNotificationAsRead']);
    Route::get('/viewAnswers', [OwnerController::class, 'viewUserSubmittedAnswers']);
    Route::delete('/delete-answers/{id}', [OwnerController::class, 'deleteSubmittedAnswers']);



});
Route::middleware(['auth:api', 'USER'])->group(function () {
    Route::post('/submit-answers', [UserController::class, 'submitAnswers']);
    Route::get('/survey', [UserController::class, 'survey']);
    Route::get('/companylist', [UserController::class, 'companylist']);
    Route::get('/company-details/{ownerId}', [UserController::class, 'companyDetails']);

    Route::get('/getUserNotifications', [OwnerController::class, 'getUserNotifications']);
    Route::patch('/userNotifications/{id}', [OwnerController::class, 'markNotificationAsRead']);



    Route::get('/privacyView', [UserController::class, 'privacyView']);
    Route::get('/termsConditionView', [UserController::class, 'termsConditionView']);
    Route::get('/aboutView', [UserController::class, 'aboutView']);
});





<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;

Route::get('/', [AuthController::class, 'login']);
Route::get('/login', [AuthController::class, 'login'])->name('login');
Route::post('/login', [AuthController::class, 'login_post'])->name('login.post');

Route::get('/signup', [AuthController::class, 'signup'])->name('signup');
Route::post('/signup', [AuthController::class, 'signup_post'])->name('signup.post');

Route::get('/refresh-captcha', function () {
        return response()->json(['captcha'=> captcha_img()]);
    })->name('refresh-captcha');
Route::middleware('auth:promoter')->group(function () {
    Route::get('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/profile', [AuthController::class, 'profile'])->name('profile');
    Route::put('/profile', [AuthController::class, 'profile_update'])->name('profile.update');
    Route::get('/change-password', [AuthController::class, 'change_password'])->name('change-password.show');
    Route::put('/change-password', [AuthController::class, 'password_update'])->name('password.update');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/customize-settings', [DashboardController::class, 'customize'])->name('customize-settings');
});

<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{AuthController,FileController,DashboardController,AdminController,InstallController};

Route::get('/',fn()=>redirect(auth()->check()?'/dashboard':'/login'));
Route::get('/install',[InstallController::class,'index'])->name('install');
Route::post('/install',[InstallController::class,'advance'])->middleware('throttle:vault-10');
Route::middleware('guest')->group(function() {
    Route::get('/login',[AuthController::class,'loginForm'])->name('login');
    Route::post('/login',[AuthController::class,'login'])->middleware('throttle:vault-5');
    Route::get('/register',[AuthController::class,'registerForm'])->name('register');
    Route::post('/register',[AuthController::class,'register'])->middleware('throttle:vault-5');
    Route::get('/forgot-password',[AuthController::class,'forgotForm'])->name('password.request');
    Route::post('/forgot-password',[AuthController::class,'forgot'])->middleware('throttle:vault-3')->name('password.email');
    Route::get('/reset-password/{token}',[AuthController::class,'resetForm'])->name('password.reset');
    Route::post('/reset-password',[AuthController::class,'reset'])->middleware('throttle:vault-5')->name('password.update');
});
Route::get('/terms',fn()=>view('terms'))->name('terms');
Route::get('/branding/{kind}',[AdminController::class,'branding'])->name('branding');
Route::middleware('auth')->group(function() {
    Route::post('/logout',[AuthController::class,'logout'])->name('logout');
    Route::get('/email/verify',[AuthController::class,'verificationNotice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}',[AuthController::class,'verify'])->middleware(['signed','throttle:vault-6'])->name('verification.verify');
    Route::post('/email/verification-notification',[AuthController::class,'resend'])->middleware('throttle:vault-3')->name('verification.send');
    Route::middleware('active')->group(function() {
        Route::get('/dashboard',[DashboardController::class,'index'])->name('dashboard');
        Route::get('/files',[FileController::class,'index'])->name('files.index');
        Route::get('/upload',[FileController::class,'create'])->name('files.create');
        Route::post('/files',[FileController::class,'store'])->middleware('throttle:vault-20')->name('files.store');
        Route::get('/files/{file}',[FileController::class,'show'])->whereUuid('file')->name('files.show');
        Route::get('/files/{file}/content',[FileController::class,'content'])->whereUuid('file')->middleware('throttle:vault-240')->name('files.content');
        Route::get('/categories',[DashboardController::class,'categories'])->name('categories');
        Route::get('/activity',[DashboardController::class,'activity'])->name('activity');
        Route::get('/profile',[AuthController::class,'profile'])->name('profile');
        Route::patch('/profile',[AuthController::class,'updateProfile'])->name('profile.update');
        Route::put('/password',[AuthController::class,'changePassword'])->middleware('throttle:vault-5')->name('password.change');
        Route::prefix('admin')->name('admin.')->middleware('admin')->group(function() {
            Route::get('/users',[AdminController::class,'users'])->name('users');
            Route::get('/users/{user}',[AdminController::class,'user'])->name('users.show');
            Route::patch('/users/{user}/status',[AdminController::class,'userStatus'])->name('users.status');
            Route::delete('/users/{user}',[AdminController::class,'deleteUser'])->name('users.delete');
            Route::get('/files',[FileController::class,'index'])->name('files');
            Route::delete('/files/{file}',[FileController::class,'destroy'])->name('files.delete');
            Route::get('/activity',[DashboardController::class,'activity'])->name('activity');
            Route::get('/categories',[AdminController::class,'categories'])->name('categories');
            Route::post('/categories',[AdminController::class,'saveCategory'])->name('categories.create');
            Route::put('/categories/{category}',[AdminController::class,'saveCategory'])->name('categories.update');
            Route::delete('/categories/{category}',[AdminController::class,'deleteCategory'])->name('categories.delete');
            Route::get('/settings',[AdminController::class,'settings'])->name('settings');
            Route::put('/settings',[AdminController::class,'saveSettings'])->name('settings.save');
            Route::put('/smtp',[AdminController::class,'smtp'])->middleware('throttle:vault-5')->name('smtp');
            Route::post('/smtp/test',[AdminController::class,'testSmtp'])->middleware('throttle:vault-3')->name('smtp.test');
        });
    });
});

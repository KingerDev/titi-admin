<?php

use App\Http\Controllers\Auth\AdminLoginController;
use Illuminate\Support\Facades\Route;

Route::get('login', [AdminLoginController::class, 'create'])->name('login');
Route::post('login', [AdminLoginController::class, 'store']);
Route::post('logout', [AdminLoginController::class, 'destroy'])->name('logout');

<?php

use Illuminate\Support\Facades\Route;

/**************************************************** Authentification ****************************************************/
Route::get('/login', [\App\Http\Controllers\AuthController::class, 'login'])->name('login');
Route::get('/callback', [\App\Http\Controllers\AuthController::class, 'callback'])->name('callback');
Route::get('/logout', [\App\Http\Controllers\AuthController::class, 'logout'])->name('logout');
/**************************************************************************************************************************/
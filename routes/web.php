<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware('guest')->group(function () {

    Route::get(
        '/login',
        [LoginController::class, 'create']
    )->name('login');

    Route::post(
        '/login',
        [LoginController::class, 'store']
    )->name('login.store');

});

Route::middleware('auth')->group(function () {

    Route::post(
        '/logout',
        [LoginController::class, 'destroy']
    )->name('logout');

    Route::middleware('usuario.activo')->group(function () {

        Route::get(
            '/dashboard',
            DashboardController::class
        )
            ->middleware("can:joysys.permiso,'dashboard.ver'")
            ->name('dashboard');

    });

});
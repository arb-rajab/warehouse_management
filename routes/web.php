<?php

use App\Http\Controllers\Admin\CellController;
use App\Http\Controllers\Admin\CellStatusLogController;
use App\Http\Controllers\Admin\RowController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\LoginController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::post('locale/{locale}', [LocaleController::class, 'update'])->name('locale.update');

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::redirect('/', '/admin/rows');

        Route::get('rows', [RowController::class, 'index'])->name('rows.index');
        Route::get('rows/create', [RowController::class, 'create'])->name('rows.create');
        Route::post('rows', [RowController::class, 'store'])->name('rows.store');
        Route::get('rows/{row}', [RowController::class, 'show'])->name('rows.show');
        Route::get('rows/{row}/edit', [RowController::class, 'edit'])->name('rows.edit');
        Route::put('rows/{row}', [RowController::class, 'update'])->name('rows.update');
        Route::delete('rows/{row}', [RowController::class, 'destroy'])->name('rows.destroy');

        Route::get('cells', [CellController::class, 'index'])->name('cells.index');

        Route::get('cell-logs', [CellStatusLogController::class, 'index'])->name('cell-logs.index');

        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::get('users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });
});

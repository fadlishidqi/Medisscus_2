<?php

use Illuminate\Support\Facades\Route;

Route::get('/reset-password', function () {
    return view('reset-password');
})->name('password.reset');

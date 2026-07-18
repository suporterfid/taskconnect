<?php

use Illuminate\Support\Facades\Route;

Route::get('/{any?}', function () {
    return response()
        ->view('app')
        ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
        ->header('Pragma', 'no-cache');
})->where('any', '^(?!api|sanctum|build|up).*$');

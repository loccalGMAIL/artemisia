<?php

use App\Http\Controllers\Auth\GoogleLoginController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'landing.index')->name('landing');

// One pair of Google routes per portal (D-10); the portal id doubles as the panel id.
foreach (['staff' => 'staff', 'client' => 'portal'] as $portal => $prefix) {
    Route::prefix("{$prefix}/auth/google")->group(function () use ($portal) {
        Route::get('redirect', [GoogleLoginController::class, 'redirect'])
            ->defaults('portal', $portal)
            ->name("google.{$portal}.redirect");

        Route::get('callback', [GoogleLoginController::class, 'callback'])
            ->defaults('portal', $portal)
            ->name("google.{$portal}.callback");
    });
}

<?php

use Illuminate\Support\Facades\Route;

Route::domain(config('app.domain'))->group(function () {
    Route::get('/', function () {
        $scheme = request()->getScheme();
        $port = request()->getPort();
        $portSuffix = in_array($port, [80, 443], true) ? '' : ":{$port}";
        $domain = config('app.domain');

        return view('home', [
            'adminUrl' => "{$scheme}://admin.{$domain}{$portSuffix}",
            'clienteUrl' => "{$scheme}://clientes.{$domain}{$portSuffix}",
        ]);
    })->name('home');
});

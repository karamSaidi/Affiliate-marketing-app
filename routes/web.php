<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    app()->setLocale('en');

    return view('welcome');
});

Route::get('/{locale}', function (string $locale) {
    abort_unless(in_array($locale, ['ar', 'en'], true), 404);
    app()->setLocale($locale);

    return view('welcome');
})->whereIn('locale', ['ar', 'en']);

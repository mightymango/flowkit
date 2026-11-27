<?php

use MightyMango\Flowkit\Models\Flow;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('flowkit::pages.index'))->name('index');

Route::get('/{flow}', fn (Flow $flow) => view('flowkit::pages.edit', ['flow' => $flow]))->name('edit');

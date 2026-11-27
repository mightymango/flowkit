<?php

use Illuminate\Support\Facades\Route;
use MightyMango\Flowkit\Models\Flow;

Route::get('/', fn () => view('flowkit::pages.index'))->name('index');

Route::get('/{flow}', fn (Flow $flow) => view('flowkit::pages.edit', ['flow' => $flow]))->name('edit');

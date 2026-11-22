<?php

use Platform\Radar\Livewire\Dashboard;
use Platform\Radar\Livewire\Sources;

Route::get('/', Dashboard::class)->name('radar.dashboard');
Route::get('/sources', Sources::class)->name('radar.sources.index');

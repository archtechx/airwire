<?php

use Airwire\Http\AirwireController;
use Illuminate\Support\Facades\Route;

Route::post('/airwire/{component}/{target?}', AirwireController::class)->name('airwire.component');

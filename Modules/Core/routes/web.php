<?php

use Illuminate\Support\Facades\Route;

// Web UI for the Core module (Stammdaten) is built in a later sprint;
// all functions are already available via /api/v1/companies and /api/v1/persons.
Route::middleware(['auth', 'verified'])->group(function () {
    //
});

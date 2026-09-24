<?php

use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    // Data Lake ist API-first (R3); keine eigenen Web-Views.
});

<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\WazuhAlertController;

Route::post('/v1/wazuh-alerts', [WazuhAlertController::class, 'store']);

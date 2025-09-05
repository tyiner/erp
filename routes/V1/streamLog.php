<?php

use Illuminate\Support\Facades\Route;

Route::group(
    ['namespace' => 'SerialStream', 'prefix' => 'Admin'],
    function () {
        Route::post('/stream-log', 'StreamLogController@send');
    }
);

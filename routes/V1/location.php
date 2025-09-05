<?php

use Illuminate\Support\Facades\Route;

Route::group(['namespace' => 'Stock', 'prefix' => 'Admin/location'], function () {
    Route::post('/', 'LocationController@create')->name('location.create');
    Route::get('/', 'LocationController@getList')->name('location.list');
    Route::delete('/', 'LocationController@delete')->name('location.delete');
    Route::put('/', 'LocationController@update')->name('location.update');
});

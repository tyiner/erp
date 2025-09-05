<?php

use Illuminate\Support\Facades\Route;

Route::group(['namespace' => 'Purchase', 'prefix' => 'Admin/company'], function () {
    Route::post('/', "CompanyController@create")->name('company.create');
    Route::delete('/', "CompanyController@destroy")->name('company.destroy');
    Route::put('/', "CompanyController@update")->name('company.update');
    Route::get('/', "CompanyController@getList")->name('company.list');
    Route::get('/location-list', "CompanyController@getLocations")->name('company.location.list');
});

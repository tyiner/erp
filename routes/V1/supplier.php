<?php

use Illuminate\Support\Facades\Route;

Route::group(['namespace' => 'Purchase', 'prefix' => 'Admin/supplier'], function () {
    Route::post('/', "SupplierController@create")->name('supplier.create');
    Route::delete('/', "SupplierController@destroy")->name('supplier.destroy');
    Route::put('/', "SupplierController@update")->name('supplier.update');
    Route::get('/', "SupplierController@getList")->name('supplier.list');
});

Route::group(['namespace' => 'Purchase', 'prefix' => 'Seller/supplier'], function () {
    Route::post('/', "SupplierController@create");
    Route::delete('/', "SupplierController@destroy");
    Route::put('/', "SupplierController@update");
    Route::get('/', "SupplierController@getList");
});

<?php

use Illuminate\Support\Facades\Route;

Route::group(['namespace' => 'Purchase', 'prefix' => 'Admin/department'], function () {
    Route::post('/', "DepartmentController@create")->name('department.create');
    Route::delete('/', "DepartmentController@destroy")->name('department.destroy');
    Route::put('/', "DepartmentController@update")->name('department.update');
    Route::get('/', "DepartmentController@getList")->name('department.list');
});

<?php
Route::group(['namespace' => 'Admin', 'as' => 'admin.'], function () {


    Route::get('login', ['as' => 'auth.showlogin', 'uses' => 'AuthController@cmsUserLogin']);
    Route::post('login', ['as' => 'auth.dologin', 'uses' => 'AuthController@doCmsUserLogin']);

    Route::group(['middleware' => 'authentication'], function () {

        Route::get('logout', ['as' => 'home.logout', 'uses' => 'AuthController@logout']);
        Route::get('dashboard', ['as' => 'home.dashboard', 'uses' => 'DashboardController@getDashboard']);
        Route::resource('producer', 'ProducerController');
        Route::resource('cmsusers', 'CmsuserController');
        Route::get('/session-report', [ 'as' => 'report.session', 'uses' => 'ReportController@getSessionReport']);
    });// authentication middleware

});
 

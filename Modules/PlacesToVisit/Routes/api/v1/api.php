<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - PlacesToVisit Module
|--------------------------------------------------------------------------
*/

Route::group(['prefix' => 'places'], function () {
    
    // Public routes (no auth required)
    Route::get('categories', 'PlaceCategoryController@index');
    Route::get('categories/{category}', 'PlaceCategoryController@show');
    Route::get('/', 'PlaceController@index');
    Route::get('leaderboard', 'PlaceController@leaderboard');
    Route::get('{place}', 'PlaceController@show');
    
    // Protected routes (auth required)
    Route::group(['middleware' => ['auth:api']], function () {
        Route::post('{place}/vote', 'VoteController@vote');
        Route::delete('{place}/vote', 'VoteController@removeVote');
        Route::get('{place}/vote-status', 'VoteController@status');
        Route::post('votes/{vote}/report', 'VoteController@report');
    });
});

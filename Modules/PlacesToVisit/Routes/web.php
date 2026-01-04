<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes - PlacesToVisit Module (Admin)
|--------------------------------------------------------------------------
*/

Route::group([
    'prefix' => 'admin/places',
    'as' => 'admin.places.',
    'middleware' => ['admin', 'current-module'],
    'namespace' => 'Admin'
], function () {
    
    // Categories
    Route::group(['prefix' => 'categories', 'as' => 'categories.'], function () {
        Route::get('/', 'PlaceCategoryController@index')->name('index');
        Route::get('create', 'PlaceCategoryController@create')->name('create');
        Route::post('/', 'PlaceCategoryController@store')->name('store');
        Route::get('{category}/edit', 'PlaceCategoryController@edit')->name('edit');
        Route::put('{category}', 'PlaceCategoryController@update')->name('update');
        Route::delete('{category}', 'PlaceCategoryController@destroy')->name('destroy');
        Route::get('{category}/toggle-status', 'PlaceCategoryController@toggleStatus')->name('toggle-status');
    });

    // Places
    Route::get('/', 'PlaceController@index')->name('index');
    Route::get('create', 'PlaceController@create')->name('create');
    Route::post('/', 'PlaceController@store')->name('store');
    Route::get('{place}/edit', 'PlaceController@edit')->name('edit');
    Route::put('{place}', 'PlaceController@update')->name('update');
    Route::delete('{place}', 'PlaceController@destroy')->name('destroy');
    Route::get('{place}/toggle-status', 'PlaceController@toggleStatus')->name('toggle-status');
    Route::get('{place}/toggle-featured', 'PlaceController@toggleFeatured')->name('toggle-featured');

    // Leaderboard & Votes
    Route::group(['prefix' => 'leaderboard', 'as' => 'leaderboard.'], function () {
        Route::get('/', 'LeaderboardController@index')->name('index');
        Route::get('votes', 'LeaderboardController@votes')->name('votes');
        Route::get('votes/{vote}/toggle-flag', 'LeaderboardController@toggleFlag')->name('toggle-flag');
        Route::delete('votes/{vote}', 'LeaderboardController@deleteVote')->name('delete-vote');
        Route::post('clear-cache', 'LeaderboardController@clearCache')->name('clear-cache');
    });
});

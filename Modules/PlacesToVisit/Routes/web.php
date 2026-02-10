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
    Route::get('{place}', 'PlaceController@show')->name('show');
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

    // Banners
    Route::group(['prefix' => 'banners', 'as' => 'banners.'], function () {
        Route::get('/', 'PlaceBannerController@index')->name('index');
        Route::get('create', 'PlaceBannerController@create')->name('create');
        Route::post('/', 'PlaceBannerController@store')->name('store');
        Route::get('{banner}/edit', 'PlaceBannerController@edit')->name('edit');
        Route::put('{banner}', 'PlaceBannerController@update')->name('update');
        Route::delete('{banner}', 'PlaceBannerController@destroy')->name('destroy');
        Route::get('{banner}/toggle-status', 'PlaceBannerController@toggleStatus')->name('toggle-status');
        Route::get('{banner}/toggle-featured', 'PlaceBannerController@toggleFeatured')->name('toggle-featured');
    });

    // Offers
    Route::group(['prefix' => 'offers', 'as' => 'offers.'], function () {
        Route::get('/', 'PlaceOfferController@index')->name('index');
        Route::get('create', 'PlaceOfferController@create')->name('create');
        Route::post('/', 'PlaceOfferController@store')->name('store');
        Route::get('{offer}/edit', 'PlaceOfferController@edit')->name('edit');
        Route::put('{offer}', 'PlaceOfferController@update')->name('update');
        Route::delete('{offer}', 'PlaceOfferController@destroy')->name('destroy');
        Route::get('{offer}/toggle-status', 'PlaceOfferController@toggleStatus')->name('toggle-status');
    });

    // Submissions
    Route::group(['prefix' => 'submissions', 'as' => 'submissions.'], function () {
        Route::get('/', 'PlaceSubmissionController@index')->name('index');
        Route::get('{submission}', 'PlaceSubmissionController@show')->name('show');
        Route::post('{submission}/approve', 'PlaceSubmissionController@approve')->name('approve');
        Route::post('{submission}/reject', 'PlaceSubmissionController@reject')->name('reject');
        Route::delete('{submission}', 'PlaceSubmissionController@destroy')->name('destroy');
    });
});

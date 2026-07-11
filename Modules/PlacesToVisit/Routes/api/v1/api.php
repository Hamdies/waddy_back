<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - PlacesToVisit Module
|--------------------------------------------------------------------------
*/

Route::group(['prefix' => 'places'], function () {
    
    // ==================== Public Routes ====================
    
    Route::get('categories', 'PlaceCategoryController@index');
    Route::get('categories/{category}', 'PlaceCategoryController@show');
    Route::get('tags', 'PlaceCategoryController@tags');
    Route::get('/', 'PlaceController@index');
    Route::get('leaderboard', 'PlaceController@leaderboard');
    Route::get('top-voters', 'PlaceController@topVoters');
    Route::get('trending', 'PlaceController@trending');

    // Weekly winners (hall of fame + latest champion)
    Route::get('winners', 'WinnerController@index');
    Route::get('winners/latest', 'WinnerController@latest');

    // KPI events from the app (guests count too; auth attaches user when present)
    Route::post('events', 'PlaceEventController@store')->middleware('throttle:60,1');
    
    // Banners (public)
    Route::get('banners', 'PlaceBannerController@index');
    Route::get('banners/featured', 'PlaceBannerController@featured');
    
    // Zones
    Route::get('zones', 'PlaceZoneController@index');
    Route::get('zones/{zoneId}/places', 'PlaceZoneController@places');
    
    Route::get('{place}', 'PlaceController@show');
    Route::get('{place}/reviews', 'VoteController@reviews');
    
    // ==================== Protected Routes ====================
    
    Route::group(['middleware' => ['auth:api']], function () {
        // Voting & Reviews (rate-limited to deter abuse)
        Route::post('{place}/vote', 'VoteController@vote')->middleware('throttle:30,1');
        Route::delete('{place}/vote', 'VoteController@removeVote')->middleware('throttle:30,1');
        Route::get('{place}/vote-status', 'VoteController@status');
        Route::post('votes/{vote}/report', 'VoteController@report')->middleware('throttle:10,1');

        // Favorites
        Route::get('favorites/my', 'PlaceFavoriteController@index');
        Route::post('{place}/favorite', 'PlaceFavoriteController@store');
        Route::delete('{place}/favorite', 'PlaceFavoriteController@destroy');
        Route::post('{place}/toggle-favorite', 'PlaceFavoriteController@toggle');

        // Submissions
        Route::get('submissions/my', 'PlaceSubmissionController@index');
        Route::post('submissions', 'PlaceSubmissionController@store')->middleware('throttle:10,1');
        Route::get('submissions/{submission}', 'PlaceSubmissionController@show');
    });
});

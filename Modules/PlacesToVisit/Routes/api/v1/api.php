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
    Route::get('trending', 'PlaceController@trending');
    
    // Banners (public)
    Route::get('banners', 'PlaceBannerController@index');
    Route::get('banners/featured', 'PlaceBannerController@featured');
    
    Route::get('{place}', 'PlaceController@show');
    Route::get('{place}/reviews', 'VoteController@reviews');
    
    // ==================== Protected Routes ====================
    
    Route::group(['middleware' => ['auth:api']], function () {
        // Voting & Reviews
        Route::post('{place}/vote', 'VoteController@vote');
        Route::delete('{place}/vote', 'VoteController@removeVote');
        Route::get('{place}/vote-status', 'VoteController@status');
        Route::post('votes/{vote}/report', 'VoteController@report');
        
        // Favorites
        Route::get('favorites/my', 'PlaceFavoriteController@index');
        Route::post('{place}/favorite', 'PlaceFavoriteController@store');
        Route::delete('{place}/favorite', 'PlaceFavoriteController@destroy');
        Route::post('{place}/toggle-favorite', 'PlaceFavoriteController@toggle');
        
        // Submissions
        Route::get('submissions/my', 'PlaceSubmissionController@index');
        Route::post('submissions', 'PlaceSubmissionController@store');
        Route::get('submissions/{submission}', 'PlaceSubmissionController@show');
    });
});

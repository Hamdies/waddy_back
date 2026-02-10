<?php

return [
    'name' => 'PlacesToVisit',
    
    // ==================== Leaderboard ====================
    
    // Minimum votes required to appear on leaderboard
    'min_votes_for_leaderboard' => 5,
    
    // Number of top places to show
    'leaderboard_limit' => 10,
    
    // Cache duration in minutes
    'leaderboard_cache_minutes' => 60,

    // ==================== Trending ====================

    'trending' => [
        'limit' => 10,
        'cache_minutes' => 30,
        'window_days' => 7, // Days to look back for "recent" votes
    ],

    // ==================== Banners ====================

    'banners' => [
        'max_featured_banners' => 5,
        'default_image_size' => '1920x600',
        'allowed_types' => ['default', 'category', 'place', 'external'],
    ],

    // ==================== XP Rewards ====================

    'xp' => [
        'vote' => 5,              // XP for voting on a place
        'review' => 10,           // Additional XP for writing a text review
        'photo_review' => 15,     // Additional XP for adding a photo to review
        'submission_approved' => 25, // XP for an approved place submission
    ],

    // ==================== Moderation ====================

    'report_auto_flag_threshold' => 3, // Number of reports before auto-flagging a review

    // ==================== Submissions ====================

    'submissions' => [
        'max_pending_per_user' => 5, // Max number of pending submissions per user
        'image_max_size' => 2048,    // Max image size in KB
    ],
];

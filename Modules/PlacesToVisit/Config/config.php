<?php

return [
    'name' => 'PlacesToVisit',
    
    // Minimum votes required to appear on leaderboard
    'min_votes_for_leaderboard' => 5,
    
    // Number of top places to show
    'leaderboard_limit' => 10,
    
    // Cache duration in minutes
    'leaderboard_cache_minutes' => 60,
    
    // Banner settings
    'banners' => [
        'max_featured_banners' => 5,
        'default_image_size' => '1920x600',
        'allowed_types' => ['default', 'category', 'place', 'external'],
    ],
];

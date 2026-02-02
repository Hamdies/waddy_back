<?php

/**
 * Order Configuration
 * 
 * Centralized configuration for order and dispatch settings.
 * These values were previously hardcoded throughout the controllers.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Delivery Man Settings
    |--------------------------------------------------------------------------
    */
    
    // Maximum orders a delivery man can accept at once
    'dm_maximum_orders' => env('DM_MAXIMUM_ORDERS', 10),
    
    // Maximum cash in hand for delivery man (0 = unlimited)
    'dm_max_cash_in_hand' => env('DM_MAX_CASH_IN_HAND', 50000),
    
    // Cash in hand overflow check flag
    'cash_in_hand_overflow_deliveryman' => env('CASH_IN_HAND_OVERFLOW_DM', true),

    /*
    |--------------------------------------------------------------------------
    | Order Scheduling
    |--------------------------------------------------------------------------
    */
    
    // Minutes ahead to show scheduled orders for delivery men
    'schedule_order_lookahead_minutes' => env('ORDER_SCHEDULE_LOOKAHEAD', 30),

    /*
    |--------------------------------------------------------------------------
    | OTP Verification
    |--------------------------------------------------------------------------
    */
    
    // Maximum OTP verification attempts before rate limiting
    'otp_max_attempts' => env('OTP_MAX_ATTEMPTS', 5),
    
    // OTP rate limit decay time in minutes
    'otp_decay_minutes' => env('OTP_DECAY_MINUTES', 15),

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    */
    
    // Default pagination limit for latest orders API
    'latest_orders_default_limit' => env('LATEST_ORDERS_LIMIT', 50),
    
    // Maximum pagination limit
    'latest_orders_max_limit' => env('LATEST_ORDERS_MAX_LIMIT', 100),

    /*
    |--------------------------------------------------------------------------
    | Order Status Transitions
    |--------------------------------------------------------------------------
    | Define valid status transitions for orders
    */
    
    'valid_transitions' => [
        'pending' => ['confirmed', 'accepted', 'canceled'],
        'confirmed' => ['accepted', 'processing', 'canceled'],
        'accepted' => ['processing', 'handover', 'canceled'],
        'processing' => ['handover', 'canceled'],
        'handover' => ['picked_up', 'canceled'],
        'picked_up' => ['out_for_delivery', 'delivered', 'canceled'],
        'out_for_delivery' => ['delivered', 'canceled'],
        'delivered' => ['refund_requested'],
        'refund_requested' => ['refunded'],
        'canceled' => [],
        'refunded' => [],
        'failed' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Order Confirmation Model
    |--------------------------------------------------------------------------
    | Who can confirm orders: 'store' or 'deliveryman'
    */
    
    'confirmation_model' => env('ORDER_CONFIRMATION_MODEL', 'store'),

    /*
    |--------------------------------------------------------------------------
    | Cancellation Settings
    |--------------------------------------------------------------------------
    */
    
    'canceled_by_deliveryman' => env('CANCELED_BY_DELIVERYMAN', false),
    'canceled_by_store' => env('CANCELED_BY_STORE', true),
    'canceled_by_customer' => env('CANCELED_BY_CUSTOMER', true),

    /*
    |--------------------------------------------------------------------------
    | Delivery Verification
    |--------------------------------------------------------------------------
    */
    
    'delivery_verification' => env('ORDER_DELIVERY_VERIFICATION', true),
];

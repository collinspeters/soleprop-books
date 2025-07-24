<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Receipt Processing Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration options for receipt upload and processing functionality.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Async Processing
    |--------------------------------------------------------------------------
    |
    | Whether to process receipts asynchronously using queues or synchronously.
    | Set to false for immediate processing (useful for development/testing).
    |
    */
    'async_processing' => env('RECEIPT_ASYNC_PROCESSING', true),

    /*
    |--------------------------------------------------------------------------
    | File Upload Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for receipt file uploads.
    |
    */
    'upload' => [
        'max_size' => env('RECEIPT_MAX_SIZE', 10240), // KB (10MB default)
        'allowed_types' => ['jpg', 'jpeg', 'png', 'gif', 'pdf'],
        'storage_disk' => env('RECEIPT_STORAGE_DISK', 'local'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Transaction Matching Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for automatic transaction matching.
    |
    */
    'matching' => [
        'date_tolerance_days' => env('RECEIPT_DATE_TOLERANCE', 5),
        'minimum_match_score' => env('RECEIPT_MIN_MATCH_SCORE', 0.7),
        'auto_match_threshold' => env('RECEIPT_AUTO_MATCH_THRESHOLD', 0.8),
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Categorization Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for AI-powered expense categorization.
    |
    */
    'ai' => [
        'enabled' => env('RECEIPT_AI_ENABLED', true),
        'fallback_to_rules' => env('RECEIPT_AI_FALLBACK', true),
        'confidence_threshold' => env('RECEIPT_AI_CONFIDENCE_THRESHOLD', 0.6),
    ],
];
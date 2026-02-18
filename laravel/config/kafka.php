<?php

return [
    'brokers' => env('KAFKA_BROKERS', 'localhost:9092'),
    'consumer_group_id' => env('KAFKA_CONSUMER_GROUP_ID', 'laravel-consumer-group'),
    'consumer_timeout_ms' => (int) env('KAFKA_CONSUMER_DEFAULT_TIMEOUT', 2000),
    'offset_reset' => env('KAFKA_OFFSET_RESET', 'earliest'),
    'auto_commit' => (bool) env('KAFKA_AUTO_COMMIT', true),
    'sleep_on_error' => (int) env('KAFKA_ERROR_SLEEP', 5),
    'partition' => (int) env('KAFKA_PARTITION', 0),
    'compression' => env('KAFKA_COMPRESSION_TYPE', 'none'),
    'debug' => (bool) env('KAFKA_DEBUG', false),
    'batch_repository' => env('KAFKA_BATCH_REPOSITORY', 'Junges\Kafka\BatchRepositories\InMemoryBatchRepository'),
    'flush_retry_sleep_in_ms' => 100,
    'cache_driver' => env('KAFKA_CACHE_DRIVER', env('CACHE_DRIVER', 'file')),

    'topic' => env('KAFKA_TOPIC', 'laravel-events'),

    // SASL (Fase 1) — usado pelo mateusjunges/laravel-kafka
    'securityProtocol' => env('KAFKA_SECURITY_PROTOCOL', 'SASL_PLAINTEXT'),
    'sasl' => [
        'username' => env('KAFKA_SASL_USERNAME', ''),
        'password' => env('KAFKA_SASL_PASSWORD', ''),
        'mechanisms' => env('KAFKA_SASL_MECHANISMS', 'PLAIN'),
        'security_protocol' => env('KAFKA_SECURITY_PROTOCOL', 'SASL_PLAINTEXT'),
    ],
];

<?php

return [
    'brokers' => env('KAFKA_BROKERS', 'localhost:9092'),
    'topics' => [
        'orders' => 'orders',
        'stock-alerts' => 'stock-alerts',
        'restock' => 'restock',
    ],
];

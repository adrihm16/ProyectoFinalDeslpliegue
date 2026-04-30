<?php
function getRedisConnection() {
    $host = getenv('REDIS_HOST') ?: 'redis';
    $port = getenv('REDIS_PORT') ?: 6379;

    $redis = new Redis();
    try {
        $redis->connect($host, $port);
    } catch (Exception $e) {
        die('Error conectando a Redis: ' . $e->getMessage());
    }
    return $redis;
}

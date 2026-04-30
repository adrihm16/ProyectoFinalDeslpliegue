<?php
function metricsRequestStart() {
    return microtime(true);
}

function metricsRecordRequest($redis, $service) {
    $redis->incr('metrics:requests:total');
    $redis->incr('metrics:requests:' . $service);
}

function metricsRecordResponse($redis, $startTime) {
    $elapsedMs = (microtime(true) - $startTime) * 1000;
    $redis->incrByFloat('metrics:response:sum_ms', $elapsedMs);
    $redis->incr('metrics:response:count');
    $redis->set('metrics:response:last_ms', (string)round($elapsedMs, 2));
}

function metricsRecordCache($redis, $hit) {
    $key = $hit ? 'metrics:cache:hit' : 'metrics:cache:miss';
    $redis->incr($key);
}
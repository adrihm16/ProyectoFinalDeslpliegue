<?php
require_once '../config/database.php';
require_once '../config/redis.php';
require_once '../config/metrics.php';

$db = getDBConnection();
$redis = getRedisConnection();
$startTime = metricsRequestStart();
metricsRecordRequest($redis, 'frontend');

$requestsTotal = (int)($redis->get('metrics:requests:total') ?: 0);
$requestsFrontend = (int)($redis->get('metrics:requests:frontend') ?: 0);
$requestsApi = (int)($redis->get('metrics:requests:api') ?: 0);

$responseSum = (float)($redis->get('metrics:response:sum_ms') ?: 0);
$responseCount = (int)($redis->get('metrics:response:count') ?: 0);
$avgResponse = $responseCount ? $responseSum / $responseCount : 0;
$lastResponse = (float)($redis->get('metrics:response:last_ms') ?: 0);

$cacheHit = (int)($redis->get('metrics:cache:hit') ?: 0);
$cacheMiss = (int)($redis->get('metrics:cache:miss') ?: 0);
$cacheTotal = $cacheHit + $cacheMiss;
$cacheRate = $cacheTotal ? ($cacheHit / $cacheTotal) * 100 : 0;

$totalPosts = (int)$db->query('SELECT COUNT(*) FROM posts')->fetchColumn();
$totalUsers = (int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn();
$totalComments = (int)$db->query('SELECT COUNT(*) FROM comments')->fetchColumn();
$totalViews = (int)$db->query('SELECT COALESCE(SUM(views), 0) FROM posts')->fetchColumn();

$dbStatus = 'OK';
$redisStatus = $redis->ping() ? 'OK' : 'ERROR';

$apiStatus = 'ERROR';
$apiContext = stream_context_create([
    'http' => [
        'timeout' => 1,
    ],
]);
$apiResponse = @file_get_contents('http://api:8000/api/stats', false, $apiContext);
if ($apiResponse) {
    $apiStatus = 'OK';
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoreo - MicroBlog</title>
    <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: linear-gradient(135deg, #1f2937 0%, #374151 100%);
        min-height: 100vh;
        padding: 20px;
        color: #f9fafb;
    }

    .container {
        max-width: 1100px;
        margin: 0 auto;
    }

    .header {
        background: rgba(255, 255, 255, 0.08);
        padding: 25px;
        border-radius: 15px;
        margin-bottom: 25px;
        backdrop-filter: blur(6px);
    }

    h1 {
        font-size: 2.2em;
        margin-bottom: 5px;
    }

    .subtitle {
        color: #d1d5db;
    }

    .grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
    }

    .card {
        background: rgba(255, 255, 255, 0.1);
        padding: 20px;
        border-radius: 15px;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.25);
    }

    .card h2 {
        font-size: 1.2em;
        margin-bottom: 10px;
        color: #93c5fd;
    }

    .metric {
        font-size: 1.8em;
        font-weight: 700;
        margin-bottom: 4px;
    }

    .label {
        color: #e5e7eb;
        font-size: 0.9em;
    }

    .status {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 0.85em;
        font-weight: 600;
    }

    .ok {
        background: #10b981;
        color: #0f172a;
    }

    .error {
        background: #f97316;
        color: #111827;
    }

    .btn {
        display: inline-block;
        margin-top: 15px;
        background: #60a5fa;
        color: #0f172a;
        padding: 8px 16px;
        border-radius: 25px;
        text-decoration: none;
        font-weight: 600;
    }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>Panel de Monitoreo</h1>
            <p class="subtitle">Resumen de actividad y salud del sistema</p>
            <a class="btn" href="index.php">Volver al inicio</a>
        </div>

        <div class="grid">
            <div class="card">
                <h2>Peticiones</h2>
                <div class="metric"><?php echo $requestsTotal; ?></div>
                <div class="label">Total</div>
            </div>
            <div class="card">
                <h2>Frontend</h2>
                <div class="metric"><?php echo $requestsFrontend; ?></div>
                <div class="label">Peticiones</div>
            </div>
            <div class="card">
                <h2>API</h2>
                <div class="metric"><?php echo $requestsApi; ?></div>
                <div class="label">Peticiones</div>
            </div>
            <div class="card">
                <h2>Tiempo de respuesta</h2>
                <div class="metric"><?php echo number_format($avgResponse, 2); ?> ms</div>
                <div class="label">Promedio (ultimo <?php echo $lastResponse; ?> ms)</div>
            </div>
            <div class="card">
                <h2>Uso de cach&eacute;</h2>
                <div class="metric"><?php echo number_format($cacheRate, 1); ?>%</div>
                <div class="label">Hits: <?php echo $cacheHit; ?> / Misses: <?php echo $cacheMiss; ?></div>
            </div>
        </div>

        <div class="grid" style="margin-top:20px;">
            <div class="card">
                <h2>Estado de servicios</h2>
                <p class="label">MySQL: <span class="status <?php echo $dbStatus === 'OK' ? 'ok' : 'error'; ?>"><?php echo $dbStatus; ?></span></p>
                <p class="label">Redis: <span class="status <?php echo $redisStatus === 'OK' ? 'ok' : 'error'; ?>"><?php echo $redisStatus; ?></span></p>
                <p class="label">API: <span class="status <?php echo $apiStatus === 'OK' ? 'ok' : 'error'; ?>"><?php echo $apiStatus; ?></span></p>
            </div>
            <div class="card">
                <h2>Datos</h2>
                <p class="label">Posts: <?php echo $totalPosts; ?></p>
                <p class="label">Usuarios: <?php echo $totalUsers; ?></p>
                <p class="label">Comentarios: <?php echo $totalComments; ?></p>
                <p class="label">Vistas totales: <?php echo $totalViews; ?></p>
            </div>
        </div>
    </div>
    <?php metricsRecordResponse($redis, $startTime); ?>
</body>

</html>

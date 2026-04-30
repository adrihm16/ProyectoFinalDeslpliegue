<?php
session_start();
require_once '../config/database.php';
require_once '../config/redis.php';
require_once '../config/metrics.php';

// Conectar a la base de datos
$db = getDBConnection();
$redis = getRedisConnection();
$startTime = metricsRequestStart();
metricsRecordRequest($redis, 'frontend');

// Obtener posts desde caché o BD
$cacheKey = 'blog:posts:all';
$posts = $redis->get($cacheKey);
$cacheHit = false;

if (!$posts) {
    $stmt = $db->query("SELECT p.*, u.username, COUNT(c.id) as comment_count 
                        FROM posts p 
                        LEFT JOIN users u ON p.user_id = u.id 
                        LEFT JOIN comments c ON p.id = c.post_id 
                        GROUP BY p.id 
                        ORDER BY p.created_at DESC");
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $redis->setex($cacheKey, 300, json_encode($posts)); // 5 minutos
    metricsRecordCache($redis, false);
} else {
    $posts = json_decode($posts, true);
    $cacheHit = true;
    metricsRecordCache($redis, true);
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MicroBlog - Sistema de Blog con Docker</title>
    <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
        padding: 20px;
    }

    .container {
        max-width: 1200px;
        margin: 0 auto;
    }

    header {
        background: white;
        padding: 30px;
        border-radius: 15px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        margin-bottom: 30px;
        text-align: center;
    }

    h1 {
        color: #667eea;
        font-size: 2.5em;
        margin-bottom: 10px;
    }

    .subtitle {
        color: #666;
        font-size: 1.1em;
    }

    .stats {
        display: flex;
        justify-content: center;
        gap: 30px;
        margin-top: 20px;
        padding-top: 20px;
        border-top: 2px solid #f0f0f0;
    }

    .stat-item {
        text-align: center;
    }

    .stat-number {
        font-size: 2em;
        font-weight: bold;
        color: #667eea;
    }

    .stat-label {
        color: #666;
        font-size: 0.9em;
    }

    .posts-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 25px;
        margin-bottom: 30px;
    }

    .post-card {
        background: white;
        border-radius: 15px;
        padding: 25px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .post-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
    }

    .post-title {
        color: #333;
        font-size: 1.5em;
        margin-bottom: 10px;
        font-weight: 600;
    }

    .post-meta {
        display: flex;
        gap: 15px;
        color: #888;
        font-size: 0.9em;
        margin-bottom: 15px;
        padding-bottom: 15px;
        border-bottom: 1px solid #eee;
    }

    .post-excerpt {
        color: #555;
        line-height: 1.6;
        margin-bottom: 15px;
    }

    .post-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .btn {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 10px 20px;
        border-radius: 25px;
        text-decoration: none;
        font-weight: 600;
        transition: opacity 0.3s ease;
    }

    .btn:hover {
        opacity: 0.8;
    }

    .comment-count {
        color: #888;
        font-size: 0.9em;
    }

    .new-post-btn {
        position: fixed;
        bottom: 30px;
        right: 30px;
        width: 60px;
        height: 60px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 2em;
        text-decoration: none;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.3);
        transition: transform 0.3s ease;
    }

    .new-post-btn:hover {
        transform: scale(1.1);
    }

    .system-info {
        background: white;
        padding: 20px;
        border-radius: 15px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        margin-top: 30px;
    }

    .system-info h3 {
        color: #667eea;
        margin-bottom: 15px;
    }

    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
    }

    .info-item {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        border-left: 4px solid #667eea;
    }

    .info-label {
        font-weight: 600;
        color: #333;
        margin-bottom: 5px;
    }

    .info-value {
        color: #666;
        font-size: 0.9em;
    }
    </style>
</head>

<body>
    <div class="container">
        <header>
            <h1>🚀 MicroBlog</h1>
            <p class="subtitle">Sistema de Blog con Arquitectura de Microservicios</p>

            <div class="stats">
                <div class="stat-item">
                    <div class="stat-number"><?php echo count($posts); ?></div>
                    <div class="stat-label">Posts Publicados</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">
                        <?php 
                        $userCount = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
                        echo $userCount;
                        ?>
                    </div>
                    <div class="stat-label">Usuarios</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">
                        <?php 
                        $commentCount = $db->query("SELECT COUNT(*) FROM comments")->fetchColumn();
                        echo $commentCount;
                        ?>
                    </div>
                    <div class="stat-label">Comentarios</div>
                </div>
            </div>
        </header>

        <div class="posts-grid">
            <?php foreach ($posts as $post): ?>
            <div class="post-card">
                <h2 class="post-title"><?php echo htmlspecialchars($post['title']); ?></h2>
                <div class="post-meta">
                    <span>👤 <?php echo htmlspecialchars($post['username']); ?></span>
                    <span>📅 <?php echo date('d/m/Y', strtotime($post['created_at'])); ?></span>
                </div>
                <p class="post-excerpt"><?php echo htmlspecialchars(substr($post['content'], 0, 150)) . '...'; ?></p>
                <div class="post-footer">
                    <a href="post.php?id=<?php echo $post['id']; ?>" class="btn">Leer más</a>
                    <span class="comment-count">💬 <?php echo $post['comment_count']; ?> comentarios</span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="system-info">
            <h3>ℹ️ Información del Sistema</h3>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Servidor Web</div>
                    <div class="info-value"><?php echo $_SERVER['SERVER_SOFTWARE']; ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">PHP Version</div>
                    <div class="info-value"><?php echo phpversion(); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Base de Datos</div>
                    <div class="info-value">
                        <?php echo $db->getAttribute(PDO::ATTR_SERVER_VERSION); ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">Redis</div>
                    <div class="info-value">
                        <?php echo $redis->ping() ? 'Conectado ✓' : 'Desconectado ✗'; ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">Caché activa</div>
                    <div class="info-value">
                        <?php echo $cacheHit ? 'Sí (desde caché)' : 'No (desde BD)'; ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">Dashboard</div>
                    <div class="info-value"><a href="monitor.php">Ver monitoreo</a></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Host</div>
                    <div class="info-value"><?php echo gethostname(); ?></div>
                </div>
            </div>
        </div>

        <a href="new-post.php" class="new-post-btn" title="Nuevo Post">+</a>
    </div>
    <?php metricsRecordResponse($redis, $startTime); ?>
</body>

</html>
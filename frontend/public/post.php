<?php
session_start();
require_once '../config/database.php';
require_once '../config/redis.php';
require_once '../config/metrics.php';

$db = getDBConnection();
$redis = getRedisConnection();
$startTime = metricsRequestStart();
metricsRecordRequest($redis, 'frontend');

$postId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$postId) {
    http_response_code(400);
    metricsRecordResponse($redis, $startTime);
    echo 'ID de post invalido.';
    exit;
}

$updateStmt = $db->prepare('UPDATE posts SET views = views + 1 WHERE id = :id');
$updateStmt->execute([':id' => $postId]);

$cacheKey = 'blog:post:' . $postId;
$cached = $redis->get($cacheKey);
$cacheHit = false;

if ($cached) {
    $payload = json_decode($cached, true);
    $post = $payload['post'];
    $comments = $payload['comments'];
    $cacheHit = true;
    metricsRecordCache($redis, true);
} else {
    $postStmt = $db->prepare('SELECT p.*, u.username FROM posts p LEFT JOIN users u ON p.user_id = u.id WHERE p.id = :id');
    $postStmt->execute([':id' => $postId]);
    $post = $postStmt->fetch(PDO::FETCH_ASSOC);

    if (!$post) {
        http_response_code(404);
        metricsRecordResponse($redis, $startTime);
        echo 'Post no encontrado.';
        exit;
    }

    $commentStmt = $db->prepare('SELECT c.*, u.username FROM comments c LEFT JOIN users u ON c.user_id = u.id WHERE c.post_id = :id ORDER BY c.created_at DESC');
    $commentStmt->execute([':id' => $postId]);
    $comments = $commentStmt->fetchAll(PDO::FETCH_ASSOC);

    $payload = ['post' => $post, 'comments' => $comments];
    $redis->setex($cacheKey, 300, json_encode($payload));
    metricsRecordCache($redis, false);
}

if (isset($post['views'])) {
    $post['views'] = (int)$post['views'] + 1;
    if ($cacheHit) {
        $payload['post'] = $post;
        $redis->setex($cacheKey, 300, json_encode($payload));
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($post['title']); ?> - MicroBlog</title>
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
        max-width: 900px;
        margin: 0 auto;
    }

    .card {
        background: white;
        padding: 30px;
        border-radius: 15px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        margin-bottom: 25px;
    }

    .post-title {
        color: #333;
        font-size: 2em;
        margin-bottom: 10px;
    }

    .post-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        color: #777;
        font-size: 0.95em;
        margin-bottom: 20px;
    }

    .post-content {
        color: #444;
        line-height: 1.7;
        font-size: 1.05em;
        margin-bottom: 20px;
        white-space: pre-line;
    }

    .badge {
        background: #f3f4f6;
        color: #555;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.85em;
    }

    .comments-title {
        color: #667eea;
        font-size: 1.4em;
        margin-bottom: 15px;
    }

    .comment {
        border-bottom: 1px solid #eee;
        padding: 15px 0;
    }

    .comment:last-child {
        border-bottom: none;
    }

    .comment-meta {
        color: #888;
        font-size: 0.9em;
        margin-bottom: 8px;
    }

    .comment-content {
        color: #555;
        line-height: 1.5;
    }

    .btn {
        display: inline-block;
        margin-top: 10px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 10px 20px;
        border-radius: 25px;
        text-decoration: none;
        font-weight: 600;
        transition: opacity 0.3s ease;
    }

    .btn:hover {
        opacity: 0.85;
    }

    .cache-info {
        color: #666;
        font-size: 0.9em;
        margin-top: 10px;
    }
    </style>
</head>

<body>
    <div class="container">
        <div class="card">
            <h1 class="post-title"><?php echo htmlspecialchars($post['title']); ?></h1>
            <div class="post-meta">
                <span>👤 <?php echo htmlspecialchars($post['username']); ?></span>
                <span>📅 <?php echo date('d/m/Y', strtotime($post['created_at'])); ?></span>
                <span class="badge">👁️ <?php echo (int)$post['views']; ?> vistas</span>
            </div>
            <div class="post-content"><?php echo htmlspecialchars($post['content']); ?></div>
            <div class="cache-info">Cach&eacute;: <?php echo $cacheHit ? 'hit' : 'miss'; ?></div>
            <a href="index.php" class="btn">Volver al inicio</a>
        </div>

        <div class="card">
            <h2 class="comments-title">Comentarios (<?php echo count($comments); ?>)</h2>
            <?php if (!$comments): ?>
                <p class="comment-content">No hay comentarios para este post.</p>
            <?php else: ?>
                <?php foreach ($comments as $comment): ?>
                    <div class="comment">
                        <div class="comment-meta">
                            👤 <?php echo htmlspecialchars($comment['username']); ?>
                            · <?php echo date('d/m/Y', strtotime($comment['created_at'])); ?>
                        </div>
                        <div class="comment-content"><?php echo htmlspecialchars($comment['content']); ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php metricsRecordResponse($redis, $startTime); ?>
</body>

</html>

<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/redis.php';
require_once __DIR__ . '/../config/metrics.php';

$db = getDBConnection();
$redis = getRedisConnection();
$startTime = metricsRequestStart();
metricsRecordRequest($redis, 'api');

function sendJson($data, $status = 200) {
    global $redis, $startTime;
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    metricsRecordResponse($redis, $startTime);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$originalUri = $_SERVER['HTTP_X_ORIGINAL_URI'] ?? null;
$uriPath = parse_url($originalUri ?? ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$rawPath = rtrim($uriPath, '/');
$rawPath = $rawPath === '' ? '/' : $rawPath;
$path = $rawPath;
if ($path === '/api') {
    $path = '/';
} elseif (strpos($path, '/api/') === 0) {
    $path = substr($path, 4);
}

if ($method === 'GET' && ($path === '/posts' || $rawPath === '/api/posts' || $rawPath === '/posts')) {
    $cacheKey = 'blog:api:posts';
    $cached = $redis->get($cacheKey);
    if ($cached) {
        metricsRecordCache($redis, true);
        sendJson(json_decode($cached, true));
    }

    $stmt = $db->query("SELECT p.*, u.username, COUNT(c.id) as comment_count
                        FROM posts p
                        LEFT JOIN users u ON p.user_id = u.id
                        LEFT JOIN comments c ON p.id = c.post_id
                        GROUP BY p.id
                        ORDER BY p.created_at DESC");
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $redis->setex($cacheKey, 300, json_encode($posts));
    metricsRecordCache($redis, false);
    sendJson($posts);
}

if ($method === 'GET' && (preg_match('#^/posts/(\d+)$#', $path, $matches) || preg_match('#^/api/posts/(\d+)$#', $rawPath, $matches))) {
    $postId = (int)$matches[1];
    $cacheKey = 'blog:api:post:' . $postId;
    $cached = $redis->get($cacheKey);
    if ($cached) {
        metricsRecordCache($redis, true);
        sendJson(json_decode($cached, true));
    }

    $postStmt = $db->prepare('SELECT p.*, u.username FROM posts p LEFT JOIN users u ON p.user_id = u.id WHERE p.id = :id');
    $postStmt->execute([':id' => $postId]);
    $post = $postStmt->fetch(PDO::FETCH_ASSOC);

    if (!$post) {
        sendJson(['error' => 'Post no encontrado'], 404);
    }

    $commentStmt = $db->prepare('SELECT c.*, u.username FROM comments c LEFT JOIN users u ON c.user_id = u.id WHERE c.post_id = :id ORDER BY c.created_at DESC');
    $commentStmt->execute([':id' => $postId]);
    $comments = $commentStmt->fetchAll(PDO::FETCH_ASSOC);

    $payload = ['post' => $post, 'comments' => $comments];
    $redis->setex($cacheKey, 300, json_encode($payload));
    metricsRecordCache($redis, false);
    sendJson($payload);
}

if ($method === 'POST' && ($path === '/posts' || $rawPath === '/api/posts' || $rawPath === '/posts')) {
    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        $payload = $_POST;
    }

    $userId = isset($payload['user_id']) ? (int)$payload['user_id'] : 0;
    $title = isset($payload['title']) ? trim($payload['title']) : '';
    $content = isset($payload['content']) ? trim($payload['content']) : '';

    if ($userId <= 0 || $title === '' || $content === '') {
        sendJson(['error' => 'Datos invalidos. Se requiere user_id, title y content.'], 422);
    }

    $stmt = $db->prepare('INSERT INTO posts (user_id, title, content) VALUES (:user_id, :title, :content)');
    $stmt->execute([
        ':user_id' => $userId,
        ':title' => $title,
        ':content' => $content,
    ]);

    $postId = (int)$db->lastInsertId();
    $redis->del('blog:api:posts', 'blog:posts:all');
    sendJson(['id' => $postId, 'status' => 'created'], 201);
}

if ($method === 'GET' && ($path === '/stats' || $rawPath === '/api/stats' || $rawPath === '/stats')) {
    $totalPosts = (int)$db->query('SELECT COUNT(*) FROM posts')->fetchColumn();
    $totalUsers = (int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $totalComments = (int)$db->query('SELECT COUNT(*) FROM comments')->fetchColumn();
    $totalViews = (int)$db->query('SELECT COALESCE(SUM(views), 0) FROM posts')->fetchColumn();

    $requestsTotal = (int)($redis->get('metrics:requests:total') ?: 0);
    $responseSum = (float)($redis->get('metrics:response:sum_ms') ?: 0);
    $responseCount = (int)($redis->get('metrics:response:count') ?: 0);
    $avgResponse = $responseCount ? $responseSum / $responseCount : 0;
    $cacheHit = (int)($redis->get('metrics:cache:hit') ?: 0);
    $cacheMiss = (int)($redis->get('metrics:cache:miss') ?: 0);

    sendJson([
        'posts' => $totalPosts,
        'users' => $totalUsers,
        'comments' => $totalComments,
        'views' => $totalViews,
        'requests' => $requestsTotal,
        'avg_response_ms' => round($avgResponse, 2),
        'cache_hits' => $cacheHit,
        'cache_misses' => $cacheMiss,
        'services' => [
            'db' => 'ok',
            'redis' => $redis->ping() ? 'ok' : 'error',
        ],
        'timestamp' => date('c'),
    ]);
}

sendJson(['error' => 'Endpoint no encontrado'], 404);

<?php

require_once __DIR__ . '/utils/JWT.php';
require_once __DIR__ . '/models/Notification.php';

$token = $_GET['token'] ?? '';
$last_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;

if (!$token) {
    http_response_code(401);
    echo 'Missing token';
    exit;
}

$decoded = JWT::decode($token);
if (!$decoded || empty($decoded['id'])) {
    http_response_code(401);
    echo 'Invalid token';
    exit;
}

@ini_set('zlib.output_compression', 0);
@ini_set('output_buffering', 'off');
@ini_set('implicit_flush', 1);
while (ob_get_level() > 0) {
    ob_end_flush();
}
ob_implicit_flush(true);

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

$notification = new Notification();
$user_id = (int)$decoded['id'];
$start = time();

echo ": connected\n\n";
flush();

while (!connection_aborted() && (time() - $start) < 55) {
    try {
        $items = $notification->getForUser($user_id, 20, $last_id);
    } catch (Throwable $e) {
        $items = [];
        error_log('Notification stream query failed: ' . $e->getMessage());
    }
    if (!empty($items)) {
        $latest_id = $last_id;
        foreach ($items as $item) {
            $item_id = (int)$item['id'];
            if ($item_id > $latest_id) {
                $latest_id = $item_id;
            }
        }
        $last_id = $latest_id;

        echo "event: notifications\n";
        echo 'data: ' . json_encode(array_values(array_reverse($items)), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
        flush();
    } else {
        echo ": heartbeat\n\n";
        flush();
    }

    sleep(2);
}


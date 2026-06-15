<?php
// 聚餐投票後端：用一個 JSON 檔保存資料，不需要 MySQL。
header('Content-Type: application/json; charset=utf-8');

$file = __DIR__ . '/votes.json';
$method = $_SERVER['REQUEST_METHOD'];

// 讀取全部投票（回傳陣列）
if ($method === 'GET') {
    if (!file_exists($file)) {
        echo json_encode([]);
        exit;
    }
    $raw = file_get_contents($file);
    $data = json_decode($raw, true);
    if (!is_array($data)) $data = [];
    echo json_encode(array_values($data), JSON_UNESCAPED_UNICODE);
    exit;
}

// 新增 / 更新一筆投票（依名字去重）
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $name  = isset($input['name']) ? trim($input['name']) : '';
    $dates = isset($input['dates']) && is_array($input['dates']) ? $input['dates'] : null;

    if ($name === '' || $dates === null) {
        http_response_code(400);
        echo json_encode(['error' => 'invalid input']);
        exit;
    }

    // 用獨佔鎖避免多人同時送出時資料被覆蓋
    $fp = fopen($file, 'c+');
    if (!$fp) {
        http_response_code(500);
        echo json_encode(['error' => 'cannot open data file']);
        exit;
    }
    flock($fp, LOCK_EX);

    $raw = stream_get_contents($fp);
    $votes = json_decode($raw, true);
    if (!is_array($votes)) $votes = [];

    $key = mb_strtolower($name, 'UTF-8');
    $votes[$key] = [
        'name'      => $name,
        'dates'     => array_values($dates),
        'updatedAt' => round(microtime(true) * 1000),
    ];

    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($votes, JSON_UNESCAPED_UNICODE));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'method not allowed']);

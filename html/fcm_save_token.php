<?php
// /fcm_save_token.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'includes/db.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'msg' => '로그인 필요']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'msg' => '잘못된 요청']);
    exit;
}

$data    = json_decode(file_get_contents('php://input'), true);
$token   = trim($data['token'] ?? '');
$user_id = (int)$_SESSION['user_id'];

if ($token === '') {
    echo json_encode(['success' => false, 'msg' => '토큰 없음']);
    exit;
}

try {
    // 있으면 UPDATE, 없으면 INSERT
    $pdo->prepare("
        INSERT INTO fcm_tokens (user_id, token)
        VALUES (:uid, :token)
        ON DUPLICATE KEY UPDATE token = VALUES(token), updated_at = NOW()
    ")->execute([':uid' => $user_id, ':token' => $token]);

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'msg' => '저장 오류']);
}

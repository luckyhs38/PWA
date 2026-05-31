<?php
// /board/board_subscribe.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'msg' => '로그인 필요']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'msg' => '잘못된 요청']);
    exit;
}

$data       = json_decode(file_get_contents('php://input'), true);
$board_type = trim($data['board_type'] ?? '');
$user_id    = (int)$_SESSION['user_id'];

$allowed_types = ['anonymity', 'writing'];
if (!in_array($board_type, $allowed_types)) {
    echo json_encode(['success' => false, 'msg' => '잘못된 게시판']);
    exit;
}

try {
    $exists = $pdo->prepare("
        SELECT id FROM board_subscriptions
        WHERE user_id = :uid AND board_type = :type
    ");
    $exists->execute([':uid' => $user_id, ':type' => $board_type]);
    $subscribed = $exists->fetch();

    if ($subscribed) {
        $pdo->prepare("
            DELETE FROM board_subscriptions
            WHERE user_id = :uid AND board_type = :type
        ")->execute([':uid' => $user_id, ':type' => $board_type]);

        echo json_encode(['success' => true, 'subscribed' => false]);
    } else {
        $pdo->prepare("
            INSERT INTO board_subscriptions (user_id, board_type)
            VALUES (:uid, :type)
        ")->execute([':uid' => $user_id, ':type' => $board_type]);

        echo json_encode(['success' => true, 'subscribed' => true]);
    }

} catch (PDOException $e) {
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'msg' => '오류 발생']);
}

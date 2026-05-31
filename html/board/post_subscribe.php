<?php
// /board/post_subscribe.php
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

$data     = json_decode(file_get_contents('php://input'), true);
$board_id = isset($data['board_id']) ? (int)$data['board_id'] : 0;
$user_id  = (int)$_SESSION['user_id'];

if ($board_id === 0) {
    echo json_encode(['success' => false, 'msg' => '잘못된 요청']);
    exit;
}

try {
    // 게시글 존재 확인
    $check = $pdo->prepare("SELECT id FROM boards WHERE id = :id AND hidden_yn = 'N'");
    $check->execute([':id' => $board_id]);
    if (!$check->fetch()) {
        echo json_encode(['success' => false, 'msg' => '존재하지 않는 게시글']);
        exit;
    }

    // 현재 구독 여부 확인
    $exists = $pdo->prepare("
        SELECT id FROM post_subscriptions
        WHERE user_id = :uid AND board_id = :bid
    ");
    $exists->execute([':uid' => $user_id, ':bid' => $board_id]);
    $subscribed = $exists->fetch();

    if ($subscribed) {
        // 구독 취소
        $pdo->prepare("
            DELETE FROM post_subscriptions
            WHERE user_id = :uid AND board_id = :bid
        ")->execute([':uid' => $user_id, ':bid' => $board_id]);

        echo json_encode(['success' => true, 'subscribed' => false]);
    } else {
        // 구독
        $pdo->prepare("
            INSERT INTO post_subscriptions (user_id, board_id)
            VALUES (:uid, :bid)
        ")->execute([':uid' => $user_id, ':bid' => $board_id]);

        echo json_encode(['success' => true, 'subscribed' => true]);
    }

} catch (PDOException $e) {
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'msg' => '오류 발생']);
}

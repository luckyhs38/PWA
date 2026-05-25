<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header('Location: /');
    exit;
}

// 본인 알림만 조회
$stmt = $pdo->prepare("
    SELECT *
    FROM notifications
    WHERE id = :id
      AND user_id = :user_id
    LIMIT 1
");

$stmt->execute([
    ':id' => $id,
    ':user_id' => $_SESSION['user_id']
]);

$notification = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$notification) {
    header('Location: /');
    exit;
}

// 읽음 처리
$stmt = $pdo->prepare("
    UPDATE notifications
    SET is_read = 1
    WHERE id = :id
");

$stmt->execute([
    ':id' => $id
]);

// 이동 URL
$url = $notification['url'] ?: '/';

header("Location: {$url}");
exit;
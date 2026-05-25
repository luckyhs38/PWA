<?php
require_once '../includes/auth_check.php';
require_once '../includes/db.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode([]);
    exit;
}

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT id, message, is_read, target_id, created_at
    FROM notifications
    WHERE user_id = :uid
    ORDER BY created_at DESC
    LIMIT 15
");

$stmt->execute([
    ':uid' => $user_id
]);

$list = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($list);
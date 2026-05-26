<?php
// /ajax/notification_delete.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /');
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$action = $_POST['action'] ?? 'single';

try {
    $pdo->beginTransaction();

    if ($action === 'all') {
        // 전체 삭제: 먼저 전체 읽음 처리
        $stmt = $pdo->prepare("
            UPDATE notifications
            SET is_read = 1
            WHERE user_id = :user_id
        ");
        $stmt->execute([
            ':user_id' => $user_id
        ]);

        // 그 다음 전체 삭제
        $stmt = $pdo->prepare("
            DELETE FROM notifications
            WHERE user_id = :user_id
        ");
        $stmt->execute([
            ':user_id' => $user_id
        ]);

    } else {
        // 개별 삭제
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

        if ($id <= 0) {
            $pdo->rollBack();
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/'));
            exit;
        }

        // 먼저 읽음 처리
        $stmt = $pdo->prepare("
            UPDATE notifications
            SET is_read = 1
            WHERE id = :id
              AND user_id = :user_id
        ");
        $stmt->execute([
            ':id' => $id,
            ':user_id' => $user_id
        ]);

        // 그 다음 삭제
        $stmt = $pdo->prepare("
            DELETE FROM notifications
            WHERE id = :id
              AND user_id = :user_id
        ");
        $stmt->execute([
            ':id' => $id,
            ':user_id' => $user_id
        ]);
    }

    $pdo->commit();

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

$back_url = $_SERVER['HTTP_REFERER'] ?? '/';
header('Location: ' . $back_url);
exit;
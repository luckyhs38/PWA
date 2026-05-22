<?php
// /admin/post_action.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_admin($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: posts.php');
    exit;
}

$post_id  = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
$action   = $_POST['action']   ?? '';
$redirect = $_POST['redirect'] ?? 'posts.php';

if ($post_id === 0 || !in_array($action, ['hide', 'restore'])) {
    header('Location: posts.php?msg=error');
    exit;
}

try {
    $hidden_yn = $action === 'hide' ? 'Y' : 'N';
    $pdo->prepare("
        UPDATE boards SET hidden_yn = :hidden WHERE id = :id
    ")->execute([':hidden' => $hidden_yn, ':id' => $post_id]);

    header('Location: ' . $redirect);
    exit;

} catch (PDOException $e) {
    error_log($e->getMessage());
    header('Location: posts.php?msg=error');
    exit;
}

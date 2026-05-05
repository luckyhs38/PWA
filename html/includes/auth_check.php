<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 로그인 체크
if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php?error=auth");
    exit;
}

require_once __DIR__ . '/db.php';

try {
    $stmt = $pdo->prepare("SELECT status, deleted_at FROM users WHERE id = :id");
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || $user['status'] != 1 || $user['deleted_at'] !== null) {

        // 세션 완전 제거
        $_SESSION = [];

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }

        session_destroy();

        header("Location: /login.php?error=invalid");
        exit;
    }

    // 추가 보안
    session_regenerate_id(true);

} catch (PDOException $e) {
    error_log($e->getMessage());
    header("Location: /error.php");
    exit;
}
?>
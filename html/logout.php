<?php
session_start();

// 세션 데이터 제거
$_SESSION = [];

// 세션 쿠키 삭제
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// 세션 파괴
session_destroy();

// 리다이렉트
header("Location: index.php");
exit;
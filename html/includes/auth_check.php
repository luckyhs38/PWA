<?php
// ================================================================
// /includes/auth_check.php
// 로그인 + 권한 관리 통합
// ================================================================


// 세션 시작
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// DB 연결
require_once __DIR__ . '/db.php';



// ================================================================
// 로그인 여부
// ================================================================
function is_logged_in(): bool {

    return isset($_SESSION['user_id']);
}



// ================================================================
// 현재 권한 반환
// guest / user / writer / admin
// ================================================================
function current_role(): string {

    return $_SESSION['role'] ?? 'guest';
}



// ================================================================
// 관리자 여부
// ================================================================
function is_admin(): bool {

    return current_role() === 'admin';
}



// ================================================================
// 작가 여부
// ================================================================
function is_writer(): bool {

    return in_array(
        current_role(),
        ['writer', 'admin']
    );
}



// ================================================================
// 로그인 필수
// ================================================================
function require_login(): void {

    if (!is_logged_in()) {

        $current = urlencode($_SERVER['REQUEST_URI']);

        header("Location: /login.php?redirect={$current}");
        exit;
    }
}



// ================================================================
// 관리자 권한 필수
// ================================================================
function require_admin(): void {

    require_login();

    if (!is_admin()) {

        http_response_code(403);

        exit('관리자 권한이 필요합니다.');
    }
}



// ================================================================
// 작가 권한 필수
// ================================================================
function require_writer(): void {

    require_login();

    if (!is_writer()) {

        http_response_code(403);

        exit('작가 권한이 필요합니다.');
    }
}



// ================================================================
// 로그인 상태 유효성 검사
// ================================================================
if (is_logged_in()) {

    try {

        $stmt = $pdo->prepare("
            SELECT
                role,
                status,
                deleted_at
            FROM users
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([
            ':id' => (int)$_SESSION['user_id']
        ]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // 유효하지 않은 계정
        if (
            !$user ||
            $user['status'] != 1 ||
            $user['deleted_at'] !== null
        ) {

            $_SESSION = [];

            // 세션 쿠키 제거
            if (ini_get('session.use_cookies')) {

                $params = session_get_cookie_params();

                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params['path'],
                    $params['domain'],
                    $params['secure'],
                    $params['httponly']
                );
            }

            session_destroy();

            header('Location: /login.php?error=invalid');
            exit;
        }

        // role 세션 갱신
        $_SESSION['role'] = $user['role'];

    } catch (PDOException $e) {

        error_log($e->getMessage());

        header('Location: /error.php');
        exit;
    }
}

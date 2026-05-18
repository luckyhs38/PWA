
<?php
// ================================================================
// /includes/auth.php
// 반드시 session_start() 이후 include
// db.php 이후 include 권장
// ================================================================



// ================================================================
// 로그인 여부 확인
// ================================================================
function is_logged_in(): bool {
    return isset($_SESSION['user_id']);
}



// ================================================================
// 현재 로그인 유저 role 반환
// guest / user / writer / admin
// 세션 캐싱 사용
// ================================================================
function get_current_role(PDO $pdo): string {

    // 비회원
    if (!is_logged_in()) {
        return 'guest';
    }

    // 세션 캐싱
    if (!isset($_SESSION['role'])) {

        $stmt = $pdo->prepare("
            SELECT role
            FROM users
            WHERE id = :id
              AND deleted_at IS NULL
              AND status = 1
            LIMIT 1
        ");

        $stmt->execute([
            ':id' => (int)$_SESSION['user_id']
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        // 유저 없거나 비활성화 상태
        if (!$row) {

            // 세션 완전 제거
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

            header('Location: /login.php');
            exit;
        }

        $_SESSION['role'] = $row['role'];
    }

    return $_SESSION['role'];
}



// ================================================================
// 로그인 필수
// 비회원이면 로그인 페이지 이동
// ================================================================
function require_login(PDO $pdo): void {

    if (get_current_role($pdo) === 'guest') {

        $current = urlencode($_SERVER['REQUEST_URI']);

        header("Location: /login.php?redirect={$current}");
        exit;
    }
}



// ================================================================
// 작가 이상 권한 필요
// writer / admin 허용
// ================================================================
function require_writer(PDO $pdo): void {

    require_login($pdo);

    $role = get_current_role($pdo);

    if (!in_array($role, ['writer', 'admin'])) {

        http_response_code(403);

        include $_SERVER['DOCUMENT_ROOT'] . '/includes/header.php';

        echo '
        <div style="
            max-width:500px;
            margin:160px auto;
            text-align:center;
            font-family:sans-serif;
        ">

            <p style="
                font-size:15px;
                color:#555;
                margin-bottom:20px;
            ">
                작가 권한이 필요한 페이지입니다.
            </p>

            <a
                href=\"javascript:history.back()\"
                style=\"
                    font-size:13px;
                    color:#aaa;
                    text-decoration:underline;
                \"
            >
                돌아가기
            </a>

        </div>';

        include $_SERVER['DOCUMENT_ROOT'] . '/includes/footer.php';

        exit;
    }
}



// ================================================================
// 관리자 권한 필요
// admin만 허용
// ================================================================
function require_admin(PDO $pdo): void {

    require_login($pdo);

    if (get_current_role($pdo) !== 'admin') {

        http_response_code(403);

        include $_SERVER['DOCUMENT_ROOT'] . '/includes/header.php';

        echo '
        <div style="
            max-width:500px;
            margin:160px auto;
            text-align:center;
            font-family:sans-serif;
        ">

            <p style="
                font-size:15px;
                color:#555;
                margin-bottom:20px;
            ">
                관리자 권한이 필요한 페이지입니다.
            </p>

            <a
                href=\"javascript:history.back()\"
                style=\"
                    font-size:13px;
                    color:#aaa;
                    text-decoration:underline;
                \"
            >
                돌아가기
            </a>

        </div>';

        include $_SERVER['DOCUMENT_ROOT'] . '/includes/footer.php';

        exit;
    }
}



// ================================================================
// 편의 함수
// ================================================================
function is_guest(PDO $pdo): bool {
    return get_current_role($pdo) === 'guest';
}

function is_user(PDO $pdo): bool {
    return get_current_role($pdo) === 'user';
}

function is_writer(PDO $pdo): bool {
    return in_array(
        get_current_role($pdo),
        ['writer', 'admin']
    );
}

function is_admin(PDO $pdo): bool {
    return get_current_role($pdo) === 'admin';
}

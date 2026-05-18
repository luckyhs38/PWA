<?php
// /admin/user_action.php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../includes/db.php';
require_once '../includes/auth_check.php';

// 1. 관리자 권한 필수 체크 (auth_check.php 통합 버전을 사용하므로 인자 불필요)
require_admin();

// POST 요청 검증
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "<script>alert('잘못된 접근입니다.'); location.href='users.php';</script>";
    exit;
}

$action   = $_POST['action'] ?? '';
$user_id  = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$redirect = $_POST['redirect'] ?? 'users.php';

// 보안: 외부 URL 리다이렉트 방지
if (str_starts_with($redirect, 'http://') || str_starts_with($redirect, 'https://')) {
    $redirect = 'users.php';
}

if ($user_id <= 0) {
    echo "<script>alert('잘못된 사용자 ID입니다.'); history.back();</script>";
    exit;
}

// 방어 코드: 최고 관리자가 실수로 본인의 권한을 일반 회원으로 바꾸거나 정지하는 것 방지
if ($user_id === (int)$_SESSION['user_id']) {
    echo "<script>alert('본인의 권한이나 상태는 변경할 수 없습니다.'); history.back();</script>";
    exit;
}

try {
    if ($action === 'change_role') {
        // ==========================================
        // [1] 권한 변경 처리 (일반 <-> 작가 <-> 관리자)
        // ==========================================
        $role = $_POST['role'] ?? '';
        if (!in_array($role, ['user', 'writer', 'admin'])) {
            echo "<script>alert('올바르지 않은 권한 등급입니다.'); history.back();</script>";
            exit;
        }

        $stmt = $pdo->prepare("UPDATE users SET role = :role WHERE id = :id AND deleted_at IS NULL");
        $stmt->execute([
            ':role' => $role,
            ':id'   => $user_id
        ]);

        // 기존 리다이렉트 주소에 성공 메시지 파라미터 결합
        if (strpos($redirect, 'msg=') === false) {
            $connector = (strpos($redirect, '?') === false) ? '?' : '&';
            $redirect .= $connector . 'msg=role_updated';
        }

        header("Location: {$redirect}");
        exit;

    } elseif ($action === 'toggle_status') {
        // ==========================================
        // [2] 계정 상태 토글 처리 (활성(1) <-> 정지(0))
        // ==========================================
        
        // 현재 회원의 상태 값을 먼저 조회합니다.
        $stmt = $pdo->prepare("SELECT status FROM users WHERE id = :id AND deleted_at IS NULL");
        $stmt->execute([':id' => $user_id]);
        $current_status = $stmt->fetchColumn();

        if ($current_status === false) {
            echo "<script>alert('존재하지 않거나 이미 탈퇴한 회원입니다.'); history.back();</script>";
            exit;
        }

        // 상태값 반전 (1이면 0으로, 0이면 1로)
        $new_status = ($current_status == 1) ? 0 : 1;

        $stmt = $pdo->prepare("UPDATE users SET status = :status WHERE id = :id");
        $stmt->execute([
            ':status' => $new_status,
            ':id'     => $user_id
        ]);

        if (strpos($redirect, 'msg=') === false) {
            $connector = (strpos($redirect, '?') === false) ? '?' : '&';
            $redirect .= $connector . 'msg=status_updated';
        }

        header("Location: {$redirect}");
        exit;

    } else {
        echo "<script>alert('잘못된 요청 명령입니다.'); history.back();</script>";
        exit;
    }

} catch (PDOException $e) {
    error_log("User Action Error: " . $e->getMessage());
    
    // 에러 발생 시 원래 페이지로 에러 메시지와 함께 리다이렉트
    if (strpos($redirect, 'msg=') === false) {
        $connector = (strpos($redirect, '?') === false) ? '?' : '&';
        $redirect .= $connector . 'msg=error';
    }
    header("Location: {$redirect}");
    exit;
}
<?php
// /admin/writer_action.php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../includes/db.php';
require_once '../includes/auth_check.php';

// 1. 관리자 권한 필수 체크
require_admin();

// 2. POST 데이터 수신
$application_id  = isset($_POST['application_id']) ? (int)$_POST['application_id'] : 0;
$action          = $_POST['action'] ?? '';
$reject_reason   = trim($_POST['reject_reason'] ?? '');
$redirect_status = $_POST['redirect_status'] ?? 'pending'; // 처리 후 돌아갈 탭 위치

// 3. 유효성 검사
if ($application_id <= 0 || !in_array($action, ['approve', 'reject'])) {
    echo "<script>alert('잘못된 접근입니다.'); history.back();</script>";
    exit;
}

try {
    // 트랜잭션 시작! (두 개 이상의 쿼리를 하나로 묶어서 실행)
    $pdo->beginTransaction();

    // 4. 신청서 정보 조회 (대기 중인 신청인지, 조작된 데이터는 아닌지 확인)
    // FOR UPDATE: 트랜잭션이 끝날 때까지 다른 곳에서 이 데이터를 건드리지 못하게 잠금(Lock)
    $stmt = $pdo->prepare("
        SELECT user_id, status 
        FROM writer_applications 
        WHERE id = :id FOR UPDATE
    ");
    $stmt->execute([':id' => $application_id]);
    $application = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$application) {
        throw new Exception("존재하지 않는 신청입니다.");
    }
    if ($application['status'] !== 'pending') {
        throw new Exception("이미 처리된 신청입니다.");
    }

    $admin_id = $_SESSION['user_id']; // 현재 처리하는 관리자의 ID

    if ($action === 'approve') {
        // ==========================================
        // [승인 처리 로직]
        // ==========================================
        
        // 1) 신청서 상태 변경 (approved)
        $update_app = $pdo->prepare("
            UPDATE writer_applications 
            SET status = 'approved',
                processed_at = NOW(),
                processed_by = :admin_id
            WHERE id = :id
        ");
        $update_app->execute([
            ':admin_id' => $admin_id,
            ':id'       => $application_id
        ]);

        // 2) 실제 유저 권한을 'writer'로 승급
        // (방어 코드: 혹시 이미 admin인 사람의 권한을 강등시키지 않도록 role != 'admin' 조건 추가)
        $update_user = $pdo->prepare("
            UPDATE users 
            SET role = 'writer' 
            WHERE id = :user_id 
              AND role != 'admin'
        ");
        $update_user->execute([':user_id' => $application['user_id']]);

        $msg = 'approved';

    } elseif ($action === 'reject') {
        // ==========================================
        // [거절 처리 로직]
        // ==========================================
        
        // 신청서 상태 변경 (rejected) 및 거절 사유 기록
        $update_app = $pdo->prepare("
            UPDATE writer_applications 
            SET status = 'rejected',
                reject_reason = :reject_reason,
                processed_at = NOW(),
                processed_by = :admin_id
            WHERE id = :id
        ");
        $update_app->execute([
            ':reject_reason' => $reject_reason,
            ':admin_id'      => $admin_id,
            ':id'            => $application_id
        ]);

        $msg = 'rejected';
    }

    // 모든 업데이트가 성공하면 트랜잭션 커밋(저장)!
    $pdo->commit();

    // 5. 원래 있던 탭으로 리다이렉트하여 결과 알림 띄우기
    header("Location: writer_list.php?status={$redirect_status}&msg={$msg}");
    exit;

} catch (Exception $e) {
    // 중간에 하나라도 에러가 나면 트랜잭션 롤백(전체 취소)!
    $pdo->rollBack();
    error_log("Writer Action Error: " . $e->getMessage());
    
    $error_msg = htmlspecialchars($e->getMessage());
    echo "<script>alert('처리 중 오류가 발생했습니다: {$error_msg}'); history.back();</script>";
    exit;
}
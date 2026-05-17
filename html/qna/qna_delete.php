<?php
// /qna/qna_delete.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    echo "<script>alert('로그인 후 이용 가능합니다.'); location.href='login.php';</script>";
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    echo "<script>alert('잘못된 접근입니다.'); history.back();</script>";
    exit;
}

// 임시 관리자 기준: users.id = 1
$is_admin = (int)$_SESSION['user_id'] === 1;

try {
    // 1. 문의글 조회
    $stmt = $pdo->prepare("
        SELECT id, user_id, deleted_at
        FROM qna
        WHERE id = :id
          AND deleted_at IS NULL
    ");

    $stmt->execute([
        ':id' => $id
    ]);

    $qna = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$qna) {
        echo "<script>alert('존재하지 않거나 이미 삭제된 문의입니다.'); location.href='qna.php';</script>";
        exit;
    }

    // 2. 삭제 권한 확인
    // 작성자 본인 또는 관리자만 삭제 가능
    $is_writer = (int)$qna['user_id'] === (int)$_SESSION['user_id'];

    if (!$is_writer && !$is_admin) {
        echo "<script>alert('삭제 권한이 없습니다.'); history.back();</script>";
        exit;
    }

    // 3. 소프트 삭제 처리
    $stmt = $pdo->prepare("
        UPDATE qna
        SET deleted_at = NOW(),
            updated_at = NOW()
        WHERE id = :id
          AND deleted_at IS NULL
    ");

    $stmt->execute([
        ':id' => $id
    ]);

    echo "<script>alert('문의가 삭제되었습니다.'); location.href='qna.php';</script>";
    exit;

} catch (PDOException $e) {
    echo "<script>alert('삭제 처리 중 오류가 발생했습니다.'); history.back();</script>";
    exit;
}
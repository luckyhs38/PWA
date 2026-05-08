<?php
// /board/delete.php

require_once '../includes/db.php';
require_once '../includes/auth_check.php'; // 로그인 체크

// 1. 파라미터 받기
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$type = $_GET['type'] ?? 'anonymity';

// 2. 게시판 타입 검증
$allowed_types = [
    'anonymity' => '익명글',
    'writing'   => '작가만의 방'
];

if ($id <= 0 || !array_key_exists($type, $allowed_types)) {
    echo "<script>alert('잘못된 접근입니다.'); history.back();</script>";
    exit;
}

try {
    // 3. 게시글 조회
    $sql = "
        SELECT id, user_id, hidden_yn
        FROM boards
        WHERE id = :id
          AND board_type = :type
          AND hidden_yn = 'N'
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':id' => $id,
        ':type' => $type
    ]);

    $post = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$post) {
        echo "<script>alert('존재하지 않거나 이미 삭제된 게시글입니다.'); location.href='list.php?type={$type}';</script>";
        exit;
    }

    // 4. 작성자 본인 확인
    if ((int)$post['user_id'] !== (int)$_SESSION['user_id']) {
        echo "<script>alert('삭제 권한이 없습니다.'); history.back();</script>";
        exit;
    }

    // 5. 소프트 삭제 처리
    $delete_sql = "
        UPDATE boards
        SET hidden_yn = 'Y',
            deleted_at = NOW(),
            updated_at = NOW()
        WHERE id = :id
          AND user_id = :user_id
          AND board_type = :type
          AND hidden_yn = 'N'
    ";

    $stmt = $pdo->prepare($delete_sql);
    $stmt->execute([
        ':id' => $id,
        ':user_id' => $_SESSION['user_id'],
        ':type' => $type
    ]);

    echo "<script>alert('게시글이 삭제되었습니다.'); location.href='list.php?type={$type}';</script>";
    exit;

} catch (PDOException $e) {
    echo "<script>alert('삭제 처리 중 오류가 발생했습니다.'); history.back();</script>";
    exit;
}
?>
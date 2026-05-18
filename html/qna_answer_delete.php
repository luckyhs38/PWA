<?php
// /qna_answer_delete.php

require_once './includes/db.php';
require_once './includes/auth_check.php';

// 1. 관리자 권한 필수 체크
require_admin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    echo "<script>alert('잘못된 접근입니다.'); history.back();</script>";
    exit;
}

try {
    // 2. 답변 내용 초기화 및 상태 변경
    // [사수 코멘트] 완전히 글을 지우는게 아니라, 답변 관련 컬럼만 NULL로 비워줍니다.
    $stmt = $pdo->prepare("
        UPDATE qna 
        SET answer_content = NULL,
            status = 'pending',
            answered_at = NULL
        WHERE id = :id
    ");
    
    $stmt->execute([':id' => $id]);

    // 3. 뷰 페이지로 리다이렉트
    echo "<script>alert('관리자 답변이 삭제되었습니다.'); location.href='qna_view.php?id={$id}';</script>";
    exit;

} catch (PDOException $e) {
    error_log("QnA Answer Delete Error: " . $e->getMessage());
    echo "<script>alert('답변 삭제 중 오류가 발생했습니다.'); history.back();</script>";
    exit;
}
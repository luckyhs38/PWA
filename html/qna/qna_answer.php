<?php
// /qna/qna_answer.php

// 1. DB 및 권한 체크 파일 포함
require_once '../includes/db.php';
require_once '../includes/auth_check.php';

// 2. 관리자 권한 필수 체크 (auth_check.php의 함수 활용)
// 관리자가 아니면 이 함수 내부에서 자동으로 차단 및 종료됩니다.
require_admin();

// 3. POST 데이터 수신 및 정제
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$answer_content = trim($_POST['answer_content'] ?? '');

// 4. 유효성 검사
if ($id <= 0) {
    echo "<script>alert('잘못된 접근입니다.'); history.back();</script>";
    exit;
}

if ($answer_content === '') {
    echo "<script>alert('답변 내용을 입력해주세요.'); history.back();</script>";
    exit;
}

// 답변 글자 수 제한이 필요하다면 추가 (예: 2000자)
if (mb_strlen($answer_content) > 2000) {
    echo "<script>alert('답변은 2000자 이내로 입력해주세요.'); history.back();</script>";
    exit;
}

try {
    // 5. 대상 문의글 존재 여부 확인
    $stmt = $pdo->prepare("
        SELECT id, status 
        FROM qna 
        WHERE id = :id 
          AND deleted_at IS NULL
    ");
    
    $stmt->execute([':id' => $id]);
    $qna = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$qna) {
        echo "<script>alert('존재하지 않거나 삭제된 문의입니다.'); location.href='qna.php';</script>";
        exit;
    }

    // 6. 답변 업데이트 
    // IFNULL(answered_at, NOW())를 사용하여 최초 답변 시에만 시간이 기록되도록 처리 (수정 시 기존 시간 유지)
    $update_stmt = $pdo->prepare("
        UPDATE qna 
        SET answer_content = :answer_content,
            status = 'answered',
            answered_at = IFNULL(answered_at, NOW())
        WHERE id = :id
    ");

    $update_stmt->execute([
        ':answer_content' => $answer_content,
        ':id' => $id
    ]);

    // 7. 성공 처리 후 뷰 페이지로 리다이렉트
    echo "<script>alert('답변이 성공적으로 저장되었습니다.'); location.href='qna_view.php?id={$id}';</script>";
    exit;

} catch (PDOException $e) {
    // 에러 로그 기록 (실무 권장)
    error_log("QnA Answer Error: " . $e->getMessage());
    echo "<script>alert('답변 저장 중 오류가 발생했습니다.'); history.back();</script>";
    exit;
}
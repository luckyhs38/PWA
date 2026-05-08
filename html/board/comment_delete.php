<?php
require_once '../includes/db.php';
require_once '../includes/auth_check.php';

$comment_id = isset($_POST['comment_id']) ? (int)$_POST['comment_id'] : 0;
$board_id = isset($_POST['board_id']) ? (int)$_POST['board_id'] : 0;
$type = $_POST['type'] ?? 'anonymity';

$allowed_types = [
    'anonymity' => '익명글',
    'writing' => '작가만의 방'
];

if ($comment_id <= 0 || $board_id <= 0 || !array_key_exists($type, $allowed_types)) {
    echo "<script>alert('잘못된 접근입니다.'); history.back();</script>";
    exit;
}

try {
    // 1. 댓글 조회
    $stmt = $pdo->prepare("
        SELECT id, user_id, parent_id
        FROM comments
        WHERE id = :id
          AND board_id = :board_id
          AND hidden_yn = 'N'
          AND deleted_at IS NULL
    ");

    $stmt->execute([
        ':id' => $comment_id,
        ':board_id' => $board_id
    ]);

    $comment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$comment) {
        echo "<script>alert('존재하지 않거나 이미 삭제된 댓글입니다.'); location.href='view.php?id={$board_id}&type={$type}';</script>";
        exit;
    }

    // 2. 본인 댓글인지 확인
    if ((int)$comment['user_id'] !== (int)$_SESSION['user_id']) {
        echo "<script>alert('삭제 권한이 없습니다.'); history.back();</script>";
        exit;
    }

    // 3. 소프트 삭제
    // 일반 댓글을 삭제하면 그 댓글의 대댓글도 같이 숨김 처리
    if ($comment['parent_id'] === null) {
        $stmt = $pdo->prepare("
            UPDATE comments
            SET hidden_yn = 'Y',
                deleted_at = NOW(),
                updated_at = NOW()
            WHERE (id = :id OR parent_id = :id)
              AND board_id = :board_id
        ");

        $stmt->execute([
            ':id' => $comment_id,
            ':board_id' => $board_id
        ]);
    } else {
        $stmt = $pdo->prepare("
            UPDATE comments
            SET hidden_yn = 'Y',
                deleted_at = NOW(),
                updated_at = NOW()
            WHERE id = :id
              AND user_id = :user_id
        ");

        $stmt->execute([
            ':id' => $comment_id,
            ':user_id' => $_SESSION['user_id']
        ]);
    }

    echo "<script>alert('댓글이 삭제되었습니다.'); location.href='view.php?id={$board_id}&type={$type}';</script>";
    exit;

} catch (PDOException $e) {
    echo "<script>alert('댓글 삭제 중 오류가 발생했습니다.'); history.back();</script>";
    exit;
}
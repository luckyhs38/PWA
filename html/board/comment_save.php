<?php
require_once '../includes/db.php';
require_once '../includes/auth_check.php';

$board_id = isset($_POST['board_id']) ? (int)$_POST['board_id'] : 0;
$type = $_POST['type'] ?? 'anonymity';
$parent_id = isset($_POST['parent_id']) && $_POST['parent_id'] !== ''
    ? (int)$_POST['parent_id']
    : null;

$content = trim($_POST['content'] ?? '');

$allowed_types = [
    'anonymity' => '익명글',
    'writing' => '작가만의 방'
];

if ($board_id <= 0 || !array_key_exists($type, $allowed_types)) {
    echo "<script>alert('잘못된 접근입니다.'); history.back();</script>";
    exit;
}

if ($content === '') {
    echo "<script>alert('댓글 내용을 입력해주세요.'); history.back();</script>";
    exit;
}

if (mb_strlen($content) > 1000) {
    echo "<script>alert('댓글은 1000자 이내로 입력해주세요.'); history.back();</script>";
    exit;
}

try {
    // 1. 게시글 존재 확인
    $stmt = $pdo->prepare("
        SELECT id
        FROM boards
        WHERE id = :id
          AND board_type = :type
          AND hidden_yn = 'N'
    ");

    $stmt->execute([
        ':id' => $board_id,
        ':type' => $type
    ]);

    $post = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$post) {
        echo "<script>alert('존재하지 않거나 삭제된 게시글입니다.'); location.href='list.php?type={$type}';</script>";
        exit;
    }

    // 2. 대댓글이면 부모 댓글 검증
    if ($parent_id !== null) {
        $stmt = $pdo->prepare("
            SELECT id
            FROM comments
            WHERE id = :parent_id
              AND board_id = :board_id
              AND parent_id IS NULL
              AND hidden_yn = 'N'
              AND deleted_at IS NULL
        ");

        $stmt->execute([
            ':parent_id' => $parent_id,
            ':board_id' => $board_id
        ]);

        $parent = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$parent) {
            echo "<script>alert('답글을 달 수 없는 댓글입니다.'); history.back();</script>";
            exit;
        }
    }

    // 3. 댓글 저장
    $stmt = $pdo->prepare("
        INSERT INTO comments (
            board_id,
            user_id,
            parent_id,
            content,
            hidden_yn,
            created_at
        ) VALUES (
            :board_id,
            :user_id,
            :parent_id,
            :content,
            'N',
            NOW()
        )
    ");

    $stmt->execute([
        ':board_id' => $board_id,
        ':user_id' => $_SESSION['user_id'],
        ':parent_id' => $parent_id,
        ':content' => $content
    ]);

    echo "<script>alert('댓글이 등록되었습니다.'); location.href='view.php?id={$board_id}&type={$type}';</script>";
    exit;

} catch (PDOException $e) {
    echo "<script>alert('댓글 저장 중 오류가 발생했습니다.'); history.back();</script>";
    exit;
}
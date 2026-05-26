<?php

function create_notification(
    PDO $pdo,
    int $user_id,
    string $type,
    ?int $target_id,
    string $message,
    ?string $url = null
) {
    if ($user_id <= 0) {
        return false;
    }

    if (trim($message) === '') {
        return false;
    }

    $stmt = $pdo->prepare("
        INSERT INTO notifications
        (
            user_id,
            type,
            target_id,
            message,
            url
        )
        VALUES
        (
            :user_id,
            :type,
            :target_id,
            :message,
            :url
        )
    ");

    $stmt->execute([
        ':user_id'   => $user_id,
        ':type'      => $type,
        ':target_id' => $target_id,
        ':message'   => $message,
        ':url'       => $url
    ]);

    return true;
}


/**
 * 댓글/대댓글 등록 알림
 *
 * 일반 댓글:
 * - 게시글 작성자에게 알림
 *
 * 대댓글:
 * - 부모 댓글 작성자에게 알림
 */
function notify_comment_created(
    PDO $pdo,
    int $board_id,
    string $board_type,
    int $comment_id,
    int $comment_writer_id,
    ?int $parent_id = null
) {
    if ($board_id <= 0 || $comment_id <= 0 || $comment_writer_id <= 0) {
        return false;
    }

    // 일반 댓글인 경우: 게시글 작성자에게 알림
    if ($parent_id === null) {
        $stmt = $pdo->prepare("
            SELECT user_id
            FROM boards
            WHERE id = :board_id
              AND board_type = :board_type
              AND hidden_yn = 'N'
        ");

        $stmt->execute([
            ':board_id' => $board_id,
            ':board_type' => $board_type
        ]);

        $receiver_id = (int)$stmt->fetchColumn();

        if ($receiver_id <= 0 || $receiver_id === $comment_writer_id) {
            return false;
        }

        return create_notification(
            $pdo,
            $receiver_id,
            'comment',
            $comment_id,
            '회원님의 글에 새 댓글이 달렸습니다.',
            '/board/view.php?id=' . $board_id . '&type=' . urlencode($board_type)
        );
    }

    // 대댓글인 경우: 부모 댓글 작성자에게 알림
    $stmt = $pdo->prepare("
        SELECT user_id
        FROM comments
        WHERE id = :parent_id
          AND board_id = :board_id
          AND hidden_yn = 'N'
          AND deleted_at IS NULL
    ");

    $stmt->execute([
        ':parent_id' => $parent_id,
        ':board_id' => $board_id
    ]);

    $receiver_id = (int)$stmt->fetchColumn();

    if ($receiver_id <= 0 || $receiver_id === $comment_writer_id) {
        return false;
    }

    return create_notification(
        $pdo,
        $receiver_id,
        'reply',
        $comment_id,
        '회원님의 댓글에 답글이 달렸습니다.',
        '/board/view.php?id=' . $board_id . '&type=' . urlencode($board_type)
    );
}


/**
 * Q&A 답변 등록 알림
 */
function notify_qna_answered(PDO $pdo, int $qna_id) {
    if ($qna_id <= 0) {
        return false;
    }

    $stmt = $pdo->prepare("
        SELECT user_id
        FROM qna
        WHERE id = :id
          AND deleted_at IS NULL
    ");

    $stmt->execute([
        ':id' => $qna_id
    ]);

    $receiver_id = (int)$stmt->fetchColumn();

    if ($receiver_id <= 0) {
        return false;
    }

    return create_notification(
        $pdo,
        $receiver_id,
        'qna_answer',
        $qna_id,
        '문의에 답변이 등록되었습니다.',
        '/qna/qna_view.php?id=' . $qna_id
    );
}
<?php
// /includes/notification_helper.php
// 내부 알림 저장 + FCM 푸시 발송을 한 번에 처리하는 헬퍼
// 사용법: send_notification($pdo, $user_id, 'comment', $board_id, '메시지', '/board/view.php?id=1');

require_once __DIR__ . '/fcm_init.php';
 
/**
 * 알림 저장 + FCM 푸시 발송
 *
 * @param PDO    $pdo
 * @param int    $to_user_id  받는 사람 user_id
 * @param string $type        comment | reply | qna_answer | new_post
 * @param int    $target_id   관련 게시글/댓글 id
 * @param string $message     알림 메시지
 * @param string $url         클릭 시 이동 URL
 * @param string $push_title  푸시 제목 (생략 시 자동)
 */
function send_notification(
    PDO    $pdo,
    int    $to_user_id,
    string $type,
    int    $target_id,
    string $message,
    string $url,
    string $push_title = ''
): void {
    // 1. notifications 테이블에 저장
    try {
        $pdo->prepare("
            INSERT INTO notifications (user_id, type, target_id, message, url)
            VALUES (:uid, :type, :target_id, :message, :url)
        ")->execute([
            ':uid'       => $to_user_id,
            ':type'      => $type,
            ':target_id' => $target_id,
            ':message'   => $message,
            ':url'       => $url,
        ]);
    } catch (PDOException $e) {
        error_log('[notification] DB 저장 오류: ' . $e->getMessage());
    }

// 2. FCM 토큰 조회 (💡 수정: 모든 기기의 토큰을 다 가져오도록 fetchAll 사용)
    try {
        $stmt = $pdo->prepare("SELECT token FROM fcm_tokens WHERE user_id = :uid");
        $stmt->execute([':uid' => $to_user_id]);
        
        // 토큰 문자열만 쏙쏙 뽑아서 배열로 만듦
        $tokens = $stmt->fetchAll(PDO::FETCH_COLUMN); 
        
        if (empty($tokens)) return;

        // 3. FCM 푸시 발송
        $title = $push_title ?: match($type) {
            'comment'    => '새 댓글이 달렸어요',
            'reply'      => '새 답글이 달렸어요',
            'qna_answer' => '문의 답변이 등록됐어요',
            'new_post'   => '새 글이 등록됐어요',
            default      => '한글은 늘 도망가',
        };

        // 💡 [핵심] 유저가 가진 모든 기기(토큰)에 전부 발송!
        foreach ($tokens as $token) {
            fcm_send_to_token($token, $title, $message, $url);
        }

    } catch (PDOException $e) {
        error_log('[notification] FCM 발송 오류: ' . $e->getMessage());
    }
}

/**
 * 게시판 구독자 전체에게 알림 + FCM 발송 (새 글 등록 시)
 *
 * @param PDO    $pdo
 * @param string $board_type   anonymity | writing
 * @param int    $writer_id    글 작성자 user_id (본인 제외)
 * @param int    $board_id     게시글 id
 * @param string $title        게시글 제목
 * @param string $url          게시글 URL
 */
function send_new_post_notification(
    PDO    $pdo,
    string $board_type,
    int    $writer_id,
    int    $board_id,
    string $title,
    string $url
): void {
    try {
        // 게시판 구독자 조회 (작성자 본인 제외)
        $stmt = $pdo->prepare("
            SELECT user_id FROM board_subscriptions
            WHERE board_type = :type AND user_id != :writer_id
        ");
        $stmt->execute([':type' => $board_type, ':writer_id' => $writer_id]);
        $subscribers = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($subscribers)) return;

        $short_title = mb_strimwidth($title, 0, 30, '…');
        $message     = "새 글: {$short_title}";

        foreach ($subscribers as $uid) {
            send_notification($pdo, (int)$uid, 'new_post', $board_id, $message, $url, '새 글이 등록됐어요');
        }

    } catch (PDOException $e) {
        error_log('[notification] 새 글 알림 오류: ' . $e->getMessage());
    }
}

/**
 * 글 구독자 전체에게 알림 + FCM 발송 (댓글 등록 시)
 *
 * @param PDO    $pdo
 * @param int    $board_id       게시글 id
 * @param int    $commenter_id   댓글 작성자 (본인 제외)
 * @param string $comment_text   댓글 내용 요약
 * @param string $url            게시글 URL
 */
function send_comment_notification(
    PDO    $pdo,
    int    $board_id,
    int    $commenter_id,
    string $comment_text,
    string $url
): void {
    try {
        // 1. 게시글 작성자에게 알림
        $stmt = $pdo->prepare("SELECT user_id, title FROM boards WHERE id = :id");
        $stmt->execute([':id' => $board_id]);
        $board = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($board && (int)$board['user_id'] !== $commenter_id) {
            $short = mb_strimwidth($comment_text, 0, 40, '…');
            send_notification(
                $pdo,
                (int)$board['user_id'],
                'comment',
                $board_id,
                "내 글에 댓글이 달렸어요: {$short}",
                $url
            );
        }

        // 2. 글 구독자들에게 알림 (작성자 + 댓글 작성자 제외)
        $exclude = array_filter([
            (int)($board['user_id'] ?? 0),
            $commenter_id,
        ]);
        $placeholders = implode(',', array_fill(0, count($exclude), '?'));

        $stmt = $pdo->prepare("
            SELECT user_id FROM post_subscriptions
            WHERE board_id = :bid
              AND user_id NOT IN ({$placeholders})
        ");
        $stmt->execute(array_merge([':bid' => $board_id], $exclude));

        // PDO named + positional 혼용 불가 → 다시 prepare
        $stmt2 = $pdo->prepare("
            SELECT user_id FROM post_subscriptions
            WHERE board_id = ?
              AND user_id NOT IN ({$placeholders})
        ");
        $stmt2->execute(array_merge([$board_id], $exclude));
        $subs = $stmt2->fetchAll(PDO::FETCH_COLUMN);

        $short = mb_strimwidth($comment_text, 0, 40, '…');
        foreach ($subs as $uid) {
            send_notification(
                $pdo,
                (int)$uid,
                'comment',
                $board_id,
                "구독한 글에 댓글이 달렸어요: {$short}",
                $url
            );
        }

    } catch (PDOException $e) {
        error_log('[notification] 댓글 알림 오류: ' . $e->getMessage());
    }
}

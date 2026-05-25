<?php

function create_notification(
    PDO $pdo,
    int $user_id,
    string $type,
    ?int $target_id,
    string $message,
    ?string $url = null
) {

    // 잘못된 user_id 방어
    if ($user_id <= 0) {
        return false;
    }

    // 빈 메시지 방어
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
}
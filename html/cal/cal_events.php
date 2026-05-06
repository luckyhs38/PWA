<?php
// /html/cal/cal_events.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

require_once '../includes/db.php';

try {
    // FullCalendar가 보내는 조회 범위
    $start = $_GET['start'] ?? null;
    $end   = $_GET['end'] ?? null;

    // 로그인 사용자
    $login_uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

    // start/end를 MySQL DATETIME 형식으로 변환
    $start_dt = null;
    $end_dt = null;

    if (!empty($start) && !empty($end)) {
        $startObj = new DateTime($start);
        $endObj = new DateTime($end);

        $start_dt = $startObj->format('Y-m-d H:i:s');
        $end_dt = $endObj->format('Y-m-d H:i:s');
    }

    $sql = "
        SELECT
            s.id,
            s.user_id,
            s.title,
            s.content,
            s.start_date,
            s.end_date,
            s.allday_yn,
            s.color,
            s.hidden_yn,
            u.nickname AS user_name
        FROM schedules s
        LEFT JOIN users u ON u.id = s.user_id
        WHERE s.deleted_at IS NULL
          AND (s.hidden_yn = 'N' OR s.user_id = :login_uid)
    ";

    $params = [
        ':login_uid' => $login_uid
    ];

    // FullCalendar 조회 범위 필터
    if ($start_dt !== null && $end_dt !== null) {
        $sql .= "
            AND (
                (
                    s.end_date IS NULL
                    AND s.start_date >= :start_dt
                    AND s.start_date < :end_dt
                )
                OR
                (
                    s.end_date IS NOT NULL
                    AND s.start_date < :end_dt
                    AND s.end_date >= :start_dt
                )
            )
        ";

        $params[':start_dt'] = $start_dt;
        $params[':end_dt'] = $end_dt;
    }

    $sql .= " ORDER BY s.start_date ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $events = [];

    foreach ($rows as $row) {
        $allDay = $row['allday_yn'] === 'Y';

        // FullCalendar 날짜 형식 맞추기
        if ($allDay) {
            $startDate = date('Y-m-d', strtotime($row['start_date']));
        } else {
            $startDate = date('Y-m-d\TH:i:s', strtotime($row['start_date']));
        }

        $endDate = null;

        if (!empty($row['end_date'])) {
            if ($allDay) {
                // FullCalendar의 종일 일정 end는 exclusive라 +1일 처리
                $dt = new DateTime($row['end_date']);
                $dt->modify('+1 day');
                $endDate = $dt->format('Y-m-d');
            } else {
                $endDate = date('Y-m-d\TH:i:s', strtotime($row['end_date']));
            }
        }

        $color = $row['color'] ?: '#212529';

        $events[] = [
            'id' => (int)$row['id'],
            'title' => $row['title'],
            'start' => $startDate,
            'end' => $endDate,
            'allDay' => $allDay,
            'backgroundColor' => $color,
            'borderColor' => $color,
            'extendedProps' => [
                'user_id' => (int)$row['user_id'],
                'user_name' => $row['user_name'] ?? '알 수 없음',
                'content' => $row['content'],
                'allday_yn' => $row['allday_yn'],
                'hidden_yn' => $row['hidden_yn']
            ]
        ];
    }

    echo json_encode($events, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);

    echo json_encode([
        'error' => '일정 조회 중 오류가 발생했습니다.',
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
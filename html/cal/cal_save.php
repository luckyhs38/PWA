<?php
// cal_save.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

include '../includes/db.php'; // $pdo 연결 객체

// 로그인 체크
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => '로그인이 필요합니다.']);
    exit;
}

$login_uid = (int)$_SESSION['user_id'];
$action    = $_POST['action'] ?? 'save'; // save | delete


// ════════════════════════════════════════
//  일정 삭제 (소프트 삭제)
// ════════════════════════════════════════
if ($action === 'delete') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => '잘못된 요청입니다.']);
        exit;
    }

    try {
        // 본인 일정인지 확인
        $stmt = $pdo->prepare("SELECT user_id FROM schedules WHERE id = ? AND deleted_at IS NULL");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            echo json_encode(['success' => false, 'message' => '일정을 찾을 수 없습니다.']);
            exit;
        }

        if ((int)$row['user_id'] !== $login_uid) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => '본인 일정만 삭제할 수 있습니다.']);
            exit;
        }

        // 소프트 삭제
        $stmt = $pdo->prepare("UPDATE schedules SET deleted_at = NOW() WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode(['success' => true]);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => '삭제 중 오류가 발생했습니다.']);
    }

    exit;
}

// ════════════════════════════════════════
//  날짜 업데이트 (드래그&드롭 / 리사이즈)
// ════════════════════════════════════════
if ($action === 'update_date') {
    $id         = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $start_date = trim($_POST['start_date'] ?? '');
    $end_date   = trim($_POST['end_date']   ?? '');
    $allday_yn  = ($_POST['allday_yn'] ?? 'N') === 'Y' ? 'Y' : 'N';

    if ($id <= 0 || $start_date === '') {
        echo json_encode(['success' => false, 'message' => '잘못된 요청입니다.']);
        exit;
    }

    try {
        // 본인 일정인지 확인
        $stmt = $pdo->prepare("SELECT user_id FROM schedules WHERE id = ? AND deleted_at IS NULL");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            echo json_encode(['success' => false, 'message' => '일정을 찾을 수 없습니다.']);
            exit;
        }
        if ((int)$row['user_id'] !== $login_uid) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => '본인 일정만 수정할 수 있습니다.']);
            exit;
        }

        // 날짜 정규화
        $start_date = str_replace('T', ' ', $start_date);
        $end_date   = $end_date !== '' ? str_replace('T', ' ', $end_date) : null;

        $stmt = $pdo->prepare("
            UPDATE schedules
            SET start_date = :start_date,
                end_date   = :end_date,
                allday_yn  = :allday_yn
            WHERE id = :id
        ");
        $stmt->execute([
            ':start_date' => $start_date,
            ':end_date'   => $end_date,
            ':allday_yn'  => $allday_yn,
            ':id'         => $id,
        ]);

        echo json_encode(['success' => true]);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => '업데이트 중 오류가 발생했습니다.']);
    }
    exit;
}


// ════════════════════════════════════════
//  일정 저장
// ════════════════════════════════════════

// 입력값 수집 및 trim
$title      = trim($_POST['title']      ?? '');
$content    = trim($_POST['content']    ?? '');
$start_date = trim($_POST['start_date'] ?? '');
$end_date   = trim($_POST['end_date']   ?? '');
$allday_yn  = ($_POST['allday_yn'] ?? 'N') === 'Y' ? 'Y' : 'N';
$color      = trim($_POST['color']      ?? '#212529');
$hidden_yn  = ($_POST['hidden_yn'] ?? 'N') === 'Y' ? 'Y' : 'N';

// ── 유효성 검사 ──────────────────────────
if ($title === '') {
    echo json_encode(['success' => false, 'message' => '제목을 입력해주세요.']);
    exit;
}

if (mb_strlen($title) > 100) {
    echo json_encode(['success' => false, 'message' => '제목은 100자 이내로 입력해주세요.']);
    exit;
}

if ($start_date === '') {
    echo json_encode(['success' => false, 'message' => '시작일시를 입력해주세요.']);
    exit;
}

// 날짜 형식 검증 (YYYY-MM-DD 또는 YYYY-MM-DDTHH:MM)
function isValidDatetime(string $val): bool {
    // datetime-local → YYYY-MM-DDTHH:MM or YYYY-MM-DD HH:MM
    $val = str_replace('T', ' ', $val);
    $formats = ['Y-m-d H:i', 'Y-m-d'];
    foreach ($formats as $fmt) {
        $d = DateTime::createFromFormat($fmt, $val);
        if ($d && $d->format($fmt) === $val) return true;
    }
    return false;
}

if (!isValidDatetime($start_date)) {
    echo json_encode(['success' => false, 'message' => '시작일시 형식이 올바르지 않습니다.']);
    exit;
}

if ($end_date !== '' && !isValidDatetime($end_date)) {
    echo json_encode(['success' => false, 'message' => '종료일시 형식이 올바르지 않습니다.']);
    exit;
}

// 종료일이 시작일보다 앞서면 안 됨
if ($end_date !== '') {
    $startTs = strtotime(str_replace('T', ' ', $start_date));
    $endTs   = strtotime(str_replace('T', ' ', $end_date));
    if ($endTs < $startTs) {
        echo json_encode(['success' => false, 'message' => '종료일시는 시작일시 이후여야 합니다.']);
        exit;
    }
}

// 색상 허용 목록
$allowed_colors = ['#212529', '#0d6efd', '#198754', '#dc3545', '#fd7e14', '#6f42c1'];
if (!in_array($color, $allowed_colors, true)) {
    $color = '#212529';
}

// 종일 일정이면 날짜만 저장 (시간 제거)
if ($allday_yn === 'Y') {
    $start_date = substr(str_replace('T', ' ', $start_date), 0, 10) . ' 00:00:00';
    $end_date   = $end_date !== ''
        ? substr(str_replace('T', ' ', $end_date), 0, 10) . ' 00:00:00'
        : null;
} else {
    $start_date = str_replace('T', ' ', $start_date);
    $end_date   = $end_date !== '' ? str_replace('T', ' ', $end_date) : null;
}

// ── DB 저장 ──────────────────────────────
try {
    $stmt = $pdo->prepare("
        INSERT INTO schedules
            (user_id, title, content, start_date, end_date, allday_yn, color, hidden_yn)
        VALUES
            (:user_id, :title, :content, :start_date, :end_date, :allday_yn, :color, :hidden_yn)
    ");

    $stmt->execute([
        ':user_id'    => $login_uid,
        ':title'      => $title,
        ':content'    => $content ?: null,
        ':start_date' => $start_date,
        ':end_date'   => $end_date,
        ':allday_yn'  => $allday_yn,
        ':color'      => $color,
        ':hidden_yn'  => $hidden_yn,
    ]);

    echo json_encode(['success' => true, 'id' => (int)$pdo->lastInsertId()]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '저장 중 오류가 발생했습니다.']);
}
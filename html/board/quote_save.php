<?php
// /board/quote_save.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

// 로그인 확인
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'msg' => '로그인이 필요합니다.']);
    exit;
}

// POST + JSON만 허용
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'msg' => '잘못된 요청입니다.']);
    exit;
}

$data         = json_decode(file_get_contents('php://input'), true);
$board_id     = isset($data['board_id'])     ? (int)$data['board_id']     : 0;
$start_offset = isset($data['start_offset']) ? (int)$data['start_offset'] : -1;
$end_offset   = isset($data['end_offset'])   ? (int)$data['end_offset']   : -1;
$content      = isset($data['content'])      ? trim($data['content'])     : '';
$user_id      = (int)$_SESSION['user_id'];

// ── 입력값 검증 ────────────────────────────────────────────────
if ($board_id === 0 || $start_offset < 0 || $end_offset <= $start_offset) {
    echo json_encode(['success' => false, 'msg' => '잘못된 요청입니다.']);
    exit;
}

if ($content === '') {
    echo json_encode(['success' => false, 'msg' => '선택된 문장이 없습니다.']);
    exit;
}

// 문장 길이 제한 (너무 짧거나 긴 드래그 방지)
$len = mb_strlen($content);
if ($len < 5) {
    echo json_encode(['success' => false, 'msg' => '너무 짧은 문장입니다. 5자 이상 선택해주세요.']);
    exit;
}
if ($len > 300) {
    echo json_encode(['success' => false, 'msg' => '너무 긴 문장입니다. 300자 이내로 선택해주세요.']);
    exit;
}

try {
    // board가 실제로 존재하는지 확인
    $check = $pdo->prepare("
        SELECT id FROM boards
        WHERE id = :id AND hidden_yn = 'N'
    ");
    $check->execute([':id' => $board_id]);
    if (!$check->fetch()) {
        echo json_encode(['success' => false, 'msg' => '존재하지 않는 게시글입니다.']);
        exit;
    }

    $pdo->beginTransaction();

    // ── quotes: 같은 구간이면 INSERT 무시, 있으면 기존 id 사용 ──
    // UNIQUE KEY uq_quote (board_id, start_offset, end_offset) 활용
    $pdo->prepare("
        INSERT INTO quotes (board_id, user_id, content, start_offset, end_offset)
        VALUES (:bid, :uid, :content, :start, :end)
        ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)
    ")->execute([
        ':bid'     => $board_id,
        ':uid'     => $user_id,
        ':content' => $content,
        ':start'   => $start_offset,
        ':end'     => $end_offset,
    ]);

    $quote_id = (int)$pdo->lastInsertId();

    // ── quote_scraps: 내 서랍에 저장 ──────────────────────────
    // 이미 저장한 적 있는지 확인 (soft delete 포함)
    $scrap_check = $pdo->prepare("
        SELECT id, deleted_at FROM quote_scraps
        WHERE quote_id = :qid AND user_id = :uid
    ");
    $scrap_check->execute([':qid' => $quote_id, ':uid' => $user_id]);
    $existing_scrap = $scrap_check->fetch(PDO::FETCH_ASSOC);

    $is_new_scrap = false;

    if (!$existing_scrap) {
        // 최초 저장
        $pdo->prepare("
            INSERT INTO quote_scraps (quote_id, user_id)
            VALUES (:qid, :uid)
        ")->execute([':qid' => $quote_id, ':uid' => $user_id]);

        $is_new_scrap = true;

    } elseif ($existing_scrap['deleted_at'] !== null) {
        // 이전에 삭제했던 항목 → 복구
        $pdo->prepare("
            UPDATE quote_scraps
            SET deleted_at = NULL, created_at = NOW()
            WHERE quote_id = :qid AND user_id = :uid
        ")->execute([':qid' => $quote_id, ':uid' => $user_id]);

        $is_new_scrap = true;

    }
    // 이미 저장 중이면 highlight_count 중복 증가 없이 그냥 넘어감

    // ── highlight_count 캐시 증가 (새로 저장된 경우만) ─────────
    if ($is_new_scrap) {
        $pdo->prepare("
            UPDATE quotes
            SET highlight_count = highlight_count + 1
            WHERE id = :id
        ")->execute([':id' => $quote_id]);
    }

    $pdo->commit();

    // 이미 저장된 문장이었는지 여부도 함께 반환
    $already = !$is_new_scrap && $existing_scrap;

    echo json_encode([
        'success'  => true,
        'quote_id' => $quote_id,
        'already'  => $already, // true면 "이미 저장된 문장"
    ]);

} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'msg' => '오류가 발생했습니다.']);
}
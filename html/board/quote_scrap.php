<?php
// /board/quote_scrap.php
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

$data     = json_decode(file_get_contents('php://input'), true);
$quote_id = isset($data['quote_id']) ? (int)$data['quote_id'] : 0;
$action   = $data['action'] ?? 'toggle'; // toggle | delete
$user_id  = (int)$_SESSION['user_id'];

if ($quote_id === 0) {
    echo json_encode(['success' => false, 'msg' => '잘못된 요청입니다.']);
    exit;
}

try {
    // quote가 실제로 존재하는지 확인
    $check = $pdo->prepare("SELECT id FROM quotes WHERE id = :id");
    $check->execute([':id' => $quote_id]);
    if (!$check->fetch()) {
        echo json_encode(['success' => false, 'msg' => '존재하지 않는 문장입니다.']);
        exit;
    }

    // 내 서랍에 이미 있는지 확인 (soft delete 포함)
    $exists = $pdo->prepare("
        SELECT id, deleted_at FROM quote_scraps
        WHERE quote_id = :qid AND user_id = :uid
    ");
    $exists->execute([':qid' => $quote_id, ':uid' => $user_id]);
    $scrap = $exists->fetch(PDO::FETCH_ASSOC);

    // ── 내 서랍에서 삭제 (소프트) ──────────────────────────────
    // archive.php 내 서랍탭에서 삭제 버튼 클릭 시
    if ($action === 'delete') {
        if (!$scrap || $scrap['deleted_at'] !== null) {
            echo json_encode(['success' => false, 'msg' => '이미 삭제된 항목입니다.']);
            exit;
        }

        $pdo->beginTransaction();

        $pdo->prepare("
            UPDATE quote_scraps
            SET deleted_at = NOW()
            WHERE quote_id = :qid AND user_id = :uid
        ")->execute([':qid' => $quote_id, ':uid' => $user_id]);

        $pdo->prepare("
            UPDATE quotes
            SET highlight_count = GREATEST(highlight_count - 1, 0)
            WHERE id = :id
        ")->execute([':id' => $quote_id]);

        $pdo->commit();

        echo json_encode(['success' => true, 'scrapped' => false]);
        exit;
    }

    // ── 저장 / 취소 토글 ────────────────────────────────────────
    $pdo->beginTransaction();

    if ($scrap && $scrap['deleted_at'] === null) {
        // 저장 취소: soft delete
        $pdo->prepare("
            UPDATE quote_scraps
            SET deleted_at = NOW()
            WHERE quote_id = :qid AND user_id = :uid
        ")->execute([':qid' => $quote_id, ':uid' => $user_id]);

        $pdo->prepare("
            UPDATE quotes
            SET highlight_count = GREATEST(highlight_count - 1, 0)
            WHERE id = :id
        ")->execute([':id' => $quote_id]);

        $scrapped = false;

    } elseif ($scrap && $scrap['deleted_at'] !== null) {
        // 이전에 삭제했던 항목 → 복구 (deleted_at 초기화)
        $pdo->prepare("
            UPDATE quote_scraps
            SET deleted_at = NULL, created_at = NOW()
            WHERE quote_id = :qid AND user_id = :uid
        ")->execute([':qid' => $quote_id, ':uid' => $user_id]);

        $pdo->prepare("
            UPDATE quotes
            SET highlight_count = highlight_count + 1
            WHERE id = :id
        ")->execute([':id' => $quote_id]);

        $scrapped = true;

    } else {
        // 최초 저장
        $pdo->prepare("
            INSERT INTO quote_scraps (quote_id, user_id)
            VALUES (:qid, :uid)
        ")->execute([':qid' => $quote_id, ':uid' => $user_id]);

        $pdo->prepare("
            UPDATE quotes
            SET highlight_count = highlight_count + 1
            WHERE id = :id
        ")->execute([':id' => $quote_id]);

        $scrapped = true;
    }

    $pdo->commit();

    echo json_encode([
        'success'  => true,
        'scrapped' => $scrapped,
    ]);

} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'msg' => '오류가 발생했습니다.']);
}
<?php
// /board/quote_like.php
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

    // 이미 좋아요 했는지 확인
    $exists = $pdo->prepare("
        SELECT id FROM quote_likes
        WHERE quote_id = :qid AND user_id = :uid
    ");
    $exists->execute([':qid' => $quote_id, ':uid' => $user_id]);
    $already_liked = $exists->fetch();

    // 트랜잭션: quote_likes + quotes.like_count 캐시 동시 처리
    $pdo->beginTransaction();

    if ($already_liked) {
        // 좋아요 취소
        $pdo->prepare("
            DELETE FROM quote_likes
            WHERE quote_id = :qid AND user_id = :uid
        ")->execute([':qid' => $quote_id, ':uid' => $user_id]);

        $pdo->prepare("
            UPDATE quotes
            SET like_count = GREATEST(like_count - 1, 0)
            WHERE id = :id
        ")->execute([':id' => $quote_id]);

        $liked = false;

    } else {
        // 좋아요 추가
        $pdo->prepare("
            INSERT INTO quote_likes (quote_id, user_id)
            VALUES (:qid, :uid)
        ")->execute([':qid' => $quote_id, ':uid' => $user_id]);

        $pdo->prepare("
            UPDATE quotes
            SET like_count = like_count + 1
            WHERE id = :id
        ")->execute([':id' => $quote_id]);

        $liked = true;
    }

    $pdo->commit();

    // 최신 like_count 반환
    $row = $pdo->prepare("SELECT like_count FROM quotes WHERE id = :id");
    $row->execute([':id' => $quote_id]);
    $like_count = (int)$row->fetchColumn();

    echo json_encode([
        'success'    => true,
        'liked'      => $liked,
        'like_count' => $like_count,
    ]);

} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'msg' => '오류가 발생했습니다.']);
}
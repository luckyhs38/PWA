<?php
// /qna/qna.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/db.php';

$page    = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$status  = $_GET['status'] ?? 'all';
$q       = trim($_GET['q'] ?? '');

$allowed_status = ['all', 'pending', 'answered', 'mine'];

if (!in_array($status, $allowed_status)) {
    $status = 'all';
}

if ($status === 'mine' && !isset($_SESSION['user_id'])) {
    echo "<script>alert('로그인 후 이용 가능합니다.'); location.href='login.php';</script>";
    exit;
}

// 임시 관리자 기준: users.id = 1
$is_admin = isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === 1;

$page_size = 10;
$offset = ($page - 1) * $page_size;

$where = "WHERE q.deleted_at IS NULL";
$params = [];

if ($status === 'pending') {
    $where .= " AND q.status = 'pending'";
} elseif ($status === 'answered') {
    $where .= " AND q.status = 'answered'";
} elseif ($status === 'mine') {
    $where .= " AND q.user_id = :login_user_id";
    $params[':login_user_id'] = $_SESSION['user_id'];
}

if ($q !== '') {
    $where .= " AND (q.title LIKE :q OR q.content LIKE :q)";
    $params[':q'] = '%' . $q . '%';
}

try {
    // 전체 개수
    $count_sql = "
        SELECT COUNT(*)
        FROM qna q
        JOIN users u ON q.user_id = u.id
        {$where}
    ";

    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_count = (int)$stmt->fetchColumn();
    $total_page = max(1, (int)ceil($total_count / $page_size));

    // 목록 조회
    $list_sql = "
        SELECT
            q.id,
            q.user_id,
            q.title,
            q.private_yn,
            q.status,
            q.view_count,
            q.created_at,
            q.answered_at,
            u.nickname,
            u.user_id AS login_id
        FROM qna q
        JOIN users u ON q.user_id = u.id
        {$where}
        ORDER BY q.id DESC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $pdo->prepare($list_sql);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    $stmt->bindValue(':limit', $page_size, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $qna_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 사이드바 통계
    $stat_params = [];
    $my_count_sql = "0";

    if (isset($_SESSION['user_id'])) {
        $my_count_sql = "(SELECT COUNT(*) FROM qna WHERE user_id = :stat_user_id AND deleted_at IS NULL)";
        $stat_params[':stat_user_id'] = $_SESSION['user_id'];
    }

    $stat_sql = "
        SELECT
            (SELECT COUNT(*) FROM qna WHERE deleted_at IS NULL) AS total_qna,
            (SELECT COUNT(*) FROM qna WHERE status = 'pending' AND deleted_at IS NULL) AS pending_qna,
            (SELECT COUNT(*) FROM qna WHERE status = 'answered' AND deleted_at IS NULL) AS answered_qna,
            {$my_count_sql} AS my_qna
    ";

    $stmt = $pdo->prepare($stat_sql);
    $stmt->execute($stat_params);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // 최근 답변 완료 문의
    $recent_answer_sql = "
        SELECT id, title, answered_at
        FROM qna
        WHERE status = 'answered'
          AND deleted_at IS NULL
        ORDER BY answered_at DESC
        LIMIT 5
    ";

    $recent_answered = $pdo->query($recent_answer_sql)->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Q&A 조회 오류: " . $e->getMessage());
}

function status_label($status) {
    return $status === 'answered' ? '답변완료' : '답변대기';
}

include '../includes/header.php';
?>

<style>
.qna-wrap {
    width: 100%;
    max-width: 1100px;
    margin: 110px auto 80px;
    padding: 0 24px;
}

.qna-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    gap: 24px;
    border-bottom: 2px solid #1a1a1a;
    padding-bottom: 18px;
}

.qna-title {
    font-family: 'Noto Serif KR', serif;
    font-size: 26px;
    font-weight: 500;
    color: #1a1a1a;
    letter-spacing: -0.4px;
}

.qna-sub {
    font-size: 13px;
    color: #aaa;
    margin-top: 4px;
}

.qna-search {
    position: relative;
    width: 260px;
}

.qna-search input {
    width: 100%;
    border: 1px solid #ddd;
    border-radius: 999px;
    padding: 9px 38px 9px 16px;
    font-size: 13px;
    color: #1a1a1a;
    outline: none;
}

.qna-search input:focus {
    border-color: #1a1a1a;
}

.qna-search .si {
    position: absolute;
    right: 13px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 15px;
    color: #ccc;
}

.qna-tabs {
    display: flex;
    border-bottom: 1px solid #eee;
    margin-bottom: 28px;
}

.qna-tab {
    padding: 14px 22px;
    font-size: 14px;
    color: #aaa;
    text-decoration: none;
    border-bottom: 2px solid transparent;
    margin-bottom: -1px;
    font-family: 'Noto Serif KR', serif;
}

.qna-tab:hover {
    color: #555;
}

.qna-tab.active {
    color: #1a1a1a;
    border-bottom-color: #1a1a1a;
}

.qna-layout {
    display: grid;
    grid-template-columns: 1fr 270px;
    gap: 48px;
    align-items: start;
}

.qna-main-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 18px;
}

.qna-count {
    font-size: 13px;
    color: #999;
}

.qna-count strong {
    color: #1a1a1a;
    font-weight: 500;
}

.qna-list {
    border-top: 1px solid #eee;
}

.qna-item {
    display: grid;
    grid-template-columns: 96px 1fr 90px;
    gap: 18px;
    align-items: center;
    padding: 20px 0;
    border-bottom: 1px solid #f0f0f0;
}

.qna-status {
    display: inline-flex;
    justify-content: center;
    align-items: center;
    width: 82px;
    border-radius: 999px;
    padding: 6px 0;
    font-size: 12px;
    white-space: nowrap;
}

.qna-status.pending {
    background: #fafafa;
    border: 1px solid #ddd;
    color: #777;
}

.qna-status.answered {
    background: #1a1a1a;
    border: 1px solid #1a1a1a;
    color: #fff;
}

.qna-item-title {
    font-family: 'Noto Serif KR', serif;
    font-size: 16px;
    color: #1a1a1a;
    text-decoration: none;
    line-height: 1.6;
}

.qna-item-title:hover {
    text-decoration: underline;
}

.qna-item-title.private {
    color: #777;
}

.qna-item-title .bi-lock {
    color: #999;
    font-size: 13px;
}

.qna-meta {
    margin-top: 6px;
    font-size: 12px;
    color: #aaa;
}

.qna-view {
    font-size: 12px;
    color: #aaa;
    text-align: right;
    white-space: nowrap;
}

.qna-empty {
    text-align: center;
    padding: 70px 0;
    color: #ccc;
    border-bottom: 1px solid #eee;
}

.qna-empty i {
    display: block;
    font-size: 34px;
    margin-bottom: 12px;
}

.qna-bottom {
    display: flex;
    justify-content: center;
    align-items: center;
    margin-top: 26px;
}

.qna-pagination {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}

.qna-pagination a {
    min-width: 32px;
    height: 32px;
    border: 1px solid #ddd;
    color: #777;
    text-decoration: none;
    font-size: 13px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.qna-pagination a.active {
    background: #1a1a1a;
    border-color: #1a1a1a;
    color: #fff;
}

.qna-sidebar {
    position: sticky;
    top: 100px;
    display: flex;
    flex-direction: column;
    gap: 18px;
}

.side-card {
    background: #fafafa;
    border: 1px solid #f0f0f0;
    border-radius: 12px;
    padding: 18px 20px;
}

.side-card-title {
    font-size: 11px;
    color: #bbb;
    letter-spacing: 0.4px;
    text-transform: uppercase;
    margin-bottom: 14px;
}

.stat-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
}

.stat-cell {
    background: #fff;
    border: 1px solid #f0f0f0;
    border-radius: 8px;
    padding: 12px 14px;
}

.stat-label {
    font-size: 11px;
    color: #aaa;
    margin-bottom: 4px;
}

.stat-val {
    font-size: 20px;
    font-weight: 500;
    color: #1a1a1a;
}

.guide-text {
    font-size: 13px;
    color: #777;
    line-height: 1.8;
    margin-bottom: 0;
}

.recent-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.recent-item a {
    display: block;
    font-family: 'Noto Serif KR', serif;
    font-size: 13px;
    color: #1a1a1a;
    text-decoration: none;
    line-height: 1.6;
}

.recent-item a:hover {
    text-decoration: underline;
}

.recent-date {
    font-size: 11px;
    color: #bbb;
    margin-top: 2px;
}

.qna-btn {
    border-radius: 999px;
    font-size: 13px;
    padding: 8px 18px;
}

@media (max-width: 860px) {
    .qna-layout {
        grid-template-columns: 1fr;
        gap: 0;
    }

    .qna-sidebar {
        position: static;
        margin-top: 42px;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 14px;
    }
}

@media (max-width: 600px) {
    .qna-wrap {
        margin-top: 80px;
        padding: 0 15px;
    }

    .qna-top {
        flex-direction: column;
        align-items: flex-start;
        gap: 14px;
    }

    .qna-search {
        width: 100%;
    }

    .qna-title {
        font-size: 22px;
    }

    .qna-tab {
        padding: 12px 14px;
        font-size: 13px;
    }

    .qna-main-head {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
    }

    .qna-item {
        grid-template-columns: 1fr;
        gap: 8px;
        padding: 18px 0;
    }

    .qna-view {
        text-align: left;
    }

    .qna-bottom {
        flex-direction: column;
        align-items: stretch;
        gap: 16px;
    }

    .qna-sidebar {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="qna-wrap">

    <div class="qna-top">
        <div>
            <div class="qna-title">문의하기</div>
            <div class="qna-sub">사이트 이용 중 궁금한 점을 남겨주세요.</div>
        </div>

        <form method="get" action="qna.php" class="qna-search">
            <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
            <input
                type="text"
                name="q"
                value="<?= htmlspecialchars($q) ?>"
                placeholder="문의 검색">
            <i class="bi bi-search si"></i>
        </form>
    </div>

    <div class="qna-tabs">
        <a href="qna.php?status=all&q=<?= urlencode($q) ?>"
           class="qna-tab <?= $status === 'all' ? 'active' : '' ?>">
            전체
        </a>

        <a href="qna.php?status=pending&q=<?= urlencode($q) ?>"
           class="qna-tab <?= $status === 'pending' ? 'active' : '' ?>">
            답변대기
        </a>

        <a href="qna.php?status=answered&q=<?= urlencode($q) ?>"
           class="qna-tab <?= $status === 'answered' ? 'active' : '' ?>">
            답변완료
        </a>

        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="qna.php?status=mine&q=<?= urlencode($q) ?>"
               class="qna-tab <?= $status === 'mine' ? 'active' : '' ?>">
                내 문의
            </a>
        <?php endif; ?>
    </div>

    <div class="qna-layout">

        <main>
            <div class="qna-main-head">
                <div class="qna-count">
                    총 <strong><?= number_format($total_count) ?></strong>건의 문의
                </div>

                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="qna_write.php" class="btn btn-dark qna-btn">
                        문의 작성
                    </a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-outline-dark qna-btn">
                        로그인 후 문의
                    </a>
                <?php endif; ?>
            </div>

            <div class="qna-list">
                <?php if (empty($qna_list)): ?>
                    <div class="qna-empty">
                        <i class="bi bi-chat-square-text"></i>
                        <?= $q !== '' ? '검색 결과가 없습니다.' : '등록된 문의가 없습니다.' ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($qna_list as $qna): ?>
                        <?php
                            $is_private = $qna['private_yn'] === 'Y';
                            $is_writer = isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === (int)$qna['user_id'];
                            $can_read_private = !$is_private || $is_writer || $is_admin;
                        ?>

                        <div class="qna-item">
                            <div>
                                <span class="qna-status <?= htmlspecialchars($qna['status']) ?>">
                                    <?= status_label($qna['status']) ?>
                                </span>
                            </div>

                            <div>
                                <a href="qna_view.php?id=<?= (int)$qna['id'] ?>"
                                   class="qna-item-title <?= $is_private ? 'private' : '' ?>">

                                    <?php if ($is_private && !$can_read_private): ?>
                                        <i class="bi bi-lock me-1"></i>
                                        비공개 문의입니다
                                    <?php elseif ($is_private): ?>
                                        <i class="bi bi-lock me-1"></i>
                                        <?= htmlspecialchars($qna['title']) ?>
                                    <?php else: ?>
                                        <?= htmlspecialchars($qna['title']) ?>
                                    <?php endif; ?>
                                </a>

                                <div class="qna-meta">
                                    <?= htmlspecialchars($qna['nickname'] ?? $qna['login_id']) ?>
                                    · <?= htmlspecialchars($qna['created_at']) ?>
                                    <?php if ($qna['status'] === 'answered' && !empty($qna['answered_at'])): ?>
                                        · 답변 <?= htmlspecialchars($qna['answered_at']) ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="qna-view">
                                조회 <?= number_format($qna['view_count']) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="qna-bottom">
                <div class="qna-pagination">
                    <?php for ($i = 1; $i <= $total_page; $i++): ?>
                        <a href="qna.php?page=<?= $i ?>&status=<?= urlencode($status) ?>&q=<?= urlencode($q) ?>"
                           class="<?= $page === $i ? 'active' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                </div>
            </div>
        </main>

        <aside class="qna-sidebar">

            <div class="side-card">
                <div class="side-card-title">Q&A Stats</div>

                <div class="stat-grid">
                    <div class="stat-cell">
                        <div class="stat-label">전체 문의</div>
                        <div class="stat-val"><?= number_format($stats['total_qna']) ?></div>
                    </div>

                    <div class="stat-cell">
                        <div class="stat-label">답변대기</div>
                        <div class="stat-val"><?= number_format($stats['pending_qna']) ?></div>
                    </div>

                    <div class="stat-cell">
                        <div class="stat-label">답변완료</div>
                        <div class="stat-val"><?= number_format($stats['answered_qna']) ?></div>
                    </div>

                    <div class="stat-cell">
                        <div class="stat-label">내 문의</div>
                        <div class="stat-val"><?= number_format($stats['my_qna']) ?></div>
                    </div>
                </div>
            </div>

            <div class="side-card">
                <div class="side-card-title">Guide</div>
                <p class="guide-text">
                    문의 작성 시 공개/비공개를 선택할 수 있습니다.
                    비공개 문의는 작성자와 관리자만 확인할 수 있습니다.
                </p>
            </div>

            <?php if (!empty($recent_answered)): ?>
                <div class="side-card">
                    <div class="side-card-title">Recent Answers</div>

                    <div class="recent-list">
                        <?php foreach ($recent_answered as $recent): ?>
                            <div class="recent-item">
                                <a href="qna_view.php?id=<?= (int)$recent['id'] ?>">
                                    <?= htmlspecialchars($recent['title']) ?>
                                </a>
                                <div class="recent-date">
                                    <?= htmlspecialchars($recent['answered_at']) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

        </aside>

    </div>
</div>

<?php include '../includes/footer.php'; ?>
<?php
// /board/archive.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/db.php';

$tab  = $_GET['tab']  ?? 'all';
$sort = $_GET['sort'] ?? 'likes';
$q    = trim($_GET['q'] ?? '');

$allowed_tabs  = ['all', 'mine'];
$allowed_sorts = ['likes', 'highlights', 'recent'];

if (!in_array($tab,  $allowed_tabs))  $tab  = 'all';
if (!in_array($sort, $allowed_sorts)) $sort = 'likes';

if ($tab === 'mine' && !isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

try {
    $params = [];

    // ── 메인 문장 목록 ──────────────────────────────────────────
    if ($tab === 'mine') {
        $order = match($sort) {
            'highlights' => 'q.highlight_count DESC',
            'recent'     => 'qs.created_at DESC',
            default      => 'q.like_count DESC',
        };

        $sql = "
            SELECT
                q.id,
                q.content,
                q.like_count,
                q.highlight_count,
                qs.memo,
                qs.created_at AS scrapped_at,
                b.title       AS board_title,
                b.id          AS board_id,
                b.board_type,
                CASE WHEN ql.id IS NOT NULL THEN 1 ELSE 0 END AS is_liked
            FROM quote_scraps qs
            JOIN   quotes q ON qs.quote_id = q.id
            JOIN   boards b ON q.board_id  = b.id
            LEFT JOIN quote_likes ql
                ON ql.quote_id = q.id AND ql.user_id = :uid2
            WHERE qs.user_id    = :uid
              AND qs.deleted_at IS NULL
        ";
        if ($q !== '') {
            $sql .= " AND q.content LIKE :q";
            $params[':q'] = '%' . $q . '%';
        }
        $sql .= " ORDER BY {$order}";
        $params[':uid']  = $_SESSION['user_id'];
        $params[':uid2'] = $_SESSION['user_id'];

    } else {
        $order = match($sort) {
            'highlights' => 'q.highlight_count DESC',
            'recent'     => 'q.created_at DESC',
            default      => 'q.like_count DESC',
        };

        $uid_cols = isset($_SESSION['user_id'])
            ? ", CASE WHEN ql.id IS NOT NULL THEN 1 ELSE 0 END AS is_liked
               , CASE WHEN qs.id IS NOT NULL THEN 1 ELSE 0 END AS is_scrapped"
            : ", 0 AS is_liked, 0 AS is_scrapped";

        $sql = "
            SELECT
                q.id,
                q.content,
                q.like_count,
                q.highlight_count,
                q.created_at,
                b.title AS board_title,
                b.id    AS board_id,
                b.board_type
                {$uid_cols}
            FROM quotes q
            JOIN boards b ON q.board_id = b.id
        ";

        if (isset($_SESSION['user_id'])) {
            $sql .= "
            LEFT JOIN quote_likes ql
                ON ql.quote_id = q.id AND ql.user_id = :uid
            LEFT JOIN quote_scraps qs
                ON qs.quote_id = q.id AND qs.user_id = :uid2 AND qs.deleted_at IS NULL
            ";
            $params[':uid']  = $_SESSION['user_id'];
            $params[':uid2'] = $_SESSION['user_id'];
        }

        if ($q !== '') {
            $sql .= " WHERE (q.like_count > 0 OR q.highlight_count > 0)
                    AND q.content LIKE :q";
            $params[':q'] = '%' . $q . '%';
        } else {
            $sql .= " WHERE (q.like_count > 0 OR q.highlight_count > 0)";
        }
        $sql .= " ORDER BY {$order} LIMIT 100";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $quotes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ── 사이드바 통계 ────────────────────────────────────────────
    $stats = $pdo->query("
        SELECT
            (SELECT COUNT(*) FROM quotes)                          AS total_quotes,
            (SELECT COALESCE(SUM(like_count), 0) FROM quotes)     AS total_likes,
            (SELECT COUNT(DISTINCT user_id) FROM quote_scraps
             WHERE deleted_at IS NULL)                            AS total_users,
            (SELECT COUNT(*) FROM quotes
             WHERE DATE(created_at) = CURDATE())                  AS today_added
    ")->fetch(PDO::FETCH_ASSOC);

    // ── 이번 주 인기 TOP 5 ───────────────────────────────────────
    $hot = $pdo->query("
        SELECT q.id, q.content, q.like_count, b.title AS board_title
        FROM quotes q
        JOIN boards b ON q.board_id = b.id
        WHERE q.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY q.like_count DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

    // ── 많이 나온 글 TOP 8 ───────────────────────────────────────
    $top_boards = $pdo->query("
        SELECT b.id, b.title, b.board_type, COUNT(q.id) AS quote_cnt
        FROM quotes q
        JOIN boards b ON q.board_id = b.id
        GROUP BY b.id
        ORDER BY quote_cnt DESC
        LIMIT 8
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("오류: " . $e->getMessage());
}

// 검색어 하이라이트 헬퍼
function highlight(string $text, string $q): string {
    $safe = htmlspecialchars($text);
    if ($q === '') return $safe;
    $pattern = '/' . preg_quote(htmlspecialchars($q), '/') . '/ui';
    return preg_replace($pattern, '<mark>$0</mark>', $safe);
}

include '../includes/header.php';
?>

<style>
/* ── 공통 ── */
.arc-wrap {
    max-width: 1100px;
    margin: 110px auto 80px;
    padding: 0 24px;
}

/* ── 상단 헤더 ── */
.arc-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    border-bottom: 2px solid #1a1a1a;
    padding-bottom: 18px;
}
.arc-title {
    font-family: 'Noto Serif KR', serif;
    font-size: 26px;
    font-weight: 500;
    color: #1a1a1a;
    letter-spacing: -.4px;
}
.arc-sub {
    font-size: 13px;
    color: #aaa;
    margin-top: 4px;
}
.arc-search-wrap { position: relative; }
.arc-search-wrap input {
    border: 1px solid #ddd;
    border-radius: 999px;
    padding: 8px 36px 8px 16px;
    font-size: 13px;
    color: #1a1a1a;
    background: #fff;
    outline: none;
    width: 220px;
    font-family: sans-serif;
    transition: border-color .15s;
}
.arc-search-wrap input:focus { border-color: #1a1a1a; }
.arc-search-wrap .si {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 15px;
    color: #ccc;
}

/* ── 탭 ── */
.arc-tabs {
    display: flex;
    border-bottom: 1px solid #eee;
    margin-bottom: 28px;
}
.arc-tab {
    padding: 14px 22px;
    font-size: 14px;
    color: #bbb;
    cursor: pointer;
    border: none;
    background: none;
    border-bottom: 2px solid transparent;
    margin-bottom: -1px;
    font-family: 'Noto Serif KR', serif;
    transition: color .15s;
    text-decoration: none;
    display: inline-block;
}
.arc-tab.active { color: #1a1a1a; border-bottom-color: #1a1a1a; }
.arc-tab:hover  { color: #555; }

/* ── 2컬럼 레이아웃 ── */
.arc-layout {
    display: grid;
    grid-template-columns: 1fr 260px;
    gap: 48px;
    align-items: start;
}

/* ── 정렬 버튼 ── */
.sort-row {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 20px;
}
.sort-label {
    font-size: 12px;
    color: #bbb;
    font-family: sans-serif;
    margin-right: 4px;
}
.sort-btn {
    border: 1px solid #ddd;
    background: #fff;
    border-radius: 999px;
    padding: 6px 14px;
    font-size: 12px;
    color: #888;
    cursor: pointer;
    font-family: sans-serif;
    text-decoration: none;
    transition: all .15s;
    white-space: nowrap;
}
.sort-btn:hover  { border-color: #aaa; color: #333; }
.sort-btn.active { background: #1a1a1a; color: #fff; border-color: #1a1a1a; }

/* ── 문장 목록 ── */
.q-list { display: flex; flex-direction: column; }

.q-item {
    display: flex;
    gap: 20px;
    padding: 22px 0;
    border-bottom: 1px solid #f0f0f0;
    align-items: flex-start;
}
.q-item:last-child { border-bottom: none; }

.q-rank {
    font-size: 11px;
    font-weight: 500;
    color: #ccc;
    min-width: 22px;
    text-align: right;
    padding-top: 5px;
    flex-shrink: 0;
    font-family: sans-serif;
    font-variant-numeric: tabular-nums;
}
.q-rank.top { color: #1a1a1a; font-size: 14px; }

.q-body { flex: 1; min-width: 0; }

.q-text {
    font-family: 'Noto Serif KR', serif;
    font-size: 17px;
    font-weight: 400;
    color: #1a1a1a;
    line-height: 1.85;
    margin-bottom: 12px;
    word-break: keep-all;
}
.q-text mark {
    background: #f5f3ef;
    color: #1a1a1a;
    border-radius: 2px;
    padding: 0 2px;
}

.q-foot {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
.q-src { font-size: 12px; color: #bbb; font-family: sans-serif; }
.q-src a { color: #bbb; text-decoration: none; }
.q-src a:hover { color: #1a1a1a; }

.q-memo {
    font-size: 12px;
    color: #aaa;
    margin-top: 8px;
    font-style: italic;
    line-height: 1.5;
}
.q-memo::before { content: '" '; }
.q-memo::after  { content: ' "'; }

.q-actions { display: flex; gap: 6px; margin-left: auto; }

.act-btn {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    border: 1px solid #eee;
    background: #fff;
    border-radius: 999px;
    padding: 5px 12px;
    font-size: 12px;
    color: #aaa;
    cursor: pointer;
    font-family: sans-serif;
    transition: all .15s;
    line-height: 1;
}
.act-btn i { font-size: 13px; }
.act-btn:hover    { border-color: #bbb; color: #555; }
.act-btn.liked    { border-color: #1a1a1a; color: #1a1a1a; }
.act-btn.saved    { border-color: #1a1a1a; color: #1a1a1a; }
.act-btn.del-btn:hover { border-color: #dc3545; color: #dc3545; }

/* ── 사이드바 ── */
.arc-sidebar {
    display: flex;
    flex-direction: column;
    gap: 20px;
    position: sticky;
    top: 100px;
}
.s-card {
    background: #fafafa;
    border: 1px solid #f0f0f0;
    border-radius: 12px;
    padding: 18px 20px;
}
.s-card-title {
    font-size: 11px;
    color: #bbb;
    font-family: sans-serif;
    letter-spacing: .4px;
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
.stat-label { font-size: 11px; color: #bbb; font-family: sans-serif; margin-bottom: 4px; }
.stat-val   { font-size: 20px; font-weight: 500; color: #1a1a1a; font-family: sans-serif; }

.hot-list { display: flex; flex-direction: column; gap: 12px; }
.hot-item { display: flex; gap: 10px; align-items: flex-start; }
.hot-num {
    font-size: 11px;
    font-weight: 500;
    color: #ccc;
    min-width: 14px;
    flex-shrink: 0;
    padding-top: 3px;
    font-family: sans-serif;
}
.hot-num.top { color: #1a1a1a; }
.hot-text { font-family: 'Noto Serif KR', serif; font-size: 13px; color: #1a1a1a; line-height: 1.6; }
.hot-cnt  { font-size: 11px; color: #bbb; margin-top: 2px; font-family: sans-serif; }

.tag-cloud { display: flex; flex-wrap: wrap; gap: 6px; }
.tag-item {
    border: 1px solid #eee;
    border-radius: 999px;
    padding: 5px 12px;
    font-size: 12px;
    color: #888;
    text-decoration: none;
    font-family: sans-serif;
    transition: all .15s;
    white-space: nowrap;
}
.tag-item:hover { border-color: #aaa; color: #1a1a1a; }

/* ── 빈 상태 ── */
.arc-empty {
    text-align: center;
    padding: 60px 0;
    color: #ccc;
    font-family: sans-serif;
    font-size: 14px;
}
.arc-empty i { font-size: 34px; display: block; margin-bottom: 12px; }

/* ── 모달 ── */
.arc-modal-bg {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.3);
    z-index: 1050;
    align-items: center;
    justify-content: center;
}
.arc-modal-bg.show { display: flex; }
.arc-modal {
    background: #fff;
    border-radius: 12px;
    padding: 28px 28px 20px;
    width: 320px;
    max-width: 90vw;
    box-shadow: 0 8px 30px rgba(0,0,0,0.1);
}
.arc-modal-title { font-size: 15px; font-weight: 500; color: #1a1a1a; margin-bottom: 8px; }
.arc-modal-desc  { font-size: 13px; color: #999; line-height: 1.6; margin-bottom: 20px; }
.arc-modal-btns  { display: flex; justify-content: flex-end; gap: 8px; }
.arc-mbtn {
    font-size: 13px;
    padding: 7px 16px;
    border-radius: 6px;
    cursor: pointer;
    border: 1px solid #ddd;
    background: #fff;
    color: #333;
    font-family: sans-serif;
}
.arc-mbtn:hover { background: #f5f5f5; }
.arc-mbtn.danger { background: #fff0f0; border-color: #f5c6c6; color: #dc3545; font-weight: 500; }
.arc-mbtn.danger:hover { background: #ffe0e0; }

/* ── 반응형 ── */
@media (max-width: 860px) {
    .arc-layout {
        grid-template-columns: 1fr;
        gap: 0;
    }
    .arc-sidebar {
        position: static;
        margin-top: 40px;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 14px;
    }
}
@media (max-width: 600px) {
    .arc-wrap   { margin-top: 80px; padding: 0 15px; }
    .arc-top    { flex-direction: column; align-items: flex-start; gap: 14px; }
    .arc-search-wrap input { width: 100%; }
    .arc-title  { font-size: 22px; }
    .q-text     { font-size: 15px; }
    .arc-tab    { padding: 12px 14px; font-size: 13px; }
    .arc-sidebar { grid-template-columns: 1fr; }
    .q-actions  { margin-left: 0; margin-top: 6px; }
    .q-foot     { flex-direction: column; align-items: flex-start; gap: 8px; }
}
</style>

<div class="arc-wrap">

    <!-- 상단 헤더 -->
    <div class="arc-top">
        <div>
            <div class="arc-title">문장 아카이브</div>
            <div class="arc-sub">사람들이 밑줄 그은 문장들</div>
        </div>
        <form method="get" action="archive.php" class="arc-search-wrap">
            <input type="hidden" name="tab"  value="<?= htmlspecialchars($tab) ?>">
            <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
            <input
                type="text"
                name="q"
                value="<?= htmlspecialchars($q) ?>"
                placeholder="문장 검색">
            <i class="bi bi-search si"></i>
        </form>
    </div>

    <!-- 탭 -->
    <div class="arc-tabs">
        <?php $base = fn($t) => "archive.php?tab={$t}&sort=" . urlencode($sort) . ($q ? '&q='.urlencode($q) : ''); ?>
        <a href="<?= $base('all') ?>"  class="arc-tab <?= $tab === 'all'  ? 'active' : '' ?>">전체 순위</a>
        <?php if (isset($_SESSION['user_id'])): ?>
        <a href="<?= $base('mine') ?>" class="arc-tab <?= $tab === 'mine' ? 'active' : '' ?>">내 서랍</a>
        <?php endif; ?>
    </div>

    <div class="arc-layout">

        <!-- 메인 문장 목록 -->
        <div>
            <!-- 정렬 -->
            <div class="sort-row">
                <span class="sort-label">정렬</span>
                <?php
                $sorts = ['likes' => '좋아요순', 'highlights' => '저장순', 'recent' => '최신순'];
                foreach ($sorts as $key => $label):
                    $url = "archive.php?tab={$tab}&sort={$key}" . ($q ? '&q='.urlencode($q) : '');
                ?>
                    <a href="<?= $url ?>" class="sort-btn <?= $sort === $key ? 'active' : '' ?>">
                        <?= $label ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- 문장 카드 -->
            <div class="q-list">
                <?php if (empty($quotes)): ?>
                    <div class="arc-empty">
                        <i class="bi bi-journal-text"></i>
                        <?= $q ? '검색 결과가 없습니다.' : '아직 저장된 문장이 없습니다.' ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($quotes as $i => $quote): ?>
                        <?php
                            $rank        = $i + 1;
                            $is_liked    = !empty($quote['is_liked']);
                            $is_scrapped = !empty($quote['is_scrapped']);
                            $view_url    = "view.php?id={$quote['board_id']}&type=" . urlencode($quote['board_type']);
                        ?>
                        <div class="q-item">
                            <div class="q-rank <?= $rank <= 3 ? 'top' : '' ?>"><?= $rank ?></div>
                            <div class="q-body">
                                <div class="q-text">
                                    <?= highlight($quote['content'], $q) ?>
                                </div>

                                <?php if ($tab === 'mine' && !empty($quote['memo'])): ?>
                                    <div class="q-memo"><?= htmlspecialchars($quote['memo']) ?></div>
                                <?php endif; ?>

                                <div class="q-foot">
                                    <span class="q-src">
                                        <a href="<?= $view_url ?>">
                                            <?= htmlspecialchars($quote['board_title']) ?>
                                        </a>
                                    </span>
                                    <div class="q-actions">
                                        <!-- 좋아요 -->
                                        <?php if (isset($_SESSION['user_id'])): ?>
                                            <button
                                                type="button"
                                                class="act-btn <?= $is_liked ? 'liked' : '' ?>"
                                                data-quote-id="<?= $quote['id'] ?>"
                                                onclick="toggleLike(this)">
                                                <i class="bi <?= $is_liked ? 'bi-heart-fill' : 'bi-heart' ?>"></i>
                                                <span><?= number_format($quote['like_count']) ?></span>
                                            </button>
                                        <?php else: ?>
                                            <span class="act-btn" style="cursor:default;">
                                                <i class="bi bi-heart"></i>
                                                <?= number_format($quote['like_count']) ?>
                                            </span>
                                        <?php endif; ?>

                                        <!-- 저장 수 (읽기 전용) -->
                                        <span class="act-btn" style="cursor:default; pointer-events:none;">
                                            <i class="bi bi-bookmark"></i>
                                            <?= number_format($quote['highlight_count']) ?>
                                        </span>

                                        <!-- 전체탭: 내 서랍 저장/취소 -->
                                        <?php if ($tab === 'all' && isset($_SESSION['user_id'])): ?>
                                            <button
                                                type="button"
                                                class="act-btn <?= $is_scrapped ? 'saved' : '' ?>"
                                                data-quote-id="<?= $quote['id'] ?>"
                                                onclick="toggleScrap(this)">
                                                <i class="bi <?= $is_scrapped ? 'bi-bookmark-fill' : 'bi-bookmark' ?>"></i>
                                                <span><?= $is_scrapped ? '저장됨' : '저장' ?></span>
                                            </button>
                                        <?php endif; ?>

                                        <!-- 내 서랍탭: 삭제 -->
                                        <?php if ($tab === 'mine'): ?>
                                            <button
                                                type="button"
                                                class="act-btn del-btn"
                                                onclick="openRemoveModal(<?= $quote['id'] ?>)">
                                                <i class="bi bi-x"></i> 삭제
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- 사이드바 -->
        <aside class="arc-sidebar">

            <!-- 통계 -->
            <div class="s-card">
                <div class="s-card-title">전체 통계</div>
                <div class="stat-grid">
                    <div class="stat-cell">
                        <div class="stat-label">저장된 문장</div>
                        <div class="stat-val"><?= number_format($stats['total_quotes']) ?></div>
                    </div>
                    <div class="stat-cell">
                        <div class="stat-label">총 좋아요</div>
                        <div class="stat-val"><?= number_format($stats['total_likes']) ?></div>
                    </div>
                    <div class="stat-cell">
                        <div class="stat-label">참여 작가</div>
                        <div class="stat-val"><?= number_format($stats['total_users']) ?></div>
                    </div>
                    <div class="stat-cell">
                        <div class="stat-label">오늘 추가</div>
                        <div class="stat-val">+<?= number_format($stats['today_added']) ?></div>
                    </div>
                </div>
            </div>

            <!-- 이번 주 인기 -->
            <?php if (!empty($hot)): ?>
            <div class="s-card">
                <div class="s-card-title">이번 주 인기</div>
                <div class="hot-list">
                    <?php foreach ($hot as $hi => $h): ?>
                        <div class="hot-item">
                            <div class="hot-num <?= $hi < 3 ? 'top' : '' ?>"><?= $hi + 1 ?></div>
                            <div>
                                <div class="hot-text">
                                    <?= htmlspecialchars(mb_strimwidth($h['content'], 0, 28, '…')) ?>
                                </div>
                                <div class="hot-cnt">♥ <?= number_format($h['like_count']) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- 많이 나온 글 -->
            <?php if (!empty($top_boards)): ?>
            <div class="s-card">
                <div class="s-card-title">많이 나온 글</div>
                <div class="tag-cloud">
                    <?php foreach ($top_boards as $board): ?>
                        <a
                            href="view.php?id=<?= $board['id'] ?>&type=<?= urlencode($board['board_type']) ?>"
                            class="tag-item">
                            <?= htmlspecialchars($board['title']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

        </aside>
    </div>
</div>

<!-- 내 서랍 삭제 모달 -->
<div class="arc-modal-bg" id="removeModal">
    <div class="arc-modal">
        <div class="arc-modal-title">서랍에서 삭제할까요?</div>
        <div class="arc-modal-desc">내 서랍에서만 사라지며, 아카이브 순위에는 영향이 없습니다.</div>
        <div class="arc-modal-btns">
            <button class="arc-mbtn" onclick="closeRemoveModal()">취소</button>
            <button class="arc-mbtn danger" onclick="confirmRemove()">삭제</button>
        </div>
    </div>
</div>

<script>
/* ── 좋아요 토글 ── */
function toggleLike(btn) {
    var quoteId = btn.dataset.quoteId;
    fetch('quote_like.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ quote_id: quoteId })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (!data.success) return;
        var icon = btn.querySelector('i');
        var cnt  = btn.querySelector('span');
        if (data.liked) {
            btn.classList.add('liked');
            icon.className = 'bi bi-heart-fill';
        } else {
            btn.classList.remove('liked');
            icon.className = 'bi bi-heart';
        }
        cnt.textContent = data.like_count.toLocaleString();
    });
}

/* ── 서랍 저장/취소 토글 ── */
function toggleScrap(btn) {
    var quoteId = btn.dataset.quoteId;
    fetch('quote_scrap.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ quote_id: quoteId })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (!data.success) return;
        var icon = btn.querySelector('i');
        var txt  = btn.querySelector('span');
        if (data.scrapped) {
            btn.classList.add('saved');
            icon.className  = 'bi bi-bookmark-fill';
            txt.textContent = '저장됨';
        } else {
            btn.classList.remove('saved');
            icon.className  = 'bi bi-bookmark';
            txt.textContent = '저장';
        }
    });
}

/* ── 내 서랍 삭제 모달 ── */
var _removeTargetId = null;

function openRemoveModal(quoteId) {
    _removeTargetId = quoteId;
    document.getElementById('removeModal').classList.add('show');
}
function closeRemoveModal() {
    _removeTargetId = null;
    document.getElementById('removeModal').classList.remove('show');
}
function confirmRemove() {
    if (_removeTargetId === null) return;
    fetch('quote_scrap.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ quote_id: _removeTargetId, action: 'delete' })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) location.reload();
    });
    closeRemoveModal();
}

/* 배경 클릭 시 닫기 */
document.getElementById('removeModal').addEventListener('click', function(e) {
    if (e.target === this) closeRemoveModal();
});
</script>

<?php include '../includes/footer.php'; ?>
<?php
// /board/archive.php
require_once '../includes/db.php';

// 탭: all(전체 순위) | mine(내 서랍)
$tab  = $_GET['tab']  ?? 'all';
$sort = $_GET['sort'] ?? 'likes';
$q    = trim($_GET['q'] ?? '');

$allowed_tabs  = ['all', 'mine'];
$allowed_sorts = ['likes', 'highlights', 'recent'];

if (!in_array($tab,  $allowed_tabs))  $tab  = 'all';
if (!in_array($sort, $allowed_sorts)) $sort = 'likes';

// 내 서랍은 로그인 필요
if ($tab === 'mine' && !isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

try {
    $params = [];

    if ($tab === 'mine') {
        // 내 서랍: 내가 스크랩한 문장 (삭제 안 한 것만)
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
                b.title AS board_title,
                q.board_id,
                CASE WHEN ql.id IS NOT NULL THEN 1 ELSE 0 END AS is_liked
            FROM quote_scraps qs
            JOIN quotes q  ON qs.quote_id = q.id
            JOIN boards b  ON q.board_id  = b.id
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
        // 전체 순위
        $order = match($sort) {
            'highlights' => 'q.highlight_count DESC',
            'recent'     => 'q.created_at DESC',
            default      => 'q.like_count DESC',
        };

        $sql = "
            SELECT
                q.id,
                q.content,
                q.like_count,
                q.highlight_count,
                q.created_at,
                b.title AS board_title,
                q.board_id
                " . (isset($_SESSION['user_id']) ? ",
                CASE WHEN ql.id IS NOT NULL THEN 1 ELSE 0 END AS is_liked,
                CASE WHEN qs.id IS NOT NULL THEN 1 ELSE 0 END AS is_scrapped
                " : ", 0 AS is_liked, 0 AS is_scrapped") . "
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
            $sql .= " WHERE q.content LIKE :q";
            $params[':q'] = '%' . $q . '%';
        }

        $sql .= " ORDER BY {$order} LIMIT 100";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $quotes = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("오류: " . $e->getMessage());
}

include '../includes/header.php';
?>

<style>
/* ── 레이아웃 ── */
.arc-wrap {
    max-width: 860px;
    margin: 0 auto;
    padding: 0 20px 80px;
    margin-top: 110px;
}

/* ── 헤더 ── */
.arc-head {
    border-bottom: 2px solid #1a1a1a;
    padding-bottom: 18px;
    margin-bottom: 0;
}
.arc-head-title {
    font-family: 'Noto Serif KR', serif;
    font-size: 24px;
    font-weight: 500;
    color: #1a1a1a;
}
.arc-head-sub {
    font-size: 13px;
    color: #aaa;
    margin-top: 4px;
}

/* ── 탭 ── */
.arc-tabs {
    display: flex;
    gap: 0;
    border-bottom: 1px solid #eee;
    margin-bottom: 24px;
}
.arc-tab {
    padding: 14px 20px;
    font-size: 14px;
    color: #aaa;
    cursor: pointer;
    border: none;
    background: none;
    border-bottom: 2px solid transparent;
    margin-bottom: -1px;
    font-family: 'Noto Serif KR', serif;
    transition: color .15s;
}
.arc-tab.active {
    color: #1a1a1a;
    border-bottom-color: #1a1a1a;
}
.arc-tab:hover { color: #555; }

/* ── 툴바 ── */
.arc-toolbar {
    display: flex;
    gap: 10px;
    align-items: center;
    margin-bottom: 28px;
    flex-wrap: wrap;
}
.arc-search {
    position: relative;
    flex: 1;
    min-width: 200px;
}
.arc-search input {
    width: 100%;
    border: 1px solid #ddd;
    border-radius: 999px;
    padding: 9px 38px 9px 16px;
    font-size: 13px;
    color: #1a1a1a;
    background: #fff;
    outline: none;
    font-family: sans-serif;
    transition: border-color .15s;
}
.arc-search input:focus { border-color: #1a1a1a; }
.arc-search-icon {
    position: absolute;
    right: 13px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 15px;
    color: #ccc;
}
.arc-sorts {
    display: flex;
    gap: 6px;
}
.sort-btn {
    border: 1px solid #ddd;
    background: #fff;
    border-radius: 999px;
    padding: 8px 15px;
    font-size: 12px;
    color: #777;
    cursor: pointer;
    text-decoration: none;
    transition: all .15s;
    white-space: nowrap;
}
.sort-btn:hover { border-color: #999; color: #333; }
.sort-btn.active { background: #1a1a1a; color: #fff; border-color: #1a1a1a; }

/* ── 문장 카드 ── */
.quote-list { display: flex; flex-direction: column; }

.quote-item {
    display: flex;
    gap: 20px;
    padding: 22px 0;
    border-bottom: 1px solid #f0f0f0;
    align-items: flex-start;
    transition: background .15s;
}
.quote-item:last-child { border-bottom: none; }

.quote-rank {
    font-size: 11px;
    font-weight: 500;
    color: #ccc;
    min-width: 22px;
    text-align: right;
    padding-top: 4px;
    font-variant-numeric: tabular-nums;
}
.quote-rank.top3 { color: #1a1a1a; font-size: 13px; }

.quote-body { flex: 1; min-width: 0; }

.quote-text {
    font-family: 'Noto Serif KR', serif;
    font-size: 17px;
    font-weight: 400;
    color: #1a1a1a;
    line-height: 1.8;
    margin-bottom: 12px;
    word-break: keep-all;
}
.quote-text mark {
    background: #f0ede8;
    color: #1a1a1a;
    border-radius: 2px;
    padding: 0 2px;
}

.quote-meta {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
.quote-source {
    font-size: 12px;
    color: #bbb;
}
.quote-source a {
    color: #999;
    text-decoration: none;
}
.quote-source a:hover { color: #1a1a1a; }

.quote-actions { display: flex; gap: 6px; align-items: center; }

.action-btn {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    border: 1px solid #e8e8e8;
    background: #fff;
    border-radius: 999px;
    padding: 5px 12px;
    font-size: 12px;
    color: #999;
    cursor: pointer;
    transition: all .15s;
    text-decoration: none;
    font-family: sans-serif;
}
.action-btn i { font-size: 13px; }
.action-btn:hover { border-color: #bbb; color: #555; }
.action-btn.liked { border-color: #1a1a1a; color: #1a1a1a; }
.action-btn.scrapped { border-color: #1a1a1a; color: #1a1a1a; }

/* 내 서랍 메모 */
.quote-memo {
    font-size: 12px;
    color: #aaa;
    margin-top: 8px;
    font-style: italic;
    line-height: 1.5;
}
.quote-memo::before { content: '" '; }
.quote-memo::after  { content: ' "'; }

/* ── 빈 상태 ── */
.arc-empty {
    text-align: center;
    padding: 60px 0;
    color: #ccc;
}
.arc-empty i { font-size: 36px; display: block; margin-bottom: 12px; }
.arc-empty p { font-size: 14px; }

/* ── 모바일 ── */
@media (max-width: 575px) {
    .arc-wrap { margin-top: 80px; padding: 0 15px 60px; }
    .arc-head-title { font-size: 20px; }
    .quote-text { font-size: 15px; }
    .arc-tab { padding: 12px 14px; font-size: 13px; }
    .arc-sorts { flex-wrap: wrap; }
}
</style>

<div class="arc-wrap">

    <!-- 헤더 -->
    <div class="arc-head">
        <div class="arc-head-title">문장 아카이브</div>
        <div class="arc-head-sub">사람들이 밑줄 그은 문장들</div>
    </div>

    <!-- 탭 -->
    <div class="arc-tabs">
        <a href="archive.php?tab=all&sort=<?= htmlspecialchars($sort) ?><?= $q ? '&q='.urlencode($q) : '' ?>"
           class="arc-tab <?= $tab === 'all' ? 'active' : '' ?>">
            전체 순위
        </a>
        <?php if (isset($_SESSION['user_id'])): ?>
        <a href="archive.php?tab=mine&sort=<?= htmlspecialchars($sort) ?><?= $q ? '&q='.urlencode($q) : '' ?>"
           class="arc-tab <?= $tab === 'mine' ? 'active' : '' ?>">
            내 서랍
        </a>
        <?php endif; ?>
    </div>

    <!-- 툴바 -->
    <div class="arc-toolbar">
        <form method="get" action="archive.php" class="arc-search">
            <input type="hidden" name="tab"  value="<?= htmlspecialchars($tab) ?>">
            <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
            <input
                type="text"
                name="q"
                value="<?= htmlspecialchars($q) ?>"
                placeholder="문장 검색">
            <i class="bi bi-search arc-search-icon"></i>
        </form>

        <div class="arc-sorts">
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
    </div>

    <!-- 문장 목록 -->
    <div class="quote-list">
        <?php if (empty($quotes)): ?>
            <div class="arc-empty">
                <i class="bi bi-journal-text"></i>
                <p><?= $q ? '검색 결과가 없습니다.' : '아직 저장된 문장이 없습니다.' ?></p>
            </div>

        <?php else: ?>
            <?php foreach ($quotes as $i => $quote): ?>
                <?php $rank = $i + 1; ?>
                <div class="quote-item">
                    <!-- 순위 -->
                    <div class="quote-rank <?= $rank <= 3 ? 'top3' : '' ?>">
                        <?= $rank ?>
                    </div>

                    <div class="quote-body">
                        <!-- 문장 본문 -->
                        <div class="quote-text">
                            <?php if ($q !== ''): ?>
                                <?= str_replace(
                                    htmlspecialchars($q),
                                    '<mark>' . htmlspecialchars($q) . '</mark>',
                                    htmlspecialchars($quote['content'])
                                ) ?>
                            <?php else: ?>
                                <?= htmlspecialchars($quote['content']) ?>
                            <?php endif; ?>
                        </div>

                        <!-- 개인 메모 (내 서랍) -->
                        <?php if ($tab === 'mine' && !empty($quote['memo'])): ?>
                            <div class="quote-memo"><?= htmlspecialchars($quote['memo']) ?></div>
                        <?php endif; ?>

                        <!-- 메타 -->
                        <div class="quote-meta">
                            <span class="quote-source">
                                <a href="view.php?id=<?= $quote['board_id'] ?>&type=anonymity">
                                    <?= htmlspecialchars($quote['board_title']) ?>
                                </a>
                            </span>

                            <div class="quote-actions">
                                <!-- 좋아요 버튼 -->
                                <?php if (isset($_SESSION['user_id'])): ?>
                                    <button
                                        type="button"
                                        class="action-btn <?= $quote['is_liked'] ? 'liked' : '' ?>"
                                        data-quote-id="<?= $quote['id'] ?>"
                                        onclick="toggleLike(this)">
                                        <i class="bi <?= $quote['is_liked'] ? 'bi-heart-fill' : 'bi-heart' ?>"></i>
                                        <span><?= number_format($quote['like_count']) ?></span>
                                    </button>
                                <?php else: ?>
                                    <span class="action-btn">
                                        <i class="bi bi-heart"></i>
                                        <span><?= number_format($quote['like_count']) ?></span>
                                    </span>
                                <?php endif; ?>

                                <!-- 저장 수 -->
                                <span class="action-btn" style="cursor:default; pointer-events:none;">
                                    <i class="bi bi-bookmark"></i>
                                    <span><?= number_format($quote['highlight_count']) ?></span>
                                </span>

                                <!-- 내 서랍: 삭제 버튼 -->
                                <?php if ($tab === 'mine'): ?>
                                    <button
                                        type="button"
                                        class="action-btn"
                                        onclick="openRemoveModal(<?= $quote['id'] ?>)"
                                        style="color:#ccc;">
                                        <i class="bi bi-x"></i> 삭제
                                    </button>
                                <?php endif; ?>

                                <!-- 전체: 내 서랍 저장/취소 -->
                                <?php if ($tab === 'all' && isset($_SESSION['user_id'])): ?>
                                    <button
                                        type="button"
                                        class="action-btn <?= $quote['is_scrapped'] ? 'scrapped' : '' ?>"
                                        data-quote-id="<?= $quote['id'] ?>"
                                        onclick="toggleScrap(this)">
                                        <i class="bi <?= $quote['is_scrapped'] ? 'bi-bookmark-fill' : 'bi-bookmark' ?>"></i>
                                        <span><?= $quote['is_scrapped'] ? '저장됨' : '저장' ?></span>
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

<!-- 내 서랍 삭제 모달 -->
<div class="modal-backdrop-custom" id="removeModal">
    <div class="modal-box">
        <div class="modal-box-title">서랍에서 삭제할까요?</div>
        <div class="modal-box-desc">내 서랍에서만 사라지며, 아카이브 순위에는 영향이 없습니다.</div>
        <div class="modal-box-actions">
            <button class="modal-btn" onclick="closeRemoveModal()">취소</button>
            <button class="modal-btn modal-btn-danger" onclick="confirmRemove()">삭제</button>
        </div>
    </div>
</div>

<style>
.modal-backdrop-custom {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.3);
    z-index: 1050;
    align-items: center;
    justify-content: center;
}
.modal-backdrop-custom.show { display: flex; }
.modal-box {
    background: #fff;
    border-radius: 12px;
    padding: 28px 28px 20px;
    width: 320px;
    max-width: 90vw;
    box-shadow: 0 8px 30px rgba(0,0,0,0.1);
}
.modal-box-title { font-size: 15px; font-weight: 500; color: #1a1a1a; margin-bottom: 8px; }
.modal-box-desc  { font-size: 13px; color: #999; line-height: 1.6; margin-bottom: 20px; }
.modal-box-actions { display: flex; justify-content: flex-end; gap: 8px; }
.modal-btn {
    font-size: 13px; padding: 7px 16px; border-radius: 6px;
    cursor: pointer; border: 1px solid #ddd; background: #fff; color: #333;
}
.modal-btn:hover { background: #f5f5f5; }
.modal-btn-danger { background: #fff0f0; border-color: #f5c6c6; color: #dc3545; font-weight: 500; }
.modal-btn-danger:hover { background: #ffe0e0; }
</style>

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
            btn.classList.add('scrapped');
            icon.className  = 'bi bi-bookmark-fill';
            txt.textContent = '저장됨';
        } else {
            btn.classList.remove('scrapped');
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
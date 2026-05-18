<?php
// /mypage.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'includes/auth_check.php';
require_login(); 

$user_id = (int)$_SESSION['user_id'];
$role    = current_role();

try {
    // 유저 기본 정보
    $stmt = $pdo->prepare("
        SELECT id, user_id, nickname, email, phone, created_at
        FROM users
        WHERE id = :id AND deleted_at IS NULL
    ");
    $stmt->execute([':id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        session_destroy();
        header('Location: /login.php');
        exit;
    }

    // 작가 승인일 (approved 상태 중 가장 최근)
    $writer_since = null;
    if ($role === 'writer' || $role === 'admin') {
        $stmt = $pdo->prepare("
            SELECT processed_at FROM writer_applications
            WHERE user_id = :uid AND status = 'approved'
            ORDER BY processed_at DESC LIMIT 1
        ");
        $stmt->execute([':uid' => $user_id]);
        $writer_since = $stmt->fetchColumn();
    }

    // 작가 신청 상태 (일반회원인 경우)
    $apply_status = null;
    if ($role === 'user' || $role === 'guest') {
        $stmt = $pdo->prepare("
            SELECT status FROM writer_applications
            WHERE user_id = :uid
            ORDER BY created_at DESC LIMIT 1
        ");
        $stmt->execute([':uid' => $user_id]);
        $apply_status = $stmt->fetchColumn() ?: null;
    }

    // 통계 카운트
    $stmt = $pdo->prepare("
        SELECT
            (SELECT COUNT(*) FROM boards
             WHERE writer_id = :uid1 AND hidden_yn = 'N') AS post_count,
            (SELECT COUNT(*) FROM comments
             WHERE user_id = :uid2 AND deleted_at IS NULL AND hidden_yn = 'N') AS comment_count,
            (SELECT COUNT(*) FROM quote_scraps
             WHERE user_id = :uid3 AND deleted_at IS NULL) AS scrap_count,
            (SELECT COUNT(*) FROM qna
             WHERE user_id = :uid4 AND deleted_at IS NULL) AS qna_count
    ");
    $stmt->execute([
        ':uid1' => $user_id,
        ':uid2' => $user_id,
        ':uid3' => $user_id,
        ':uid4' => $user_id,
    ]);
    $counts = $stmt->fetch(PDO::FETCH_ASSOC);

    // 최근 작성 글 (5개)
    $stmt = $pdo->prepare("
        SELECT id, title, board_type, view_count, created_at
        FROM boards
        WHERE writer_id = :uid AND hidden_yn = 'N'
        ORDER BY created_at DESC LIMIT 5
    ");
    $stmt->execute([':uid' => $user_id]);
    $recent_posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 내 서랍 (5개)
    $stmt = $pdo->prepare("
        SELECT q.content, q.board_id, b.title AS board_title, qs.created_at
        FROM quote_scraps qs
        JOIN quotes q ON qs.quote_id = q.id
        JOIN boards b ON q.board_id  = b.id
        WHERE qs.user_id = :uid AND qs.deleted_at IS NULL
        ORDER BY qs.created_at DESC LIMIT 5
    ");
    $stmt->execute([':uid' => $user_id]);
    $scraps = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 내 문의 목록
    $stmt = $pdo->prepare("
        SELECT id, title, status, private_yn, view_count, created_at, answered_at
        FROM qna
        WHERE user_id = :uid AND deleted_at IS NULL
        ORDER BY created_at DESC LIMIT 20
    ");
    $stmt->execute([':uid' => $user_id]);
    $qna_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 문의 탭별 카운트
    $qna_counts = [
        'all'      => count($qna_list),
        'pending'  => count(array_filter($qna_list, fn($q) => $q['status'] === 'pending')),
        'answered' => count(array_filter($qna_list, fn($q) => $q['status'] === 'answered')),
    ];

} catch (PDOException $e) {
    die("오류: " . $e->getMessage());
}

$board_type_label = ['anonymity' => '익명글', 'writing' => '작가만의 방'];

include 'includes/header.php';
?>

<style>
/* ── 공통 래퍼 ── */
.mp-wrap {
    width: 100%;
    max-width: 1100px;
    margin: 110px auto 80px;
    padding: 0 24px;
    min-height: 60vh;
}

/* ── 상단 헤더 ── */
.mp-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    border-bottom: 2px solid #1a1a1a;
    padding-bottom: 18px;
}
.mp-title {
    font-family: 'Noto Serif KR', serif;
    font-size: 26px;
    font-weight: 500;
    color: #1a1a1a;
    letter-spacing: -.4px;
}
.mp-sub {
    font-size: 13px;
    color: #aaa;
    margin-top: 4px;
}

/* ── 탭 ── */
.mp-tabs {
    display: flex;
    border-bottom: 1px solid #eee;
    margin-bottom: 34px;
}
.mp-tab {
    padding: 14px 22px;
    font-size: 15px;
    color: #bbb;
    cursor: pointer;
    border: none;
    background: none;
    border-bottom: 2px solid transparent;
    margin-bottom: -1px;
    font-family: 'Noto Serif KR', serif;
    transition: color .15s;
}
.mp-tab.active { color: #1a1a1a; border-bottom-color: #1a1a1a; }
.mp-tab:hover  { color: #555; }

/* ── 레이아웃 ── */
.mp-layout {
    display: grid;
    grid-template-columns: 260px 1fr;
    gap: 48px;
    align-items: start;
}

/* ── 사이드바 (프로필 카드) ── */
.s-card {
    background: #fafafa;
    border: 1px solid #f0f0f0;
    border-radius: 12px;
    margin-bottom: 20px;
    overflow: hidden; 
}

/* 상단 프로필 영역 */
.profile-section {
    padding: 32px 20px 24px;
    text-align: center;
}
.mp-avatar {
    width: 76px;
    height: 76px;
    border-radius: 50%;
    background: #fff;
    border: 1px solid #eee;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 26px;
    color: #1a1a1a;
    margin: 0 auto 16px;
    font-family: 'Noto Serif KR', serif;
}
.mp-name { font-size: 16px; font-weight: 500; color: #1a1a1a; margin-bottom: 4px; }
.mp-uid  { font-size: 13px; color: #aaa; margin-bottom: 12px; }
.mp-badge {
    display: inline-block;
    font-size: 11px;
    padding: 4px 12px;
    border-radius: 999px;
    border: 1px solid #1a1a1a;
    color: #1a1a1a;
    background: #fff;
}
.mp-badge.user { border-color: #ddd; color: #888; }
.mp-badge.admin { background: #1a1a1a; color: #fff; }

/* 프로필과 통계 사이의 가로 구분선 */
.s-card-divider {
    height: 1px;
    background: #eee;
    margin: 0 20px;
}

/* 하단 통계 영역 */
.stat-section {
    padding: 24px 20px 28px;
}
.stat-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px; 
}
.stat-cell {
    background: #fff;
    border: 1px solid #eee;
    border-radius: 10px;
    padding: 26px 10px; 
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    cursor: pointer; /* 💡 마우스 커서를 손가락 모양으로 변경 */
    transition: transform 0.2s, box-shadow 0.2s;
}
.stat-cell:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.04);
}
.stat-label { font-size: 12px; color: #aaa; margin-bottom: 8px; font-family: sans-serif;}
.stat-val   { font-size: 22px; font-weight: 500; color: #1a1a1a; }

/* ── 메인 패널 ── */
.mp-panel { display: none; animation: fadeIn 0.3s ease; }
.mp-panel.active { display: block; }

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(5px); }
    to { opacity: 1; transform: translateY(0); }
}

.sec-hd {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}
.sec-title {
    font-family: 'Noto Serif KR', serif;
    font-size: 20px;
    font-weight: 500;
    color: #1a1a1a;
}
.sec-btn {
    border: 1px solid #ddd;
    background: #fff;
    border-radius: 999px;
    padding: 6px 16px;
    font-size: 12px;
    color: #666;
    cursor: pointer;
    text-decoration: none;
    transition: all .15s;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.sec-btn:hover { border-color: #1a1a1a; color: #1a1a1a; }

/* 기본 정보 테이블 */
.info-table { width: 100%; border-collapse: collapse; margin-bottom: 34px; }
.info-table td { padding: 16px 10px; border-bottom: 1px solid #f5f5f5; font-size: 14px; color: #333; }
.info-table td:first-child { color: #aaa; width: 120px; }
.info-table tr:last-child td { border-bottom: none; }

.apply-link { color: #666; text-decoration: underline; font-size: 13px; margin-left: 10px; }
.apply-link:hover { color: #1a1a1a; }

/* 리스트 아이템 공통 (게시글, 문의) */
.list-item {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 16px 0;
    border-bottom: 1px solid #f0f0f0;
}
.list-item:last-child { border-bottom: none; }
.list-badge {
    font-size: 11px;
    color: #bbb;
    border: 1px solid #eee;
    border-radius: 999px;
    padding: 3px 10px;
    white-space: nowrap;
}
.list-badge.dark { background: #1a1a1a; color: #fff; border-color: #1a1a1a; }
.list-title {
    font-size: 15px;
    color: #1a1a1a;
    text-decoration: none;
    flex: 1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.list-title:hover { text-decoration: underline; }
.list-date { font-size: 13px; color: #bbb; white-space: nowrap; }

/* 내 서랍 문장 스타일 */
.scrap-item {
    padding: 20px 0;
    border-bottom: 1px solid #f0f0f0;
}
.scrap-item:last-child { border-bottom: none; }
.scrap-text {
    font-family: 'Noto Serif KR', serif;
    font-size: 16px;
    color: #1a1a1a;
    line-height: 1.8;
    margin-bottom: 10px;
}
.scrap-src { font-size: 13px; color: #bbb; text-decoration: none; }
.scrap-src:hover { color: #1a1a1a; text-decoration: underline; }

/* 빈 상태 */
.mp-empty {
    text-align: center;
    padding: 60px 0;
    color: #ccc;
    font-size: 14px;
}
.mp-empty i { font-size: 32px; display: block; margin-bottom: 14px; color: #e0e0e0; }

/* 문의 탭 정렬 버튼 */
.sort-row { display: flex; gap: 6px; margin-bottom: 20px; }
.sort-btn {
    border: 1px solid #ddd;
    background: #fff;
    border-radius: 999px;
    padding: 6px 14px;
    font-size: 12px;
    color: #888;
    cursor: pointer;
    transition: all .15s;
}
.sort-btn.active { background: #1a1a1a; color: #fff; border-color: #1a1a1a; }

/* 반응형 */
@media (max-width: 860px) {
    .mp-layout { grid-template-columns: 1fr; gap: 0; }
    .profile-section { display: flex; align-items: center; text-align: left; gap: 24px; padding: 24px 28px; }
    .mp-avatar { margin: 0; flex-shrink: 0; }
    .stat-section { padding: 0 28px 28px; }
    .s-card-divider { margin: 0 28px 24px; }
}
@media (max-width: 600px) {
    .mp-wrap { margin-top: 80px; padding: 0 15px; }
    .mp-top { flex-direction: column; align-items: flex-start; gap: 10px; }
    .profile-section { flex-direction: column; text-align: center; padding: 28px 20px 20px; }
    .stat-section { padding: 0 20px 24px; }
    .s-card-divider { margin: 0 20px 20px; }
    .list-item { gap: 10px; }
    .list-badge { display: none; }
}
</style>

<div class="mp-wrap">

    <div class="mp-top">
        <div>
            <div class="mp-title">마이페이지</div>
            <div class="mp-sub">내 정보와 활동을 관리하세요</div>
        </div>
    </div>

    <div class="mp-tabs">
        <button class="mp-tab active" data-panel="info">기본 정보</button>
        <button class="mp-tab" data-panel="activity">나의 활동</button>
        <button class="mp-tab" data-panel="scrap">내 서랍</button>
        <button class="mp-tab" data-panel="qna">문의 내역</button>
    </div>

    <div class="mp-layout">

        <aside>
            <div class="s-card">
                
                <div class="profile-section">
                    <div class="mp-avatar">
                        <?= mb_substr($user['nickname'], 0, 1) ?>
                    </div>
                    <div class="profile-info">
                        <div class="mp-name"><?= htmlspecialchars($user['nickname']) ?></div>
                        <div class="mp-uid">@<?= htmlspecialchars($user['user_id']) ?></div>
                        
                        <?php if ($role === 'writer'): ?>
                            <span class="mp-badge">✦ 작가</span>
                        <?php elseif ($role === 'admin'): ?>
                                <span class="mp-badge admin">관리자</span>
                        <?php else: ?>
                            <span class="mp-badge user">일반회원</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="s-card-divider"></div>

                <div class="stat-section">
                    <div class="stat-grid">
                        <div class="stat-cell" onclick="switchTab('activity')">
                            <div class="stat-label">작성 글</div>
                            <div class="stat-val"><?= number_format($counts['post_count']) ?></div>
                        </div>
                        <div class="stat-cell" onclick="switchTab('activity')">
                            <div class="stat-label">댓글</div>
                            <div class="stat-val"><?= number_format($counts['comment_count']) ?></div>
                        </div>
                        <div class="stat-cell" onclick="switchTab('scrap')">
                            <div class="stat-label">내 서랍</div>
                            <div class="stat-val"><?= number_format($counts['scrap_count']) ?></div>
                        </div>
                        <div class="stat-cell" onclick="switchTab('qna')">
                            <div class="stat-label">문의</div>
                            <div class="stat-val"><?= number_format($counts['qna_count']) ?></div>
                        </div>
                    </div>
                </div>

            </div>
        </aside>

        <div>

            <div class="mp-panel active" id="panel-info">
                <div class="sec-hd">
                    <span class="sec-title">계정 정보</span>
                    <a href="/mypage_edit.php" class="sec-btn">
                        <i class="bi bi-pencil"></i> 수정
                    </a>
                </div>

                <table class="info-table">
                    <tr>
                        <td>아이디</td>
                        <td><?= htmlspecialchars($user['user_id']) ?></td>
                    </tr>
                    <tr>
                        <td>닉네임</td>
                        <td><?= htmlspecialchars($user['nickname']) ?></td>
                    </tr>
                    <tr>
                        <td>이메일</td>
                        <td><?= htmlspecialchars($user['email']) ?></td>
                    </tr>
                    <tr>
                        <td>가입일</td>
                        <td><?= date('Y.m.d', strtotime($user['created_at'])) ?></td>
                    </tr>
                    <tr>
                        <td>회원 등급</td>
                        <td>
                            <?php if ($role === 'admin'): ?>
                                <strong>관리자</strong>
                            <?php elseif ($role === 'writer'): ?>
                                <strong>✦ 작가</strong> 
                                <span style="color:#aaa; font-size:12px; margin-left:6px;">(<?= date('Y.m.d', strtotime($writer_since)) ?> 승인)</span>
                            <?php elseif ($apply_status === 'pending'): ?>
                                일반회원 <span style="color:#f5a623; font-size:13px; margin-left:10px;"><i class="bi bi-hourglass-split"></i> 작가 심사 중</span>
                            <?php else: ?>
                                일반회원 <a href="/writer_apply.php" class="apply-link">작가 신청하기</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="mp-panel" id="panel-activity">
                <div class="sec-hd">
                    <span class="sec-title">최근 작성한 글</span>
                </div>

                <?php if (empty($recent_posts)): ?>
                    <div class="mp-empty">
                        <i class="bi bi-journal-x"></i>
                        아직 작성한 글이 없습니다.
                    </div>
                <?php else: ?>
                    <div>
                        <?php foreach ($recent_posts as $post): ?>
                            <div class="list-item">
                                <span class="list-badge">
                                    <?= $board_type_label[$post['board_type']] ?? $post['board_type'] ?>
                                </span>
                                <a href="/board/view.php?id=<?= $post['id'] ?>&type=<?= urlencode($post['board_type']) ?>" class="list-title">
                                    <?= htmlspecialchars($post['title']) ?>
                                </a>
                                <span class="list-date">
                                    <?= date('Y.m.d', strtotime($post['created_at'])) ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="mp-panel" id="panel-scrap">
                <div class="sec-hd">
                    <span class="sec-title">최근 저장한 문장</span>
                </div>

                <?php if (empty($scraps)): ?>
                    <div class="mp-empty">
                        <i class="bi bi-bookmark-x"></i>
                        아직 서랍에 저장한 문장이 없습니다.
                    </div>
                <?php else: ?>
                    <div>
                        <?php foreach ($scraps as $scrap): ?>
                            <div class="scrap-item">
                                <div class="scrap-text">
                                    <?= htmlspecialchars($scrap['content']) ?>
                                </div>
                                <a href="/board/view.php?id=<?= $scrap['board_id'] ?>" class="scrap-src">
                                    <?= htmlspecialchars($scrap['board_title']) ?> <i class="bi bi-chevron-right"></i>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="mp-panel" id="panel-qna">
                <div class="sec-hd">
                    <span class="sec-title">1:1 문의 내역</span>
                    <a href="/qna/qna_write.php" class="sec-btn">
                        <i class="bi bi-pencil"></i> 문의 작성
                    </a>
                </div>

                <div class="sort-row">
                    <button class="sort-btn active" data-filter="all">전체 (<?= $qna_counts['all'] ?>)</button>
                    <button class="sort-btn" data-filter="pending">답변대기 (<?= $qna_counts['pending'] ?>)</button>
                    <button class="sort-btn" data-filter="answered">답변완료 (<?= $qna_counts['answered'] ?>)</button>
                </div>

                <?php if (empty($qna_list)): ?>
                    <div class="mp-empty">
                        <i class="bi bi-chat-square-text"></i>
                        문의 내역이 없습니다.
                    </div>
                <?php else: ?>
                    <div id="qnaList">
                        <?php foreach ($qna_list as $q): ?>
                            <div class="list-item" data-status="<?= htmlspecialchars($q['status']) ?>">
                                <span class="list-badge <?= $q['status'] === 'answered' ? 'dark' : '' ?>">
                                    <?= $q['status'] === 'answered' ? '답변완료' : '답변대기' ?>
                                </span>
                                <a href="/qna/qna_view.php?id=<?= $q['id'] ?>" class="list-title">
                                    <?php if ($q['private_yn'] === 'Y'): ?>
                                        <i class="bi bi-lock me-1" style="color:#bbb;"></i>
                                    <?php endif; ?>
                                    <?= htmlspecialchars($q['title']) ?>
                                </a>
                                <span class="list-date">
                                    <?= date('Y.m.d', strtotime($q['created_at'])) ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<script>
/* ── 탭 전환 로직 ── */
const tabs = document.querySelectorAll('.mp-tab');
const panels = document.querySelectorAll('.mp-panel');

function switchTab(panelId) {
    // 탭 액티브 변경
    tabs.forEach(t => t.classList.toggle('active', t.dataset.panel === panelId));
    // 패널 노출 변경
    panels.forEach(p => p.classList.toggle('active', p.id === 'panel-' + panelId));
    // URL 업데이트 (새로고침 시 유지)
    history.replaceState(null, '', '#' + panelId);
}

tabs.forEach(tab => {
    tab.addEventListener('click', () => switchTab(tab.dataset.panel));
});

// 초기 로딩 시 해시에 맞는 탭 열기
const hash = location.hash.replace('#', '');
if (['info', 'activity', 'scrap', 'qna'].includes(hash)) {
    switchTab(hash);
}

/* ── 문의 필터 로직 ── */
document.querySelectorAll('.sort-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.sort-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');

        const filter = this.dataset.filter;
        document.querySelectorAll('#qnaList .list-item').forEach(item => {
            item.style.display = (filter === 'all' || item.dataset.status === filter) ? 'flex' : 'none';
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>
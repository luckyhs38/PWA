<?php
// /admin/index.php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../includes/db.php';
require_once '../includes/auth_check.php';

// 1. 관리자 권한 필수 체크 (아니면 튕겨냄)
require_admin();

try {
    // 2. 전체 통계 데이터 조회
    // 전체 가입자 수
    $users_cnt = $pdo->query("SELECT COUNT(*) FROM users WHERE deleted_at IS NULL")->fetchColumn();
    
    // 전체 게시글 수 (익명글 + 작가글)
    $boards_cnt = $pdo->query("SELECT COUNT(*) FROM boards WHERE hidden_yn = 'N'")->fetchColumn();

    // 3. 처리해야 할 '대기 중' 업무 카운트
    // 대기 중인 작가 신청
    $pending_writer_cnt = $pdo->query("SELECT COUNT(*) FROM writer_applications WHERE status = 'pending'")->fetchColumn();
    
    // 답변 대기 중인 QnA
    $pending_qna_cnt = $pdo->query("SELECT COUNT(*) FROM qna WHERE status = 'pending' AND deleted_at IS NULL")->fetchColumn();

    // 4. 최근 들어온 작가 신청 (최대 5건)
    $recent_writers = $pdo->query("
        SELECT wa.id, u.nickname, wa.created_at 
        FROM writer_applications wa
        JOIN users u ON wa.user_id = u.id
        WHERE wa.status = 'pending'
        ORDER BY wa.created_at DESC LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

    // 5. 최근 들어온 미답변 QnA (최대 5건)
    $recent_qnas = $pdo->query("
        SELECT id, title, created_at 
        FROM qna 
        WHERE status = 'pending' AND deleted_at IS NULL
        ORDER BY created_at DESC LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("오류: " . $e->getMessage());
}

include '../includes/header.php';
?>

<style>
/* ── 공통 래퍼 ── */
.admin-wrap {
    width: 100%;
    max-width: 1100px;
    margin: 110px auto 80px;
    padding: 0 24px;
    flex: 1; /* 푸터 밀어내기 */
}

/* ── 상단 헤더 ── */
.admin-top {
    border-bottom: 2px solid #1a1a1a;
    padding-bottom: 18px;
    margin-bottom: 30px;
}
.admin-title {
    font-family: 'Noto Serif KR', serif;
    font-size: 26px;
    font-weight: 500;
    color: #1a1a1a;
    letter-spacing: -.4px;
}
.admin-sub { font-size: 13px; color: #aaa; margin-top: 4px; }

/* ── 요약 카드 (상단) ── */
.summary-card {
    background: #fff;
    border: 1px solid #eee;
    border-radius: 12px;
    padding: 24px;
    display: flex;
    align-items: center;
    gap: 20px;
    transition: transform 0.2s, box-shadow 0.2s;
    text-decoration: none;
    color: inherit;
}
.summary-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.04);
    color: inherit;
}
.summary-icon {
    width: 54px; height: 54px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 24px;
}
.icon-users { background: #f0f7f0; color: #4caf50; }
.icon-boards { background: #f8f9fa; color: #666; }
.icon-alert { background: #fff5f5; color: #dc3545; }

.summary-info h5 { font-size: 13px; color: #888; margin: 0 0 4px 0; font-family: sans-serif; }
.summary-info .num { font-size: 24px; font-weight: 600; color: #1a1a1a; line-height: 1; }

/* ── 메인 패널 (하단) ── */
.admin-panel {
    background: #fff;
    border: 1px solid #eee;
    border-radius: 12px;
    padding: 24px;
    height: 100%;
}
.panel-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    font-family: 'Noto Serif KR', serif;
    font-size: 18px;
    font-weight: 500;
    color: #1a1a1a;
}
.panel-head a {
    font-family: 'Noto Sans KR', sans-serif;
    font-size: 12px;
    color: #888;
    text-decoration: none;
}
.panel-head a:hover { color: #1a1a1a; text-decoration: underline; }

/* 리스트 */
.admin-list { list-style: none; padding: 0; margin: 0; }
.admin-list li {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 14px 0;
    border-bottom: 1px solid #f5f5f5;
}
.admin-list li:last-child { border-bottom: none; padding-bottom: 0; }
.admin-list a {
    color: #333;
    text-decoration: none;
    font-size: 14px;
    max-width: 250px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.admin-list a:hover { color: #000; text-decoration: underline; }
.list-date { font-size: 12px; color: #bbb; }

.badge-urgent {
    display: inline-block;
    background: #fff0f0;
    color: #dc3545;
    border: 1px solid #fcc;
    font-size: 11px;
    padding: 2px 8px;
    border-radius: 999px;
    margin-right: 6px;
    vertical-align: middle;
}

.empty-msg { text-align: center; padding: 40px 0; color: #bbb; font-size: 13px; }
</style>

<div class="admin-wrap d-flex flex-column">
    
    <div class="admin-top">
        <div class="admin-title">관리자 대시보드</div>
        <div class="admin-sub">사이트 현황과 대기 중인 업무를 확인하세요.</div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <a href="/admin/writer_list.php" class="summary-card">
                <div class="summary-icon icon-alert"><i class="bi bi-bell-fill"></i></div>
                <div class="summary-info">
                    <h5>처리 대기 업무</h5>
                    <div class="num"><?= number_format($pending_writer_cnt + $pending_qna_cnt) ?> <span style="font-size:14px;font-weight:400;color:#888;">건</span></div>
                </div>
            </a>
        </div>
        <div class="col-md-4">
            <div class="summary-card" style="cursor:default;">
                <div class="summary-icon icon-users"><i class="bi bi-people-fill"></i></div>
                <div class="summary-info">
                    <h5>전체 가입자</h5>
                    <div class="num"><?= number_format($users_cnt) ?> <span style="font-size:14px;font-weight:400;color:#888;">명</span></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="summary-card" style="cursor:default;">
                <div class="summary-icon icon-boards"><i class="bi bi-journal-text"></i></div>
                <div class="summary-info">
                    <h5>전체 게시글</h5>
                    <div class="num"><?= number_format($boards_cnt) ?> <span style="font-size:14px;font-weight:400;color:#888;">개</span></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 flex-grow-1">
        
        <div class="col-md-6 d-flex flex-column">
            <div class="admin-panel flex-grow-1">
                <div class="panel-head">
                    대기 중인 작가 신청
                    <a href="/admin/writer_list.php">전체보기 <i class="bi bi-chevron-right"></i></a>
                </div>
                
                <?php if (empty($recent_writers)): ?>
                    <div class="empty-msg">대기 중인 작가 신청이 없습니다.</div>
                <?php else: ?>
                    <ul class="admin-list">
                        <?php foreach ($recent_writers as $w): ?>
                            <li>
                                <div>
                                    <span class="badge-urgent">NEW</span>
                                    <a href="/admin/writer_list.php">
                                        <?= htmlspecialchars($w['nickname']) ?> 님의 작가 신청
                                    </a>
                                </div>
                                <div class="list-date"><?= date('m.d H:i', strtotime($w['created_at'])) ?></div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-md-6 d-flex flex-column">
            <div class="admin-panel flex-grow-1">
                <div class="panel-head">
                    답변 대기 중인 문의
                    <a href="/qna/qna.php">전체보기 <i class="bi bi-chevron-right"></i></a>
                </div>
                
                <?php if (empty($recent_qnas)): ?>
                    <div class="empty-msg">모든 문의에 답변을 완료했습니다! 🎉</div>
                <?php else: ?>
                    <ul class="admin-list">
                        <?php foreach ($recent_qnas as $q): ?>
                            <li>
                                <div>
                                    <span class="badge-urgent">답변대기</span>
                                    <a href="/qna/qna_view.php?id=<?= $q['id'] ?>">
                                        <?= htmlspecialchars($q['title']) ?>
                                    </a>
                                </div>
                                <div class="list-date"><?= date('m.d H:i', strtotime($q['created_at'])) ?></div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

    </div>

</div>

<?php include '../includes/footer.php'; ?>
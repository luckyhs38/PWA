<?php
// /mypage.php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once './includes/db.php';
require_once './includes/auth_check.php';

// 1. 로그인 필수 체크
require_login();

$user_id = $_SESSION['user_id'];

try {
    // 2. 유저 기본 정보 조회
    $stmt = $pdo->prepare("SELECT user_id AS login_id, nickname, role, created_at FROM users WHERE id = :id AND deleted_at IS NULL");
    $stmt->execute([':id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo "<script>alert('유효하지 않은 회원입니다.'); location.href='/logout.php';</script>";
        exit;
    }

    // 3. 작가 신청 현황 조회 (가장 최근 신청 1건)
    $stmt = $pdo->prepare("SELECT status, created_at, reject_reason FROM writer_applications WHERE user_id = :id ORDER BY id DESC LIMIT 1");
    $stmt->execute([':id' => $user_id]);
    $writer_app = $stmt->fetch(PDO::FETCH_ASSOC);

    // 4. 활동 통계 (글, 댓글, 스크랩 수)
    // 내가 쓴 글 (익명글 + 작가글)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM boards WHERE writer_id = :id AND hidden_yn = 'N'");
    $stmt->execute([':id' => $user_id]);
    $post_count = $stmt->fetchColumn();

    // 내가 쓴 댓글
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM comments WHERE user_id = :id AND hidden_yn = 'N' AND deleted_at IS NULL");
    $stmt->execute([':id' => $user_id]);
    $comment_count = $stmt->fetchColumn();

    // 내 서랍 (저장한 문장)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM quote_scraps WHERE user_id = :id AND deleted_at IS NULL");
    $stmt->execute([':id' => $user_id]);
    $scrap_count = $stmt->fetchColumn();

    // 5. 최근 1:1 문의 내역 (최대 3건)
    $stmt = $pdo->prepare("SELECT id, title, status, created_at FROM qna WHERE user_id = :id AND deleted_at IS NULL ORDER BY id DESC LIMIT 3");
    $stmt->execute([':id' => $user_id]);
    $qna_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("데이터 조회 오류: " . $e->getMessage());
}

include './includes/header.php';
?>

<style>
/* ── 공통 래퍼 (게시판/아카이브와 동일) ── */
.mypage-wrap {
    width: 100%;
    max-width: 1100px;
    margin: 110px auto 80px;
    padding: 0 24px;
    flex: 1; /* 👉 추가: 화면 빈 공간을 채워서 푸터를 바닥으로 밀어냄 */
}

/* ── 헤더 ── */
.mp-header {
    border-bottom: 2px solid #1a1a1a;
    padding-bottom: 18px;
    margin-bottom: 30px;
}
.mp-title {
    font-family: 'Noto Serif KR', serif;
    font-size: 26px;
    font-weight: 500;
    color: #1a1a1a;
    letter-spacing: -.4px;
}
.mp-sub { font-size: 13px; color: #aaa; margin-top: 4px; }

/* ── 카드 공통 디자인 ── */
.mp-card {
    background: #fff;
    border: 1px solid #eee;
    border-radius: 12px;
    padding: 24px;
    /* height: 100%; */
    transition: box-shadow 0.2s;
}
.mp-card:hover {
    box-shadow: 0 8px 24px rgba(0,0,0,0.04);
}
.mp-card-title {
    font-family: 'Noto Serif KR', serif;
    font-size: 18px;
    font-weight: 500;
    color: #1a1a1a;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.mp-card-title a {
    font-size: 12px;
    color: #888;
    text-decoration: none;
    font-family: 'Noto Sans KR', sans-serif;
}
.mp-card-title a:hover { color: #1a1a1a; text-decoration: underline; }

/* ── 프로필 영역 ── */
.profile-box {
    display: flex;
    align-items: center;
    gap: 20px;
}
.profile-avatar {
    width: 64px;
    height: 64px;
    background: #f5f5f5;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: #ccc;
}
.profile-info h4 { margin: 0 0 6px 0; font-size: 20px; font-weight: 500; color: #1a1a1a; }
.profile-meta { font-size: 13px; color: #888; }
.role-badge {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 500;
    margin-left: 8px;
    vertical-align: middle;
}
.role-admin { background: #1a1a1a; color: #fff; }
.role-writer { background: #f0f7f0; color: #4caf50; border: 1px solid #d4ead4; }
.role-user { background: #f8f9fa; color: #666; border: 1px solid #ddd; }

/* ── 활동 통계 ── */
.stat-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
}
.stat-item {
    text-align: center;
    padding: 16px 10px;
    background: #fafafa;
    border-radius: 8px;
    border: 1px solid #f0f0f0;
}
.stat-num { font-size: 24px; font-weight: 500; color: #1a1a1a; margin-bottom: 4px; }
.stat-label { font-size: 12px; color: #888; }

/* ── QnA 리스트 ── */
.qna-mini-list { list-style: none; padding: 0; margin: 0; }
.qna-mini-list li {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid #f5f5f5;
}
.qna-mini-list li:last-child { border-bottom: none; padding-bottom: 0; }
.qna-mini-list a {
    color: #333;
    text-decoration: none;
    font-size: 14px;
    display: block;
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.qna-mini-list a:hover { color: #000; text-decoration: underline; }
.qna-date { font-size: 12px; color: #bbb; }

.status-badge {
    font-size: 11px; padding: 3px 8px; border-radius: 4px;
}
.status-pending { background: #f8f9fa; color: #666; border: 1px solid #ddd; }
.status-answered { background: #1a1a1a; color: #fff; }

/* ── 빈 상태 ── */
.empty-state { text-align: center; padding: 20px 0; color: #bbb; font-size: 13px; }

/* 버튼 둥글게 */
.btn-rounded { border-radius: 999px; font-size: 13px; padding: 6px 16px; }

@media (max-width: 768px) {
    .mypage-wrap { margin-top: 80px; padding: 0 15px; }
}
</style>

<div class="mypage-wrap">
    <div class="mp-header">
        <div class="mp-title">마이페이지</div>
        <div class="mp-sub">나의 활동 정보와 상태를 한눈에 확인하세요.</div>
    </div>

    <div class="row g-4">
        
        <div class="col-lg-6 d-flex flex-column">
            
            <div class="mp-card mb-4">
                <div class="mp-card-title">
                    기본 정보
                    <a href="/mypage_edit.php"><i class="bi bi-pencil"></i> 수정</a>
                </div>
                <div class="profile-box">
                    <div class="profile-avatar"><i class="bi bi-person"></i></div>
                    <div class="profile-info">
                        <h4>
                            <?= htmlspecialchars($user['nickname']) ?>
                            <?php if ($user['role'] === 'admin'): ?>
                                <span class="role-badge role-admin">관리자</span>
                            <?php elseif ($user['role'] === 'writer'): ?>
                                <span class="role-badge role-writer">작가</span>
                            <?php else: ?>
                                <span class="role-badge role-user">일반회원</span>
                            <?php endif; ?>
                        </h4>
                        <div class="profile-meta">
                            아이디: <?= htmlspecialchars($user['login_id']) ?> <br>
                            가입일: <?= date('Y.m.d', strtotime($user['created_at'])) ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mp-card flex-grow-1">
                <div class="mp-card-title">나의 활동</div>
                <div class="stat-grid">
                    <div class="stat-item">
                        <div class="stat-num"><?= number_format($post_count) ?></div>
                        <div class="stat-label">작성한 글</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-num"><?= number_format($comment_count) ?></div>
                        <div class="stat-label">작성한 댓글</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-num"><?= number_format($scrap_count) ?></div>
                        <div class="stat-label">
                            <a href="/board/archive.php?tab=mine" style="color:inherit; text-decoration:none;">내 서랍 <i class="bi bi-chevron-right"></i></a>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <div class="col-lg-6 d-flex flex-column">
            
            <div class="mp-card mb-4">
                <div class="mp-card-title">작가 신청 현황</div>
                
                <?php if ($user['role'] === 'admin' || $user['role'] === 'writer'): ?>
                    <div class="d-flex align-items-center gap-3">
                        <i class="bi bi-check-circle-fill text-success fs-3"></i>
                        <div>
                            <div style="font-weight:500; color:#1a1a1a;">이미 작가 권한을 보유하고 있습니다.</div>
                            <div style="font-size:13px; color:#888;">작가만의 방에서 자유롭게 글을 작성해보세요.</div>
                        </div>
                    </div>
                <?php else: ?>
                    <?php if (!$writer_app): ?>
                        <div class="text-center py-2">
                            <p style="font-size:14px; color:#555; margin-bottom:16px;">아직 작가 신청 내역이 없습니다.<br>나만의 멋진 글을 연재하고 싶다면 신청해보세요!</p>
                            <a href="/writer_apply.php" class="btn btn-dark btn-rounded">작가 신청하기</a>
                        </div>
                    <?php elseif ($writer_app['status'] === 'pending'): ?>
                        <div class="d-flex align-items-center gap-3">
                            <i class="bi bi-hourglass-split text-warning fs-3"></i>
                            <div>
                                <div style="font-weight:500; color:#1a1a1a;">작가 신청 심사가 진행 중입니다.</div>
                                <div style="font-size:13px; color:#888;">신청일: <?= date('Y.m.d', strtotime($writer_app['created_at'])) ?></div>
                            </div>
                        </div>
                    <?php elseif ($writer_app['status'] === 'rejected'): ?>
                        <div class="d-flex align-items-start gap-3">
                            <i class="bi bi-x-circle-fill text-danger fs-3"></i>
                            <div>
                                <div style="font-weight:500; color:#dc3545;">신청이 거절되었습니다.</div>
                                <div style="font-size:13px; color:#666; margin-top:4px; background:#fafafa; padding:8px; border-radius:6px;">
                                    <strong>사유:</strong> <?= htmlspecialchars($writer_app['reject_reason'] ?: '사유 없음') ?>
                                </div>
                                <div class="mt-2">
                                    <a href="/writer_apply.php" class="btn btn-outline-dark btn-rounded btn-sm">다시 신청하기</a>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <div class="mp-card flex-grow-1">
                <div class="mp-card-title">
                    내 문의 내역
                    <a href="/qna/qna.php?status=mine"><i class="bi bi-plus"></i> 전체보기</a>
                </div>
                
                <?php if (empty($qna_list)): ?>
                    <div class="empty-state">최근 작성한 문의 내역이 없습니다.</div>
                <?php else: ?>
                    <ul class="qna-mini-list">
                        <?php foreach ($qna_list as $qna): ?>
                            <li>
                                <div>
                                    <span class="status-badge <?= $qna['status'] === 'answered' ? 'status-answered' : 'status-pending' ?>">
                                        <?= $qna['status'] === 'answered' ? '답변완료' : '답변대기' ?>
                                    </span>
                                    <a href="/qna/qna_view.php?id=<?= $qna['id'] ?>" class="d-inline-block align-middle ms-2">
                                        <?= htmlspecialchars($qna['title']) ?>
                                    </a>
                                </div>
                                <div class="qna-date"><?= date('Y.m.d', strtotime($qna['created_at'])) ?></div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<?php include './includes/footer.php'; ?>
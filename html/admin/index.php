<?php
// /admin/index.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../includes/db.php';
require_once '../includes/auth_check.php';
require_admin();

$admin_menu = 'dashboard';

try {
    // 통계
    $stats = $pdo->query("
        SELECT
            (SELECT COUNT(*) FROM users WHERE deleted_at IS NULL)                    AS total_users,
            (SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()
             AND deleted_at IS NULL)                                                  AS today_users,
            (SELECT COUNT(*) FROM boards WHERE hidden_yn = 'N')                      AS total_posts,
            (SELECT COUNT(*) FROM boards WHERE hidden_yn = 'N'
             AND DATE(created_at) = CURDATE())                                       AS today_posts,
            (SELECT COUNT(*) FROM qna WHERE status = 'pending'
             AND deleted_at IS NULL)                                                  AS pending_qna,
            (SELECT COUNT(*) FROM writer_applications WHERE status = 'pending')      AS pending_writers
    ")->fetch(PDO::FETCH_ASSOC);

    // 최근 문의 5건
    $recent_qna = $pdo->query("
        SELECT q.id, q.title, q.status, q.private_yn, q.created_at, u.nickname
        FROM qna q JOIN users u ON q.user_id = u.id
        WHERE q.deleted_at IS NULL
        ORDER BY q.created_at DESC LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

    // 작가 신청 대기
    $pending_apply = $pdo->query("
        SELECT wa.id, wa.created_at, u.nickname, u.user_id AS login_id
        FROM writer_applications wa JOIN users u ON wa.user_id = u.id
        WHERE wa.status = 'pending'
        ORDER BY wa.created_at DESC LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

    // 최근 가입 회원 5명
    $recent_users = $pdo->query("
        SELECT id, nickname, user_id, role, created_at
        FROM users WHERE deleted_at IS NULL
        ORDER BY created_at DESC LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

    // 최근 게시글 5건
    $recent_posts = $pdo->query("
        SELECT b.id, b.title, b.board_type, b.created_at, u.nickname
        FROM boards b JOIN users u ON b.user_id = u.id
        WHERE b.hidden_yn = 'N'
        ORDER BY b.created_at DESC LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("오류: " . $e->getMessage());
}

include '_layout.php';
?>

<!-- 통계 카드 -->
<div class="stat-grid-4">
    <div class="stat-card">
        <div class="stat-label">전체 회원</div>
        <div class="stat-val"><?= number_format($stats['total_users']) ?></div>
        <div class="stat-diff">▲ 오늘 +<?= $stats['today_users'] ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">전체 게시글</div>
        <div class="stat-val"><?= number_format($stats['total_posts']) ?></div>
        <div class="stat-diff">▲ 오늘 +<?= $stats['today_posts'] ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">답변 대기</div>
        <div class="stat-val"><?= $stats['pending_qna'] ?></div>
        <div class="stat-diff <?= $stats['pending_qna'] > 0 ? 'down' : '' ?>">
            <?= $stats['pending_qna'] > 0 ? '미처리 ' . $stats['pending_qna'] . '건' : '모두 처리됨' ?>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-label">작가 신청 대기</div>
        <div class="stat-val"><?= $stats['pending_writers'] ?></div>
        <div class="stat-diff <?= $stats['pending_writers'] > 0 ? 'down' : '' ?>">
            <?= $stats['pending_writers'] > 0 ? '대기 중 ' . $stats['pending_writers'] . '건' : '모두 처리됨' ?>
        </div>
    </div>
</div>

<!-- 빠른 실행 -->
<div style="display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:20px;">
    <a href="/admin/writer_list.php" style="text-decoration:none;">
        <div class="stat-card" style="text-align:center; cursor:pointer; padding:16px; transition:border-color .15s;">
            <i class="bi bi-pencil-square" style="font-size:22px; color:#888; display:block; margin-bottom:6px;"></i>
            <div style="font-size:12px; color:#888;">작가 신청 처리</div>
        </div>
    </a>
    <a href="/admin/qna_list.php" style="text-decoration:none;">
        <div class="stat-card" style="text-align:center; cursor:pointer; padding:16px;">
            <i class="bi bi-reply" style="font-size:22px; color:#888; display:block; margin-bottom:6px;"></i>
            <div style="font-size:12px; color:#888;">문의 답변</div>
        </div>
    </a>
    <a href="/admin/users.php" style="text-decoration:none;">
        <div class="stat-card" style="text-align:center; cursor:pointer; padding:16px;">
            <i class="bi bi-person-check" style="font-size:22px; color:#888; display:block; margin-bottom:6px;"></i>
            <div style="font-size:12px; color:#888;">회원 권한 변경</div>
        </div>
    </a>
    <a href="/admin/posts.php" style="text-decoration:none;">
        <div class="stat-card" style="text-align:center; cursor:pointer; padding:16px;">
            <i class="bi bi-file-text" style="font-size:22px; color:#888; display:block; margin-bottom:6px;"></i>
            <div style="font-size:12px; color:#888;">게시글 관리</div>
        </div>
    </a>
</div>

<!-- 2컬럼 그리드 -->
<div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">

    <!-- 최근 문의 -->
    <div class="adm-card" style="margin-bottom:0;">
        <div class="adm-card-hd">
            <span class="adm-card-title">최근 문의</span>
            <a href="/admin/qna_list.php" class="adm-btn">전체 보기</a>
        </div>
        <?php if (empty($recent_qna)): ?>
            <div class="adm-empty" style="padding:30px 0;">
                <i class="bi bi-inbox"></i> 문의가 없습니다.
            </div>
        <?php else: ?>
            <table class="adm-table">
                <tbody>
                <?php foreach ($recent_qna as $q): ?>
                    <tr>
                        <td style="width:80px;">
                            <span class="adm-badge <?= $q['status'] ?>">
                                <?= $q['status'] === 'answered' ? '답변완료' : '대기' ?>
                            </span>
                        </td>
                        <td>
                            <a href="/admin/qna_list.php?id=<?= $q['id'] ?>"
                               style="color:#1a1a1a; text-decoration:none; font-size:13px;
                                      white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
                                      display:block; max-width:200px;">
                                <?= htmlspecialchars($q['title']) ?>
                            </a>
                        </td>
                        <td style="color:#bbb; font-size:11px; white-space:nowrap;">
                            <?= date('m.d', strtotime($q['created_at'])) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- 작가 신청 대기 -->
    <div class="adm-card" style="margin-bottom:0;">
        <div class="adm-card-hd">
            <span class="adm-card-title">작가 신청 대기</span>
            <a href="/admin/writer_list.php" class="adm-btn">전체 보기</a>
        </div>
        <?php if (empty($pending_apply)): ?>
            <div class="adm-empty" style="padding:30px 0;">
                <i class="bi bi-check-circle"></i> 대기 중인 신청이 없습니다.
            </div>
        <?php else: ?>
            <table class="adm-table">
                <tbody>
                <?php foreach ($pending_apply as $a): ?>
                    <tr>
                        <td>
                            <div style="font-size:13px; font-weight:500; color:#1a1a1a;">
                                <?= htmlspecialchars($a['nickname']) ?>
                            </div>
                            <div style="font-size:11px; color:#bbb;">
                                @<?= htmlspecialchars($a['login_id']) ?>
                            </div>
                        </td>
                        <td style="color:#bbb; font-size:11px; white-space:nowrap;">
                            <?= date('m.d', strtotime($a['created_at'])) ?>
                        </td>
                        <td style="text-align:right;">
                            <a href="/admin/writer_list.php?id=<?= $a['id'] ?>"
                               class="adm-btn warning" style="font-size:11px; padding:4px 10px;">
                                검토
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- 최근 가입 회원 -->
    <div class="adm-card" style="margin-bottom:0;">
        <div class="adm-card-hd">
            <span class="adm-card-title">최근 가입 회원</span>
            <a href="/admin/users.php" class="adm-btn">전체 보기</a>
        </div>
        <table class="adm-table">
            <tbody>
            <?php foreach ($recent_users as $u): ?>
                <tr>
                    <td>
                        <span class="adm-badge <?= $u['role'] ?>">
                            <?= ['admin'=>'관리자','writer'=>'작가','user'=>'일반'][$u['role']] ?? $u['role'] ?>
                        </span>
                    </td>
                    <td>
                        <div style="font-size:13px; color:#1a1a1a;"><?= htmlspecialchars($u['nickname']) ?></div>
                        <div style="font-size:11px; color:#bbb;">@<?= htmlspecialchars($u['user_id']) ?></div>
                    </td>
                    <td style="color:#bbb; font-size:11px; white-space:nowrap;">
                        <?= date('m.d', strtotime($u['created_at'])) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- 최근 게시글 -->
    <div class="adm-card" style="margin-bottom:0;">
        <div class="adm-card-hd">
            <span class="adm-card-title">최근 게시글</span>
            <a href="/admin/posts.php" class="adm-btn">전체 보기</a>
        </div>
        <table class="adm-table">
            <tbody>
            <?php foreach ($recent_posts as $p): ?>
                <tr>
                    <td style="width:60px;">
                        <span class="adm-badge <?= $p['board_type'] === 'writing' ? 'writer' : 'user' ?>">
                            <?= $p['board_type'] === 'writing' ? '작가방' : '익명' ?>
                        </span>
                    </td>
                    <td>
                        <a href="/board/view.php?id=<?= $p['id'] ?>&type=<?= urlencode($p['board_type']) ?>"
                           target="_blank"
                           style="color:#1a1a1a; text-decoration:none; font-size:13px;
                                  white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
                                  display:block; max-width:200px;">
                            <?= htmlspecialchars($p['title']) ?>
                        </a>
                    </td>
                    <td style="color:#bbb; font-size:11px; white-space:nowrap;">
                        <?= date('m.d', strtotime($p['created_at'])) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div>

<?php include '_layout_end.php'; ?>
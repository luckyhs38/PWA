<?php
// /admin/writer_list.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../includes/db.php';
require_once '../includes/auth_check.php';

// 관리자만 접근
if(!is_admin()){
    $title = '관리자 전용 페이지';
    $message = '관리자만 접근할 수 있는 메뉴입니다.';
    $link = '/'; // 메인 페이지나 이전 페이지 경로
    $link_text = '메인으로 돌아가기';
    include '../includes/permission_denied.php';
    exit;
}


$status_filter = $_GET['status'] ?? 'pending';
$allowed_status = ['all', 'pending', 'approved', 'rejected'];
if (!in_array($status_filter, $allowed_status)) $status_filter = 'pending';

$page   = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit  = 15;
$offset = ($page - 1) * $limit;

// 처리 결과 메시지
$msg = $_GET['msg'] ?? '';

try {
    // 탭별 카운트
    $counts = $pdo->query("
        SELECT
            COUNT(*) AS total,
            SUM(status = 'pending')  AS pending,
            SUM(status = 'approved') AS approved,
            SUM(status = 'rejected') AS rejected
        FROM writer_applications
    ")->fetch(PDO::FETCH_ASSOC);

    // 목록 쿼리
    $where = $status_filter !== 'all' ? "WHERE wa.status = '{$status_filter}'" : '';

    $cnt_stmt = $pdo->prepare("
        SELECT COUNT(*) FROM writer_applications wa {$where}
    ");
    $cnt_stmt->execute();
    $total       = (int)$cnt_stmt->fetchColumn();
    $total_pages = max(1, ceil($total / $limit));

    $stmt = $pdo->prepare("
        SELECT
            wa.id,
            wa.status,
            wa.reason,
            wa.portfolio,
            wa.reject_reason,
            wa.created_at,
            wa.processed_at,
            u.id          AS user_id,
            u.nickname,
            u.user_id     AS user_login_id,
            u.email,
            p.nickname    AS processed_by_name
        FROM writer_applications wa
        JOIN  users u ON wa.user_id = u.id
        LEFT JOIN users p ON wa.processed_by = p.id
        {$where}
        ORDER BY wa.created_at DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("오류: " . $e->getMessage());
}

include '../includes/header.php';
?>

<style>
.admin-wrap {
    width: 100%;
    max-width: 1100px;
    margin: 110px auto 80px;
    padding: 0 24px;
}

/* 헤더 */
.admin-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    border-bottom: 2px solid #1a1a1a;
    padding-bottom: 18px;
    margin-bottom: 0;
}
.admin-title {
    font-family: 'Noto Serif KR', serif;
    font-size: 24px;
    font-weight: 500;
    color: #1a1a1a;
}
.admin-sub { font-size: 13px; color: #aaa; margin-top: 4px; }

/* 탭 */
.admin-tabs {
    display: flex;
    border-bottom: 1px solid #eee;
    margin-bottom: 24px;
}
.admin-tab {
    padding: 13px 20px;
    font-size: 14px;
    color: #bbb;
    cursor: pointer;
    border: none;
    background: none;
    border-bottom: 2px solid transparent;
    margin-bottom: -1px;
    font-family: sans-serif;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: color .15s;
}
.admin-tab.active { color: #1a1a1a; border-bottom-color: #1a1a1a; }
.admin-tab:hover  { color: #555; }
.tab-cnt {
    font-size: 11px;
    background: #f0f0f0;
    color: #888;
    border-radius: 999px;
    padding: 1px 7px;
    font-weight: 500;
}
.admin-tab.active .tab-cnt { background: #1a1a1a; color: #fff; }
.tab-cnt.hot { background: #fff0f0; color: #dc3545; }

/* 테이블 */
.admin-table {
    width: 100%;
    border-collapse: collapse;
}
.admin-table th {
    font-size: 11px;
    font-weight: 500;
    color: #bbb;
    font-family: sans-serif;
    padding: 12px 10px;
    text-align: left;
    letter-spacing: .3px;
    border-bottom: 1px solid #eee;
    white-space: nowrap;
}
.admin-table td {
    font-size: 13px;
    color: #333;
    padding: 14px 10px;
    border-bottom: 1px solid #f5f5f5;
    vertical-align: middle;
    font-family: sans-serif;
}
.admin-table tbody tr:hover { background: #fafafa; }

.col-num      { width: 44px; color: #ccc !important; font-size: 11px !important; }
.col-user     { width: 130px; }
.col-reason   { }
.col-portfolio{ width: 90px; text-align: center !important; }
.col-date     { width: 100px; color: #bbb !important; font-size: 12px !important; white-space: nowrap; }
.col-status   { width: 90px; text-align: center !important; }
.col-action   { width: 130px; text-align: center !important; }

.reason-text {
    max-width: 340px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    display: block;
    cursor: pointer;
    color: #333;
}
.reason-text:hover { color: #1a1a1a; text-decoration: underline; }

/* 뱃지 */
.badge-pending  { display:inline-block; font-size:11px; padding:3px 9px; border-radius:999px; background:#fff8e6; color:#f5a623; border:1px solid #fde8a0; }
.badge-approved { display:inline-block; font-size:11px; padding:3px 9px; border-radius:999px; background:#f0f7f0; color:#4caf50; border:1px solid #d4ead4; }
.badge-rejected { display:inline-block; font-size:11px; padding:3px 9px; border-radius:999px; background:#fff5f5; color:#dc3545; border:1px solid #fcc; }

/* 버튼 */
.btn-approve {
    border: 1px solid #d4ead4;
    background: #f0f7f0;
    color: #4caf50;
    border-radius: 6px;
    padding: 5px 12px;
    font-size: 12px;
    font-family: sans-serif;
    cursor: pointer;
    transition: all .15s;
}
.btn-approve:hover { background: #4caf50; color: #fff; border-color: #4caf50; }
.btn-reject {
    border: 1px solid #fcc;
    background: #fff5f5;
    color: #dc3545;
    border-radius: 6px;
    padding: 5px 12px;
    font-size: 12px;
    font-family: sans-serif;
    cursor: pointer;
    transition: all .15s;
    margin-left: 4px;
}
.btn-reject:hover { background: #dc3545; color: #fff; border-color: #dc3545; }

.portfolio-link {
    font-size: 12px;
    color: #888;
    text-decoration: none;
}
.portfolio-link:hover { color: #1a1a1a; text-decoration: underline; }

/* 빈 상태 */
.admin-empty {
    text-align: center;
    padding: 60px 0;
    color: #ccc;
    font-size: 14px;
    font-family: sans-serif;
}
.admin-empty i { font-size: 32px; display: block; margin-bottom: 12px; }

/* 페이지네이션 */
.pagination-wrap { display: flex; justify-content: center; gap: 4px; margin-top: 28px; }
.page-btn {
    border: 1px solid #eee; background: #fff; color: #888;
    border-radius: 6px; padding: 7px 12px; font-size: 13px;
    font-family: sans-serif; cursor: pointer; text-decoration: none;
    transition: all .15s; min-width: 36px; text-align: center;
}
.page-btn:hover  { border-color: #aaa; color: #333; }
.page-btn.active { background: #1a1a1a; color: #fff; border-color: #1a1a1a; }
.page-btn.disabled { opacity: .35; pointer-events: none; }

/* 알림 */
.admin-alert {
    padding: 12px 16px;
    border-radius: 8px;
    font-size: 13px;
    font-family: sans-serif;
    margin-bottom: 20px;
}
.admin-alert.success { background: #f0f7f0; border: 1px solid #d4ead4; color: #4caf50; }
.admin-alert.error   { background: #fff5f5; border: 1px solid #fcc; color: #dc3545; }

/* 모달 */
.modal-bg {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,0.3); z-index: 1050;
    align-items: center; justify-content: center;
}
.modal-bg.show { display: flex; }
.modal-box {
    background: #fff; border-radius: 12px;
    padding: 28px; width: 420px; max-width: 90vw;
    box-shadow: 0 8px 30px rgba(0,0,0,0.1);
}
.modal-title { font-size: 16px; font-weight: 500; color: #1a1a1a; margin-bottom: 16px; }
.modal-body  { font-size: 14px; color: #555; line-height: 1.7; margin-bottom: 20px; white-space: pre-wrap; word-break: break-word; }
.modal-label { font-size: 13px; color: #555; margin-bottom: 8px; font-family: sans-serif; }
.modal-textarea {
    width: 100%; border: 1px solid #ddd; border-radius: 8px;
    padding: 10px 12px; font-size: 13px; font-family: sans-serif;
    color: #1a1a1a; resize: vertical; min-height: 100px; outline: none;
    box-sizing: border-box;
}
.modal-textarea:focus { border-color: #1a1a1a; }
.modal-btns { display: flex; justify-content: flex-end; gap: 8px; margin-top: 16px; }
.mbtn {
    font-size: 13px; padding: 8px 18px; border-radius: 6px;
    cursor: pointer; border: 1px solid #ddd; background: #fff;
    color: #333; font-family: sans-serif;
}
.mbtn:hover { background: #f5f5f5; }
.mbtn-approve { background: #f0f7f0; border-color: #d4ead4; color: #4caf50; font-weight: 500; }
.mbtn-approve:hover { background: #4caf50; color: #fff; border-color: #4caf50; }
.mbtn-reject  { background: #fff5f5; border-color: #fcc; color: #dc3545; font-weight: 500; }
.mbtn-reject:hover  { background: #dc3545; color: #fff; border-color: #dc3545; }

@media (max-width: 768px) {
    .admin-wrap { margin-top: 80px; padding: 0 15px; }
    .col-portfolio, .col-date { display: none; }
    .reason-text { max-width: 180px; }
}
</style>

<div class="admin-wrap">

    <div class="admin-top">
        <div>
            <div class="admin-title">작가 신청 관리</div>
            <div class="admin-sub">신청 목록을 검토하고 승인 또는 거절해주세요</div>
        </div>
    </div>

    <!-- 처리 결과 메시지 -->
    <?php if ($msg === 'approved'): ?>
        <div class="admin-alert success"><i class="bi bi-check-circle me-1"></i>승인 처리되었습니다.</div>
    <?php elseif ($msg === 'rejected'): ?>
        <div class="admin-alert success"><i class="bi bi-check-circle me-1"></i>거절 처리되었습니다.</div>
    <?php elseif ($msg === 'error'): ?>
        <div class="admin-alert error"><i class="bi bi-exclamation-circle me-1"></i>처리 중 오류가 발생했습니다.</div>
    <?php endif; ?>

    <!-- 탭 -->
    <div class="admin-tabs">
        <?php
        $tabs = [
            'pending'  => ['label' => '대기 중',   'cnt' => $counts['pending']],
            'approved' => ['label' => '승인됨',     'cnt' => $counts['approved']],
            'rejected' => ['label' => '거절됨',     'cnt' => $counts['rejected']],
            'all'      => ['label' => '전체',       'cnt' => $counts['total']],
        ];
        foreach ($tabs as $key => $tab):
        ?>
            <a href="?status=<?= $key ?>" class="admin-tab <?= $status_filter === $key ? 'active' : '' ?>">
                <?= $tab['label'] ?>
                <span class="tab-cnt <?= ($key === 'pending' && $tab['cnt'] > 0) ? 'hot' : '' ?>">
                    <?= number_format($tab['cnt']) ?>
                </span>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- 목록 -->
    <?php if (empty($applications)): ?>
        <div class="admin-empty">
            <i class="bi bi-inbox"></i>
            <?= $status_filter === 'pending' ? '대기 중인 신청이 없습니다.' : '해당 신청이 없습니다.' ?>
        </div>
    <?php else: ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th class="col-num">#</th>
                    <th class="col-user">신청자</th>
                    <th class="col-reason">신청 사유</th>
                    <th class="col-portfolio">포트폴리오</th>
                    <th class="col-date">신청일</th>
                    <th class="col-status">상태</th>
                    <th class="col-action">처리</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($applications as $i => $app): ?>
                    <tr>
                        <td class="col-num"><?= $total - $offset - $i ?></td>

                        <td class="col-user">
                            <div style="font-weight:500; color:#1a1a1a;">
                                <?= htmlspecialchars($app['nickname']) ?>
                            </div>
                            <div style="font-size:11px; color:#bbb;">
                                <?= htmlspecialchars($app['user_login_id']) ?>
                            </div>
                        </td>

                        <td class="col-reason">
                            <span
                                class="reason-text"
                                onclick="openReasonModal(
                                    <?= $app['id'] ?>,
                                    '<?= htmlspecialchars(addslashes($app['nickname'])) ?>',
                                    '<?= htmlspecialchars(addslashes($app['reason'])) ?>',
                                    '<?= $app['status'] ?>'
                                )">
                                <?= htmlspecialchars($app['reason']) ?>
                            </span>
                        </td>

                        <td class="col-portfolio" style="text-align:center;">
                            <?php if ($app['portfolio']): ?>
                                <a href="<?= htmlspecialchars($app['portfolio']) ?>"
                                   target="_blank" class="portfolio-link">
                                    <i class="bi bi-link-45deg"></i> 링크
                                </a>
                            <?php else: ?>
                                <span style="color:#ddd; font-size:12px;">—</span>
                            <?php endif; ?>
                        </td>

                        <td class="col-date">
                            <?= date('Y.m.d', strtotime($app['created_at'])) ?>
                        </td>

                        <td class="col-status" style="text-align:center;">
                            <?php if ($app['status'] === 'pending'): ?>
                                <span class="badge-pending">대기중</span>
                            <?php elseif ($app['status'] === 'approved'): ?>
                                <span class="badge-approved">승인됨</span>
                            <?php else: ?>
                                <span class="badge-rejected">거절됨</span>
                            <?php endif; ?>
                        </td>

                        <td class="col-action" style="text-align:center;">
                            <?php if ($app['status'] === 'pending'): ?>
                                <button
                                    class="btn-approve"
                                    onclick="openApproveModal(<?= $app['id'] ?>, '<?= htmlspecialchars(addslashes($app['nickname'])) ?>')">
                                    승인
                                </button>
                                <button
                                    class="btn-reject"
                                    onclick="openRejectModal(<?= $app['id'] ?>, '<?= htmlspecialchars(addslashes($app['nickname'])) ?>')">
                                    거절
                                </button>
                            <?php else: ?>
                                <span style="font-size:12px; color:#bbb;">
                                    <?= $app['processed_at'] ? date('Y.m.d', strtotime($app['processed_at'])) : '—' ?>
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- 페이지네이션 -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination-wrap">
                <a href="?status=<?= $status_filter ?>&page=<?= max(1, $page-1) ?>"
                   class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>">
                    <i class="bi bi-chevron-left"></i>
                </a>
                <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                    <a href="?status=<?= $status_filter ?>&page=<?= $p ?>"
                       class="page-btn <?= $p === $page ? 'active' : '' ?>">
                        <?= $p ?>
                    </a>
                <?php endfor; ?>
                <a href="?status=<?= $status_filter ?>&page=<?= min($total_pages, $page+1) ?>"
                   class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- 신청 사유 상세 모달 -->
<div class="modal-bg" id="reasonModal">
    <div class="modal-box">
        <div class="modal-title" id="reasonModalTitle"></div>
        <div class="modal-body"  id="reasonModalBody"></div>
        <div class="modal-btns">
            <button class="mbtn" onclick="closeModal('reasonModal')">닫기</button>
        </div>
    </div>
</div>

<!-- 승인 확인 모달 -->
<div class="modal-bg" id="approveModal">
    <div class="modal-box">
        <div class="modal-title">작가 신청을 승인할까요?</div>
        <div class="modal-body" id="approveModalBody"></div>
        <div class="modal-btns">
            <button class="mbtn" onclick="closeModal('approveModal')">취소</button>
            <button class="mbtn mbtn-approve" onclick="submitApprove()">승인</button>
        </div>
    </div>
</div>

<!-- 거절 모달 (사유 입력) -->
<div class="modal-bg" id="rejectModal">
    <div class="modal-box">
        <div class="modal-title">작가 신청을 거절할까요?</div>
        <div class="modal-body" id="rejectModalBody"></div>
        <div class="modal-label">거절 사유 <span style="color:#bbb; font-size:11px;">(선택)</span></div>
        <textarea
            class="modal-textarea"
            id="rejectReason"
            placeholder="신청자에게 전달될 거절 사유를 입력해주세요"></textarea>
        <div class="modal-btns">
            <button class="mbtn" onclick="closeModal('rejectModal')">취소</button>
            <button class="mbtn mbtn-reject" onclick="submitReject()">거절</button>
        </div>
    </div>
</div>

<!-- 처리 폼 (숨김) -->
<form method="post" action="writer_action.php" id="actionForm" style="display:none;">
    <input type="hidden" name="application_id" id="actionAppId">
    <input type="hidden" name="action"          id="actionType">
    <input type="hidden" name="reject_reason"   id="actionRejectReason">
    <input type="hidden" name="redirect_status" value="<?= htmlspecialchars($status_filter) ?>">
</form>

<script>
var _currentAppId = null;

/* ── 사유 상세 모달 ── */
function openReasonModal(id, nickname, reason, status) {
    document.getElementById('reasonModalTitle').textContent = nickname + ' 님의 신청 사유';
    document.getElementById('reasonModalBody').textContent  = reason;
    document.getElementById('reasonModal').classList.add('show');
}

/* ── 승인 모달 ── */
function openApproveModal(id, nickname) {
    _currentAppId = id;
    document.getElementById('approveModalBody').textContent =
        nickname + ' 님의 신청을 승인하면 즉시 작가 권한이 부여됩니다.';
    document.getElementById('approveModal').classList.add('show');
}

function submitApprove() {
    document.getElementById('actionAppId').value = _currentAppId;
    document.getElementById('actionType').value  = 'approve';
    document.getElementById('actionForm').submit();
}

/* ── 거절 모달 ── */
function openRejectModal(id, nickname) {
    _currentAppId = id;
    document.getElementById('rejectModalBody').textContent =
        nickname + ' 님의 신청을 거절합니다.';
    document.getElementById('rejectReason').value = '';
    document.getElementById('rejectModal').classList.add('show');
}

function submitReject() {
    document.getElementById('actionAppId').value         = _currentAppId;
    document.getElementById('actionType').value          = 'reject';
    document.getElementById('actionRejectReason').value  = document.getElementById('rejectReason').value;
    document.getElementById('actionForm').submit();
}

/* ── 공통 닫기 ── */
function closeModal(id) {
    document.getElementById(id).classList.remove('show');
}

// 배경 클릭 시 닫기
document.querySelectorAll('.modal-bg').forEach(function(el) {
    el.addEventListener('click', function(e) {
        if (e.target === el) el.classList.remove('show');
    });
});
</script>

<?php include '../includes/footer.php'; ?>
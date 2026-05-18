<?php
// /admin/writer_list.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../includes/db.php';
require_once '../includes/auth_check.php';

// 관리자만 접근
require_admin();

$admin_menu = 'writers';

$status_filter = $_GET['status'] ?? 'pending';
$allowed_status = ['all', 'pending', 'approved', 'rejected'];
if (!in_array($status_filter, $allowed_status)) $status_filter = 'pending';

$page   = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit  = 15;
$offset = ($page - 1) * $limit;

// 처리 결과 메시지
$msg = $_GET['msg'] ?? '';

try {
    // 💡 [수정] SUM 대신 COUNT(CASE WHEN...)을 사용하여 빈 데이터일 때도 0을 반환하도록 수정!
    $counts = $pdo->query("
        SELECT
            COUNT(*) AS total,
            COUNT(CASE WHEN status = 'pending'  THEN 1 END) AS pending,
            COUNT(CASE WHEN status = 'approved' THEN 1 END) AS approved,
            COUNT(CASE WHEN status = 'rejected' THEN 1 END) AS rejected
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

include '_layout.php';
?>

<?php if ($msg === 'approved'): ?>
    <div class="adm-alert success"><i class="bi bi-check-circle me-1"></i>승인 처리되었습니다.</div>
<?php elseif ($msg === 'rejected'): ?>
    <div class="adm-alert success"><i class="bi bi-check-circle me-1"></i>거절 처리되었습니다.</div>
<?php elseif ($msg === 'error'): ?>
    <div class="adm-alert error"><i class="bi bi-exclamation-circle me-1"></i>처리 중 오류가 발생했습니다.</div>
<?php endif; ?>

<div class="adm-tabs">
    <?php
    $tabs = [
        'pending'  => ['label' => '대기 중',   'cnt' => $counts['pending']],
        'approved' => ['label' => '승인됨',     'cnt' => $counts['approved']],
        'rejected' => ['label' => '거절됨',     'cnt' => $counts['rejected']],
        'all'      => ['label' => '전체',       'cnt' => $counts['total']],
    ];
    foreach ($tabs as $key => $tab):
        $is_active = ($status_filter === $key);
    ?>
        <a href="?status=<?= $key ?>" class="adm-tab <?= $is_active ? 'active' : '' ?>">
            <?= $tab['label'] ?>
            <span class="adm-tab-cnt" style="<?= ($key === 'pending' && $tab['cnt'] > 0 && !$is_active) ? 'background:#fff0f0; color:#dc3545;' : '' ?>">
                <?= number_format($tab['cnt']) ?>
            </span>
        </a>
    <?php endforeach; ?>
</div>

<div class="adm-card">
    
    <div class="adm-card-hd">
        <span class="adm-card-title">작가 신청 목록</span>
    </div>

    <?php if (empty($applications)): ?>
        <div class="adm-empty">
            <i class="bi bi-inbox"></i>
            <?= $status_filter === 'pending' ? '대기 중인 신청이 없습니다.' : '해당 신청이 없습니다.' ?>
        </div>
    <?php else: ?>
        <table class="adm-table">
            <thead>
                <tr>
                    <th style="width:44px;">#</th>
                    <th style="width:130px;">신청자</th>
                    <th>신청 사유</th>
                    <th style="width:90px; text-align:center;">포트폴리오</th>
                    <th style="width:100px;">신청일</th>
                    <th style="width:90px; text-align:center;">상태</th>
                    <th style="width:130px; text-align:center;">처리</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($applications as $i => $app): ?>
                    <tr>
                        <td style="color:#ccc; font-size:11px;"><?= $total - $offset - $i ?></td>
                        <td>
                            <div style="font-weight:500; color:#1a1a1a;">
                                <?= htmlspecialchars($app['nickname']) ?>
                            </div>
                            <div style="font-size:11px; color:#bbb;">
                                @<?= htmlspecialchars($app['user_login_id']) ?>
                            </div>
                        </td>
                        <td>
                            <span style="max-width:300px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; display:block; cursor:pointer; color:#333;"
                                  onclick="openReasonModal(
                                      <?= $app['id'] ?>,
                                      <?= htmlspecialchars(json_encode($app['nickname'])) ?>,
                                      <?= htmlspecialchars(json_encode($app['reason'])) ?>
                                  )"
                                  onmouseover="this.style.textDecoration='underline'; this.style.color='#1a1a1a';"
                                  onmouseout="this.style.textDecoration='none'; this.style.color='#333';">
                                <?= htmlspecialchars($app['reason']) ?>
                            </span>
                        </td>
                        <td style="text-align:center;">
                            <?php if ($app['portfolio']): ?>
                                <a href="<?= htmlspecialchars($app['portfolio']) ?>" target="_blank" style="font-size:12px; color:#888; text-decoration:none;" onmouseover="this.style.textDecoration='underline'; this.style.color='#1a1a1a';" onmouseout="this.style.textDecoration='none'; this.style.color='#888';">
                                    <i class="bi bi-link-45deg"></i> 링크
                                </a>
                            <?php else: ?>
                                <span style="color:#ddd; font-size:12px;">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="color:#bbb; font-size:12px;">
                            <?= date('Y.m.d', strtotime($app['created_at'])) ?>
                        </td>
                        <td style="text-align:center;">
                            <span class="adm-badge <?= $app['status'] ?>">
                                <?= ['pending'=>'대기중', 'approved'=>'승인됨', 'rejected'=>'거절됨'][$app['status']] ?>
                            </span>
                        </td>
                        <td style="text-align:center;">
                            <?php if ($app['status'] === 'pending'): ?>
                                <button class="adm-btn success" style="padding:4px 10px; font-size:11px;" 
                                        onclick='openApproveModal(<?= $app['id'] ?>, <?= htmlspecialchars(json_encode($app['nickname'])) ?>)'>승인</button>
                                <button class="adm-btn danger" style="padding:4px 10px; font-size:11px;" 
                                        onclick='openRejectModal(<?= $app['id'] ?>, <?= htmlspecialchars(json_encode($app['nickname'])) ?>)'>거절</button>
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

        <?php if ($total_pages > 1): ?>
            <div class="adm-pagination">
                <a href="?status=<?= $status_filter ?>&page=<?= max(1, $page-1) ?>"
                   class="adm-page-btn <?= $page <= 1 ? 'disabled' : '' ?>">
                    <i class="bi bi-chevron-left"></i>
                </a>
                <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                    <a href="?status=<?= $status_filter ?>&page=<?= $p ?>"
                       class="adm-page-btn <?= $p === $page ? 'active' : '' ?>">
                        <?= $p ?>
                    </a>
                <?php endfor; ?>
                <a href="?status=<?= $status_filter ?>&page=<?= min($total_pages, $page+1) ?>"
                   class="adm-page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<div class="adm-modal-bg" id="reasonModal">
    <div class="adm-modal">
        <div class="adm-modal-title" id="reasonModalTitle"></div>
        <div class="adm-modal-desc" id="reasonModalBody" style="white-space:pre-wrap; word-break:break-word;"></div>
        <div class="adm-modal-btns">
            <button class="adm-btn" onclick="closeModal('reasonModal')">닫기</button>
        </div>
    </div>
</div>

<div class="adm-modal-bg" id="approveModal">
    <div class="adm-modal">
        <div class="adm-modal-title">작가 신청을 승인할까요?</div>
        <div class="adm-modal-desc" id="approveModalBody"></div>
        <div class="adm-modal-btns">
            <button class="adm-btn" onclick="closeModal('approveModal')">취소</button>
            <button class="adm-btn success" onclick="submitApprove()">승인</button>
        </div>
    </div>
</div>

<div class="adm-modal-bg" id="rejectModal">
    <div class="adm-modal">
        <div class="adm-modal-title">작가 신청을 거절할까요?</div>
        <div class="adm-modal-desc" id="rejectModalBody" style="margin-bottom:12px;"></div>
        <div class="adm-modal-label">거절 사유 <span style="color:#bbb; font-size:11px;">(선택)</span></div>
        <textarea class="adm-modal-textarea" id="rejectReason" placeholder="신청자에게 전달될 거절 사유를 입력해주세요"></textarea>
        <div class="adm-modal-btns">
            <button class="adm-btn" onclick="closeModal('rejectModal')">취소</button>
            <button class="adm-btn danger" onclick="submitReject()">거절</button>
        </div>
    </div>
</div>

<form method="post" action="writer_action.php" id="actionForm" style="display:none;">
    <input type="hidden" name="application_id" id="actionAppId">
    <input type="hidden" name="action"          id="actionType">
    <input type="hidden" name="reject_reason"   id="actionRejectReason">
    <input type="hidden" name="redirect_status" value="<?= htmlspecialchars($status_filter) ?>">
</form>

<script>
var _currentAppId = null;

function openReasonModal(id, nickname, reason) {
    document.getElementById('reasonModalTitle').textContent = nickname + ' 님의 신청 사유';
    document.getElementById('reasonModalBody').textContent  = reason;
    document.getElementById('reasonModal').classList.add('show');
}

function openApproveModal(id, nickname) {
    _currentAppId = id;
    document.getElementById('approveModalBody').textContent = nickname + ' 님의 신청을 승인하면 즉시 작가 권한이 부여됩니다.';
    document.getElementById('approveModal').classList.add('show');
}

function submitApprove() {
    document.getElementById('actionAppId').value = _currentAppId;
    document.getElementById('actionType').value  = 'approve';
    document.getElementById('actionForm').submit();
}

function openRejectModal(id, nickname) {
    _currentAppId = id;
    document.getElementById('rejectModalBody').textContent = nickname + ' 님의 신청을 거절합니다.';
    document.getElementById('rejectReason').value = '';
    document.getElementById('rejectModal').classList.add('show');
}

function submitReject() {
    document.getElementById('actionAppId').value         = _currentAppId;
    document.getElementById('actionType').value          = 'reject';
    document.getElementById('actionRejectReason').value  = document.getElementById('rejectReason').value;
    document.getElementById('actionForm').submit();
}

function closeModal(id) {
    document.getElementById(id).classList.remove('show');
}
</script>

<?php include '_layout_end.php'; ?>
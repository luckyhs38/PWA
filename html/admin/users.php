<?php
// /admin/users.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../includes/db.php';
require_once '../includes/auth_check.php';
require_admin();

$admin_menu = 'users';

$page   = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit  = 20;
$offset = ($page - 1) * $limit;

$search      = trim($_GET['search'] ?? '');
$filter_role = $_GET['role'] ?? 'all';
$filter_status = $_GET['status'] ?? 'all';
$msg         = $_GET['msg'] ?? '';

$allowed_roles   = ['all', 'user', 'writer', 'admin'];
$allowed_statuses = ['all', '1', '0'];
if (!in_array($filter_role, $allowed_roles))     $filter_role = 'all';
if (!in_array($filter_status, $allowed_statuses)) $filter_status = 'all';

try {
    // 💡 [수정] WHERE 조건 생성 로직을 더 스마트하게!
    $conditions = [];
    $params     = [];

    if ($search !== '') {
        $conditions[] = "(nickname LIKE :search OR user_id LIKE :search2 OR email LIKE :search3)";
        $params[':search']  = '%' . $search . '%';
        $params[':search2'] = '%' . $search . '%';
        $params[':search3'] = '%' . $search . '%';
    }

    if ($filter_role !== 'all') {
        $conditions[] = "role = :role";
        $params[':role'] = $filter_role;
    }

    // 🚨 여기서 탈퇴/비활성 회원을 정확히 걸러줍니다.
    if ($filter_status === '0') {
        // [비활성/탈퇴] status가 0이거나 deleted_at이 채워져 있는 경우
        $conditions[] = "(status = 0 OR deleted_at IS NOT NULL)";
    } elseif ($filter_status === '1') {
        // [활성] status가 1이고 삭제되지 않은 정상 회원
        $conditions[] = "status = 1 AND deleted_at IS NULL";
    } else {
        // [전체] 삭제되지 않은 회원을 기본으로 보여줌
        $conditions[] = "deleted_at IS NULL";
    }

    $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

    // 전체 카운트
    $cnt_stmt = $pdo->prepare("SELECT COUNT(*) FROM users {$where}");
    $cnt_stmt->execute($params);
    $total       = (int)$cnt_stmt->fetchColumn();
    $total_pages = max(1, ceil($total / $limit));

    // 목록 조회
    $stmt = $pdo->prepare("
        SELECT id, user_id, nickname, email, phone, role, status, created_at, deleted_at
        FROM users
        {$where}
        ORDER BY created_at DESC
        LIMIT :limit OFFSET :offset
    ");
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 💡 [수정] 탭별 카운트 (탈퇴 회원을 정확히 세도록 변경)
    $tab_counts = $pdo->query("
        SELECT
            COUNT(CASE WHEN deleted_at IS NULL THEN 1 END) AS total,
            COUNT(CASE WHEN role = 'user' AND deleted_at IS NULL THEN 1 END)   AS cnt_user,
            COUNT(CASE WHEN role = 'writer' AND deleted_at IS NULL THEN 1 END) AS cnt_writer,
            COUNT(CASE WHEN role = 'admin' AND deleted_at IS NULL THEN 1 END)  AS cnt_admin,
            COUNT(CASE WHEN status = 0 OR deleted_at IS NOT NULL THEN 1 END)   AS cnt_withdrawn
        FROM users
    ")->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("오류: " . $e->getMessage());
}

$role_labels = ['admin' => '관리자', 'writer' => '작가', 'user' => '일반'];

include '_layout.php';
?>

<?php if ($msg === 'role_updated'): ?>
    <div class="adm-alert success"><i class="bi bi-check-circle"></i> 권한이 변경되었습니다.</div>
<?php elseif ($msg === 'status_updated'): ?>
    <div class="adm-alert success"><i class="bi bi-check-circle"></i> 계정 상태가 변경되었습니다.</div>
<?php elseif ($msg === 'error'): ?>
    <div class="adm-alert error"><i class="bi bi-exclamation-circle"></i> 처리 중 오류가 발생했습니다.</div>
<?php endif; ?>

<!-- 탭 -->
<div style="display:flex; border-bottom:1px solid #eee; margin-bottom:20px;">
    <?php
    $tabs = [
        'all'    => ['label' => '전체',    'cnt' => $tab_counts['total']],
        'user'   => ['label' => '일반회원', 'cnt' => $tab_counts['cnt_user']],
        'writer' => ['label' => '작가',    'cnt' => $tab_counts['cnt_writer']],
        'admin'  => ['label' => '관리자',  'cnt' => $tab_counts['cnt_admin']],
        'withdrawn' => ['label' => '탈퇴 회원', 'cnt' => $tab_counts['cnt_withdrawn']],
    ];
    
    foreach ($tabs as $key => $tab):
        // URL 생성
        $url = $key === 'withdrawn'
            ? "?role=all&status=0" . ($search ? '&search='.urlencode($search) : '')
            : "?role={$key}" . ($search ? '&search='.urlencode($search) : '');
            
        // 💡 [사수 코멘트] 활성화(밑줄) 조건 분기 처리로 중복 매칭 버그 수정!
        if ($key === 'withdrawn') {
            // 탈퇴회원 탭은 status가 0이고 role이 all일 때만 활성화
            $is_active = ($filter_status === '0' && $filter_role === 'all');
        } else if ($key === 'all') {
            // 전체 탭은 role이 all이되, 탈퇴회원 상태(status=0)가 아닐 때만 활성화
            $is_active = ($filter_role === 'all' && $filter_status !== '0');
        } else {
            // 나머지 일반, 작가, 관리자는 원래대로 key값 매칭
            $is_active = ($filter_role === $key);
        }
    ?>
        <a href="<?= $url ?>"
           style="padding:12px 18px; font-size:14px; text-decoration:none;
                color:<?= $is_active ? '#1a1a1a' : '#bbb' ?>;
                border-bottom:2px solid <?= $is_active ? '#1a1a1a' : 'transparent' ?>;
                margin-bottom:-1px; display:inline-flex; align-items:center; gap:6px;">
            <?= $tab['label'] ?>
            
            <span style="font-size:11px; 
                         background:<?= $is_active ? '#1a1a1a' : '#f0f0f0' ?>;
                         color:<?= $is_active ? '#fff' : '#888' ?>;
                         border-radius:999px; padding:1px 7px;">
                <?= number_format($tab['cnt']) ?>
            </span>
        </a>
    <?php endforeach; ?>
</div>

<!-- 검색/필터 -->
<div class="adm-filter-bar">
    <form method="get" action="users.php" style="display:contents;">
        <input type="hidden" name="role" value="<?= htmlspecialchars($filter_role) ?>">
        <div class="adm-search">
            <input type="text" name="search"
                   value="<?= htmlspecialchars($search) ?>"
                   placeholder="닉네임, 아이디, 이메일 검색">
            <i class="bi bi-search"></i>
        </div>
        <select name="status" class="adm-select" onchange="this.form.submit()">
            <option value="all"  <?= $filter_status === 'all' ? 'selected' : '' ?>>전체 상태</option>
            <option value="1"    <?= $filter_status === '1'   ? 'selected' : '' ?>>활성</option>
            <option value="0"    <?= $filter_status === '0'   ? 'selected' : '' ?>>비활성</option>
        </select>
        <button type="submit" class="adm-btn dark">검색</button>
        <?php if ($search || $filter_role !== 'all' || $filter_status !== 'all'): ?>
            <a href="users.php" class="adm-btn">초기화</a>
        <?php endif; ?>
    </form>
</div>

<!-- 회원 목록 -->
<div class="adm-card">
    <div class="adm-card-hd">
        <span class="adm-card-title">
            회원 목록
            <span style="font-size:12px; color:#bbb; font-weight:400; margin-left:6px;">
                총 <?= number_format($total) ?>명
            </span>
        </span>
    </div>

    <?php if (empty($users)): ?>
        <div class="adm-empty">
            <i class="bi bi-people"></i>
            <?= $search ? '검색 결과가 없습니다.' : '회원이 없습니다.' ?>
        </div>
    <?php else: ?>
        <table class="adm-table">
            <thead>
                <tr>
                    <th style="width:44px;">#</th>
                    <th>닉네임 / 아이디</th>
                    <th>이메일</th>
                    <th style="width:90px;">권한</th>
                    <th style="width:80px;">상태</th>
                    <th style="width:100px;">가입일</th>
                    <th style="width:140px; text-align:center;">관리</th>
                </tr>
            </thead>
<tbody>
                <?php foreach ($users as $i => $u): ?>
                    <tr>
                        <td style="color:#ccc; font-size:11px;"><?= $total - $offset - $i ?></td>
                        <td>
                            <div style="font-weight:500; color:#1a1a1a;">
                                <?= htmlspecialchars($u['nickname']) ?>
                            </div>
                            <div style="font-size:11px; color:#bbb;">
                                @<?= htmlspecialchars($u['user_id']) ?>
                            </div>
                        </td>
                        <td style="color:#666; font-size:13px;">
                            <?= htmlspecialchars($u['email']) ?>
                        </td>
                        <td>
                            <span class="adm-badge <?= $u['role'] ?>">
                                <?= $role_labels[$u['role']] ?? $u['role'] ?>
                            </span>
                        </td>
                        
                        <td>
                            <?php if (!empty($u['deleted_at'])): ?>
                                <span class="adm-badge" style="background:#f5f5f5; border:1px solid #ddd; color:#888;">
                                    탈퇴됨
                                </span>
                            <?php else: ?>
                                <span class="adm-badge <?= $u['status'] ? 'active' : 'inactive' ?>">
                                    <?= $u['status'] ? '활성' : '정지(비활성)' ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        
                        <td style="color:#bbb; font-size:12px;">
                            <?= date('Y.m.d', strtotime($u['created_at'])) ?>
                        </td>
                        <td style="text-align:center;">
                            <?php if ($u['id'] !== (int)$_SESSION['user_id']): ?>
                            
                                <?php if (!empty($u['deleted_at'])): ?>
                                    <span style="font-size:12px; color:#ccc;">탈퇴한 계정</span>
                                <?php else: ?>
                                    <button class="adm-btn"
                                            style="font-size:11px; padding:4px 10px; margin-right:4px;"
                                            onclick="openRoleModal(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['nickname'])) ?>', '<?= $u['role'] ?>')">
                                        권한 변경
                                    </button>
                                    <form method="post" action="user_action.php" style="display:inline;">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <input type="hidden" name="redirect"
                                               value="users.php?role=<?= $filter_role ?>&search=<?= urlencode($search) ?>&page=<?= $page ?>">
                                        <button type="submit"
                                                class="adm-btn <?= $u['status'] ? 'danger' : 'success' ?>"
                                                style="font-size:11px; padding:4px 10px;"
                                                onclick="return confirm('<?= $u['status'] ? '계정을 비활성화' : '계정을 활성화' ?>하시겠습니까?')">
                                            <?= $u['status'] ? '비활성화' : '활성화' ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                                
                            <?php else: ?>
                                <span style="font-size:12px; color:#ccc;">본인</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- 페이지네이션 -->
        <?php if ($total_pages > 1): ?>
            <div class="adm-pagination">
                <a href="?role=<?= $filter_role ?>&search=<?= urlencode($search) ?>&page=<?= max(1, $page-1) ?>"
                   class="adm-page-btn <?= $page <= 1 ? 'disabled' : '' ?>">
                    <i class="bi bi-chevron-left"></i>
                </a>
                <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                    <a href="?role=<?= $filter_role ?>&search=<?= urlencode($search) ?>&page=<?= $p ?>"
                       class="adm-page-btn <?= $p === $page ? 'active' : '' ?>">
                        <?= $p ?>
                    </a>
                <?php endfor; ?>
                <a href="?role=<?= $filter_role ?>&search=<?= urlencode($search) ?>&page=<?= min($total_pages, $page+1) ?>"
                   class="adm-page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- 권한 변경 모달 -->
<div class="adm-modal-bg" id="roleModal">
    <div class="adm-modal">
        <div class="adm-modal-title" id="roleModalTitle"></div>
        <div class="adm-modal-desc">변경할 권한을 선택해주세요.</div>
        <form method="post" action="user_action.php">
            <input type="hidden" name="action"   value="change_role">
            <input type="hidden" name="user_id"  id="roleUserId">
            <input type="hidden" name="redirect"
                   value="users.php?role=<?= $filter_role ?>&search=<?= urlencode($search) ?>&page=<?= $page ?>&msg=role_updated">
            <div style="display:flex; flex-direction:column; gap:8px; margin-bottom:20px;">
                <?php foreach (['user' => '일반회원', 'writer' => '작가', 'admin' => '관리자'] as $r => $label): ?>
                    <label style="display:flex; align-items:center; gap:10px; padding:10px 14px;
                                  border:1px solid #eee; border-radius:8px; cursor:pointer;
                                  font-size:13px; color:#333;">
                        <input type="radio" name="role" value="<?= $r ?>" id="role_<?= $r ?>">
                        <?= $label ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <div class="adm-modal-btns">
                <button type="button" class="adm-btn" onclick="closeModal('roleModal')">취소</button>
                <button type="submit" class="adm-btn dark">변경</button>
            </div>
        </form>
    </div>
</div>

<script>
function openRoleModal(userId, nickname, currentRole) {
    document.getElementById('roleModalTitle').textContent = nickname + ' 님의 권한 변경';
    document.getElementById('roleUserId').value = userId;
    var radio = document.getElementById('role_' + currentRole);
    if (radio) radio.checked = true;
    document.getElementById('roleModal').classList.add('show');
}
function closeModal(id) {
    document.getElementById(id).classList.remove('show');
}
</script>

<?php include '_layout_end.php'; ?>
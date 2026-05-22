<?php
// /admin/qna_list.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../includes/db.php';
require_once '../includes/auth_check.php';

// 관리자만 접근 가능
require_admin();

// 💡 사이드바 활성화 메뉴 키
$admin_menu = 'qna';

// 페이징 설정
$page   = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit  = 15;
$offset = ($page - 1) * $limit;

// 검색 및 필터 파라미터
$search        = trim($_GET['search'] ?? '');
$status_filter = $_GET['status'] ?? 'all';
$msg           = $_GET['msg'] ?? '';

$allowed_statuses = ['all', 'pending', 'answered'];
if (!in_array($status_filter, $allowed_statuses)) $status_filter = 'all';

try {
    // WHERE 조건 조합
    $conditions = ["q.deleted_at IS NULL"];
    $params     = [];

    if ($search !== '') {
        $conditions[] = "(q.title LIKE :search OR q.content LIKE :search2 OR u.nickname LIKE :search3 OR u.user_id LIKE :search4)";
        $params[':search']  = '%' . $search . '%';
        $params[':search2'] = '%' . $search . '%';
        $params[':search3'] = '%' . $search . '%';
        $params[':search4'] = '%' . $search . '%';
    }

    if ($status_filter !== 'all') {
        $conditions[] = "q.status = :status";
        $params[':status'] = $status_filter;
    }

    $where = 'WHERE ' . implode(' AND ', $conditions);

    // 전체 및 탭별 개수 카운트
    $count_sql = "
        SELECT
            COUNT(CASE WHEN q.deleted_at IS NULL THEN 1 END) AS total,
            COUNT(CASE WHEN q.status = 'pending' AND q.deleted_at IS NULL THEN 1 END) AS pending,
            COUNT(CASE WHEN q.status = 'answered' AND q.deleted_at IS NULL THEN 1 END) AS answered
        FROM qna q
        LEFT JOIN users u ON q.user_id = u.id
    ";
    
    // 검색어가 있을 땐 검색 결과 내에서 카운트
    if ($search !== '') {
        $search_where = "WHERE " . implode(' AND ', array_filter($conditions, fn($c) => strpos($c, 'q.status =') === false));
        $count_sql = "
            SELECT
                COUNT(*) AS total,
                SUM(q.status = 'pending') AS pending,
                SUM(q.status = 'answered') AS answered
            FROM qna q
            LEFT JOIN users u ON q.user_id = u.id
            {$search_where}
        ";
        $stmt_cnt = $pdo->prepare($count_sql);
        foreach ($params as $k => $v) {
            if ($k !== ':status') $stmt_cnt->bindValue($k, $v);
        }
        $stmt_cnt->execute();
        $tab_counts = $stmt_cnt->fetch(PDO::FETCH_ASSOC);
    } else {
        $tab_counts = $pdo->query($count_sql)->fetch(PDO::FETCH_ASSOC);
    }

    // 현재 탭의 전체 개수 (페이징용)
    $total = (int)($tab_counts[$status_filter === 'all' ? 'total' : $status_filter] ?? 0);
    $total_pages = max(1, ceil($total / $limit));

    // 리스트 조회
    $stmt = $pdo->prepare("
        SELECT 
            q.id, q.title, q.status, q.private_yn, q.created_at, q.answered_at,
            u.nickname, u.user_id AS login_id
        FROM qna q
        LEFT JOIN users u ON q.user_id = u.id
        {$where}
        ORDER BY q.created_at DESC
        LIMIT :limit OFFSET :offset
    ");
    
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $qna_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("오류: " . $e->getMessage());
}

include '_layout.php';
?>

<?php if ($msg === 'answered'): ?>
    <div class="adm-alert success"><i class="bi bi-check-circle"></i> 답변이 등록되었습니다.</div>
<?php elseif ($msg === 'deleted'): ?>
    <div class="adm-alert success"><i class="bi bi-check-circle"></i> 문의가 삭제되었습니다.</div>
<?php elseif ($msg === 'error'): ?>
    <div class="adm-alert error"><i class="bi bi-exclamation-circle"></i> 처리 중 오류가 발생했습니다.</div>
<?php endif; ?>

<div class="adm-tabs">
    <?php
    $tabs = [
        'all'      => ['label' => '전체',     'cnt' => $tab_counts['total'] ?? 0],
        'pending'  => ['label' => '답변대기', 'cnt' => $tab_counts['pending'] ?? 0],
        'answered' => ['label' => '답변완료', 'cnt' => $tab_counts['answered'] ?? 0],
    ];
    
    foreach ($tabs as $key => $tab):
        $is_active = ($status_filter === $key);
        $url = "?status={$key}" . ($search ? '&search='.urlencode($search) : '');
    ?>
        <a href="<?= $url ?>" class="adm-tab <?= $is_active ? 'active' : '' ?>">
            <?= $tab['label'] ?>
            <span class="adm-tab-cnt" style="<?= ($key === 'pending' && $tab['cnt'] > 0 && !$is_active) ? 'background:#fff0f0; color:#dc3545;' : '' ?>">
                <?= number_format((float)$tab['cnt']) ?>
            </span>
        </a>
    <?php endforeach; ?>
</div>

<div class="adm-filter-bar">
    <form method="get" action="qna_list.php" style="display:contents;">
        <input type="hidden" name="status" value="<?= htmlspecialchars($status_filter) ?>">
        <div class="adm-search">
            <input type="text" name="search"
                   value="<?= htmlspecialchars($search) ?>"
                   placeholder="제목, 내용, 작성자 검색">
            <i class="bi bi-search"></i>
        </div>
        <button type="submit" class="adm-btn dark">검색</button>
        <?php if ($search): ?>
            <a href="qna_list.php?status=<?= $status_filter ?>" class="adm-btn">초기화</a>
        <?php endif; ?>
    </form>
</div>

<div class="adm-card">
    <div class="adm-card-hd">
        <span class="adm-card-title">
            문의 목록
            <span style="font-size:12px; color:#bbb; font-weight:400; margin-left:6px;">
                총 <?= number_format($total) ?>건
            </span>
        </span>
    </div>

    <?php if (empty($qna_list)): ?>
        <div class="adm-empty">
            <i class="bi bi-chat-square-text"></i>
            <?= $search ? '검색 결과가 없습니다.' : '등록된 문의가 없습니다.' ?>
        </div>
    <?php else: ?>
        
        <div class="adm-table-wrap">
            <table class="adm-table">
                <thead>
                    <tr>
                        <th style="width:44px;">#</th>
                        <th style="width:80px; text-align:center;">상태</th>
                        <th>제목</th>
                        <th style="width:130px;">작성자</th>
                        <th style="width:100px;">등록일</th>
                        <th style="width:100px;">답변일</th>
                        <th style="width:80px; text-align:center;">관리</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($qna_list as $i => $q): ?>
                        <tr>
                            <td style="color:#ccc; font-size:11px;"><?= $total - $offset - $i ?></td>
                            <td style="text-align:center;">
                                <span class="adm-badge <?= $q['status'] ?>">
                                    <?= $q['status'] === 'answered' ? '답변완료' : '대기중' ?>
                                </span>
                            </td>
                            <td>
                                <a href="/qna/qna_view.php?id=<?= $q['id'] ?>" target="_blank"
                                   style="color:#1a1a1a; text-decoration:none; font-size:13px; font-weight:500; display:flex; align-items:center; gap:6px;"
                                   onmouseover="this.style.textDecoration='underline'" 
                                   onmouseout="this.style.textDecoration='none'">
                                    
                                    <?php if ($q['private_yn'] === 'Y'): ?>
                                        <i class="bi bi-lock-fill" style="color:#aaa; font-size:12px;"></i>
                                    <?php endif; ?>
                                    
                                    <span style="max-width:300px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; display:block;">
                                        <?= htmlspecialchars($q['title']) ?>
                                    </span>
                                </a>
                            </td>
                            <td>
                                <div style="font-weight:500; color:#1a1a1a;">
                                    <?= htmlspecialchars($q['nickname'] ?? '알 수 없음') ?>
                                </div>
                                <div style="font-size:11px; color:#bbb;">
                                    @<?= htmlspecialchars($q['login_id'] ?? '알 수 없음') ?>
                                </div>
                            </td>
                            <td style="color:#bbb; font-size:12px;">
                                <?= date('Y.m.d', strtotime($q['created_at'])) ?>
                            </td>
                            <td style="color:#bbb; font-size:12px;">
                                <?= $q['answered_at'] ? date('Y.m.d', strtotime($q['answered_at'])) : '—' ?>
                            </td>
                            <td style="text-align:center;">
                                <?php if ($q['status'] === 'pending'): ?>
                                    <a href="/qna/qna_view.php?id=<?= $q['id'] ?>" target="_blank" class="adm-btn success" style="font-size:11px; padding:4px 10px;">
                                        답변하기
                                    </a>
                                <?php else: ?>
                                    <a href="/qna/qna_view.php?id=<?= $q['id'] ?>" target="_blank" class="adm-btn" style="font-size:11px; padding:4px 10px;">
                                        보기
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div> <?php if ($total_pages > 1): ?>
            <div class="adm-pagination">
                <a href="?status=<?= $status_filter ?>&search=<?= urlencode($search) ?>&page=<?= max(1, $page-1) ?>"
                   class="adm-page-btn <?= $page <= 1 ? 'disabled' : '' ?>">
                    <i class="bi bi-chevron-left"></i>
                </a>
                <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                    <a href="?status=<?= $status_filter ?>&search=<?= urlencode($search) ?>&page=<?= $p ?>"
                       class="adm-page-btn <?= $p === $page ? 'active' : '' ?>">
                        <?= $p ?>
                    </a>
                <?php endfor; ?>
                <a href="?status=<?= $status_filter ?>&search=<?= urlencode($search) ?>&page=<?= min($total_pages, $page+1) ?>"
                   class="adm-page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </div>
        <?php endif; ?>
        
    <?php endif; ?>
</div>

<?php include '_layout_end.php'; ?>
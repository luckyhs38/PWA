<?php
// /admin/posts.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_admin($pdo);

$admin_menu = 'posts';

$page        = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit       = 20;
$offset      = ($page - 1) * $limit;
$search      = trim($_GET['search'] ?? '');
$filter_type = $_GET['type'] ?? 'all';
$filter_hidden = $_GET['hidden'] ?? 'all';
$msg         = $_GET['msg'] ?? '';

$allowed_types  = ['all', 'anonymity', 'writing'];
$allowed_hidden = ['all', 'N', 'Y'];
if (!in_array($filter_type,   $allowed_types))  $filter_type   = 'all';
if (!in_array($filter_hidden, $allowed_hidden)) $filter_hidden = 'all';

try {
    // 조건 생성
    $conditions = [];
    $params     = [];

    if ($search !== '') {
        $conditions[] = "(b.title LIKE :search OR u.nickname LIKE :search2)";
        $params[':search']  = '%' . $search . '%';
        $params[':search2'] = '%' . $search . '%';
    }
    if ($filter_type !== 'all') {
        $conditions[] = "b.board_type = :type";
        $params[':type'] = $filter_type;
    }
    if ($filter_hidden !== 'all') {
        $conditions[] = "b.hidden_yn = :hidden";
        $params[':hidden'] = $filter_hidden;
    }

    $where = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

    // 전체 카운트
    $cnt_stmt = $pdo->prepare("
        SELECT COUNT(*) FROM boards b
        JOIN users u ON b.user_id = u.id
        {$where}
    ");
    $cnt_stmt->execute($params);
    $total       = (int)$cnt_stmt->fetchColumn();
    $total_pages = max(1, ceil($total / $limit));

    // 목록
    $stmt = $pdo->prepare("
        SELECT
            b.id, b.title, b.board_type, b.hidden_yn,
            b.view_count, b.created_at,
            u.nickname, u.user_id AS login_id,
            (SELECT COUNT(*) FROM comments c
             WHERE c.board_id = b.id AND c.deleted_at IS NULL) AS comment_count
        FROM boards b
        JOIN users u ON b.user_id = u.id
        {$where}
        ORDER BY b.created_at DESC
        LIMIT :limit OFFSET :offset
    ");
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 탭 카운트
    $tab_counts = $pdo->query("
        SELECT
            COUNT(*)                       AS total,
            SUM(board_type = 'anonymity')  AS cnt_anon,
            SUM(board_type = 'writing')    AS cnt_writing,
            SUM(hidden_yn = 'Y')           AS cnt_hidden
        FROM boards
    ")->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("오류: " . $e->getMessage());
}

$type_labels = ['anonymity' => '익명글', 'writing' => '작가방'];

include '_layout.php';
?>

<style>
@media (max-width: 768px) {
    .posts-hide-mobile { display: none !important; }
    .adm-filter-bar { flex-direction: column; align-items: stretch; }
    .adm-search { min-width: unset; }
}
</style>

<?php if ($msg === 'hidden'): ?>
    <div class="adm-alert warning"><i class="bi bi-eye-slash"></i> 게시글이 숨김 처리되었습니다.</div>
<?php elseif ($msg === 'restored'): ?>
    <div class="adm-alert success"><i class="bi bi-eye"></i> 게시글이 복구되었습니다.</div>
<?php elseif ($msg === 'error'): ?>
    <div class="adm-alert error"><i class="bi bi-exclamation-circle"></i> 처리 중 오류가 발생했습니다.</div>
<?php endif; ?>

<!-- 탭 -->
<div style="display:flex; border-bottom:1px solid #eee; margin-bottom:20px; overflow-x:auto;">
    <?php
    $tabs = [
        'all'       => ['label' => '전체',    'cnt' => $tab_counts['total'],      'type' => 'all',       'hidden' => 'all'],
        'anonymity' => ['label' => '익명글',  'cnt' => $tab_counts['cnt_anon'],   'type' => 'anonymity', 'hidden' => 'all'],
        'writing'   => ['label' => '작가방',  'cnt' => $tab_counts['cnt_writing'],'type' => 'writing',   'hidden' => 'all'],
        'hidden'    => ['label' => '숨김',    'cnt' => $tab_counts['cnt_hidden'], 'type' => 'all',       'hidden' => 'Y'],
    ];
    foreach ($tabs as $key => $tab):
        $is_active = ($filter_type === $tab['type'] && $filter_hidden === $tab['hidden']);
        $url = "?type={$tab['type']}&hidden={$tab['hidden']}" . ($search ? '&search='.urlencode($search) : '');
    ?>
        <a href="<?= $url ?>"
           style="padding:12px 16px; font-size:14px; text-decoration:none; white-space:nowrap;
                  color:<?= $is_active ? '#1a1a1a' : '#bbb' ?>;
                  border-bottom:2px solid <?= $is_active ? '#1a1a1a' : 'transparent' ?>;
                  margin-bottom:-1px; display:inline-flex; align-items:center; gap:6px;">
            <?= $tab['label'] ?>
            <span style="font-size:11px; background:<?= $is_active ? '#1a1a1a' : '#f0f0f0' ?>;
                         color:<?= $is_active ? '#fff' : '#888' ?>;
                         border-radius:999px; padding:1px 7px;">
                <?= number_format($tab['cnt']) ?>
            </span>
        </a>
    <?php endforeach; ?>
</div>

<!-- 검색/필터 -->
<div class="adm-filter-bar">
    <form method="get" action="posts.php" style="display:contents;">
        <input type="hidden" name="type"   value="<?= htmlspecialchars($filter_type) ?>">
        <input type="hidden" name="hidden" value="<?= htmlspecialchars($filter_hidden) ?>">
        <div class="adm-search">
            <input type="text" name="search"
                   value="<?= htmlspecialchars($search) ?>"
                   placeholder="제목, 작성자 닉네임 검색">
            <i class="bi bi-search"></i>
        </div>
        <button type="submit" class="adm-btn dark">검색</button>
        <?php if ($search): ?>
            <a href="posts.php?type=<?= $filter_type ?>&hidden=<?= $filter_hidden ?>" class="adm-btn">초기화</a>
        <?php endif; ?>
    </form>
</div>

<!-- 게시글 목록 -->
<div class="adm-card">
    <div class="adm-card-hd">
        <span class="adm-card-title">
            게시글 목록
            <span style="font-size:12px; color:#bbb; font-weight:400; margin-left:6px;">
                총 <?= number_format($total) ?>건
            </span>
        </span>
    </div>

    <?php if (empty($posts)): ?>
        <div class="adm-empty">
            <i class="bi bi-file-text"></i>
            <?= $search ? '검색 결과가 없습니다.' : '게시글이 없습니다.' ?>
        </div>
    <?php else: ?>
        <table class="adm-table">
            <thead>
                <tr>
                    <th style="width:44px;">#</th>
                    <th style="width:72px;">구분</th>
                    <th>제목</th>
                    <th class="posts-hide-mobile">작성자</th>
                    <th class="posts-hide-mobile" style="width:60px; text-align:center;">댓글</th>
                    <th class="posts-hide-mobile" style="width:60px; text-align:center;">조회</th>
                    <th style="width:80px;">상태</th>
                    <th class="posts-hide-mobile" style="width:90px;">작성일</th>
                    <th style="width:90px; text-align:center;">관리</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($posts as $i => $post): ?>
                    <tr>
                        <td style="color:#ccc; font-size:11px;"><?= $total - $offset - $i ?></td>
                        <td>
                            <span class="adm-badge <?= $post['board_type'] === 'writing' ? 'writer' : 'user' ?>">
                                <?= $type_labels[$post['board_type']] ?? $post['board_type'] ?>
                            </span>
                        </td>
                        <td>
                            <a href="/board/view.php?id=<?= $post['id'] ?>&type=<?= urlencode($post['board_type']) ?>"
                               target="_blank"
                               style="color:#1a1a1a; text-decoration:none; font-size:13px;
                                      display:block; max-width:280px;
                                      white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                <?= htmlspecialchars($post['title']) ?>
                            </a>
                        </td>
                        <td class="posts-hide-mobile">
                            <div style="font-size:13px; color:#555;">
                                <?= htmlspecialchars($post['nickname']) ?>
                            </div>
                            <div style="font-size:11px; color:#bbb;">
                                @<?= htmlspecialchars($post['login_id']) ?>
                            </div>
                        </td>
                        <td class="posts-hide-mobile" style="text-align:center; color:#888; font-size:13px;">
                            <?= number_format($post['comment_count']) ?>
                        </td>
                        <td class="posts-hide-mobile" style="text-align:center; color:#888; font-size:13px;">
                            <?= number_format($post['view_count']) ?>
                        </td>
                        <td>
                            <span class="adm-badge <?= $post['hidden_yn'] === 'Y' ? 'inactive' : 'active' ?>">
                                <?= $post['hidden_yn'] === 'Y' ? '숨김' : '공개' ?>
                            </span>
                        </td>
                        <td class="posts-hide-mobile" style="color:#bbb; font-size:12px;">
                            <?= date('Y.m.d', strtotime($post['created_at'])) ?>
                        </td>
                        <td style="text-align:center;">
                            <form method="post" action="post_action.php" style="display:inline;">
                                <input type="hidden" name="post_id"   value="<?= $post['id'] ?>">
                                <input type="hidden" name="action"
                                       value="<?= $post['hidden_yn'] === 'Y' ? 'restore' : 'hide' ?>">
                                <input type="hidden" name="redirect"
                                       value="posts.php?type=<?= $filter_type ?>&hidden=<?= $filter_hidden ?>&search=<?= urlencode($search) ?>&page=<?= $page ?>&msg=<?= $post['hidden_yn'] === 'Y' ? 'restored' : 'hidden' ?>">
                                <button type="submit"
                                        class="adm-btn <?= $post['hidden_yn'] === 'Y' ? 'success' : 'danger' ?>"
                                        style="font-size:11px; padding:4px 10px;"
                                        onclick="return confirm('<?= $post['hidden_yn'] === 'Y' ? '게시글을 복구' : '게시글을 숨김 처리' ?>하시겠습니까?')">
                                    <?= $post['hidden_yn'] === 'Y' ? '복구' : '숨김' ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- 페이지네이션 -->
        <?php if ($total_pages > 1): ?>
            <div class="adm-pagination">
                <a href="?type=<?= $filter_type ?>&hidden=<?= $filter_hidden ?>&search=<?= urlencode($search) ?>&page=<?= max(1, $page-1) ?>"
                   class="adm-page-btn <?= $page <= 1 ? 'disabled' : '' ?>">
                    <i class="bi bi-chevron-left"></i>
                </a>
                <?php
                // 페이지 범위 계산 (모바일 고려 최대 5개)
                $start_page = max(1, $page - 2);
                $end_page   = min($total_pages, $page + 2);
                if ($start_page > 1):
                ?>
                    <a href="?type=<?= $filter_type ?>&hidden=<?= $filter_hidden ?>&search=<?= urlencode($search) ?>&page=1"
                       class="adm-page-btn">1</a>
                    <?php if ($start_page > 2): ?>
                        <span class="adm-page-btn" style="pointer-events:none;">…</span>
                    <?php endif; ?>
                <?php endif; ?>

                <?php for ($p = $start_page; $p <= $end_page; $p++): ?>
                    <a href="?type=<?= $filter_type ?>&hidden=<?= $filter_hidden ?>&search=<?= urlencode($search) ?>&page=<?= $p ?>"
                       class="adm-page-btn <?= $p === $page ? 'active' : '' ?>">
                        <?= $p ?>
                    </a>
                <?php endfor; ?>

                <?php if ($end_page < $total_pages): ?>
                    <?php if ($end_page < $total_pages - 1): ?>
                        <span class="adm-page-btn" style="pointer-events:none;">…</span>
                    <?php endif; ?>
                    <a href="?type=<?= $filter_type ?>&hidden=<?= $filter_hidden ?>&search=<?= urlencode($search) ?>&page=<?= $total_pages ?>"
                       class="adm-page-btn"><?= $total_pages ?></a>
                <?php endif; ?>

                <a href="?type=<?= $filter_type ?>&hidden=<?= $filter_hidden ?>&search=<?= urlencode($search) ?>&page=<?= min($total_pages, $page+1) ?>"
                   class="adm-page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include '_layout_end.php'; ?>

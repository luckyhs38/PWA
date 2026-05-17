<?php
// /board/list.php
require_once '../includes/db.php';
require_once '../includes/auth_check.php'; // 로그인 확인

// 1. 게시판 타입 설정 및 검증
$allowed_types = ['anonymity' => '익명글', 'writing' => '작가만의 방'];
$type = $_GET['type'] ?? 'anonymity';

if (!array_key_exists($type, $allowed_types)) {
    echo "<script>alert('잘못된 접근입니다.'); history.back();</script>";
    exit;
}

$board_name = $allowed_types[$type];

// 2. 페이징 및 검색 변수 설정
$page = max(1, isset($_GET['page']) ? (int)$_GET['page'] : 1);
$limit = 10; // 한 페이지에 보여줄 글 개수
$offset = ($page - 1) * $limit;
$search = trim($_GET['search'] ?? '');

// 3. 쿼리 조건 생성
$where_clause = "b.board_type = :type AND b.hidden_yn = 'N'"; // 삭제데이터 제외
$params = [':type' => $type];

if ($search !== '') {
    $where_clause .= " AND (b.title LIKE :search OR b.content LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

// 4. 전체 게시글 수 조회 (페이징용)
try {
    $count_sql = "SELECT COUNT(*) FROM boards b WHERE $where_clause";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_posts = $stmt->fetchColumn();
    $total_pages = ceil($total_posts / $limit);
} catch (PDOException $e) {
    die("카운트 조회 오류: " . $e->getMessage());
}

// 5. 게시글 목록 조회 (users 테이블 조인, 파일 첨부 여부 확인용 LEFT JOIN)
try {
    // board_images에 데이터가 있으면 has_image가 1 이상이 됨
    $list_sql = "
                SELECT b.id, b.title, b.view_count, b.created_at, b.writer_id,
                (SELECT COUNT(*) FROM board_images bi WHERE bi.board_id = b.id) as has_image
                FROM boards b
                WHERE $where_clause
                ORDER BY b.id DESC
                LIMIT :limit OFFSET :offset
                ";
    
    $stmt = $pdo->prepare($list_sql);
    // LIMIT, OFFSET은 PDO에서 정수형으로 바인딩해야 함
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("목록 조회 오류: " . $e->getMessage());
}

// 부모 디렉토리의 헤더 포함
include '../includes/header.php'; 
?>

<style>
/* ── 공통 ── */
.board-wrap {
    width: 100%;
    max-width: 1100px;
    margin: 110px auto 80px;
    padding: 0 24px;
}

/* ── 상단 헤더 (archive.php 스타일) ── */
.board-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    border-bottom: 2px solid #1a1a1a;
    padding-bottom: 18px;
    margin-bottom: 20px;
}
.board-title {
    font-family: 'Noto Serif KR', serif;
    font-size: 26px;
    font-weight: 500;
    color: #1a1a1a;
    letter-spacing: -.4px;
}
.board-sub {
    font-size: 13px;
    color: #aaa;
    margin-top: 4px;
}
.board-search-wrap { position: relative; }
.board-search-wrap input {
    border: 1px solid #ddd;
    border-radius: 999px;
    padding: 8px 36px 8px 16px;
    font-size: 13px;
    color: #1a1a1a;
    background: #fff;
    outline: none;
    width: 220px;
    font-family: sans-serif;
    transition: border-color .15s;
}
.board-search-wrap input:focus { border-color: #1a1a1a; }
.board-search-wrap .si {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 15px;
    color: #ccc;
}

/* ── 게시판 테이블 ── */
.board-table {
    width: 100%;
    border-collapse: collapse;
}
.board-table th {
    font-size: 12px;
    font-weight: 500;
    color: #bbb;
    padding: 14px 10px;
    border-bottom: 1px solid #eee;
    white-space: nowrap;
    text-align: center;
}
.board-table td {
    padding: 22px 10px;
    border-bottom: 1px solid #f0f0f0;
    vertical-align: middle;
    font-size: 14px;
    color: #555;
    text-align: center;
}
.board-table tbody tr:hover { background: #fafafa; }

/* 컬럼별 너비 및 정렬 */
.td-num { width: 60px; color: #ccc !important; font-size: 12px !important; font-variant-numeric: tabular-nums; }
.td-title { text-align: left !important; }
.td-title a {
    font-family: 'Noto Serif KR', serif;
    font-size: 17px;
    font-weight: 400;
    color: #1a1a1a;
    text-decoration: none;
    transition: color .15s;
}
.td-title a:hover { color: #888; text-decoration: underline; }
.td-author { width: 120px; font-size: 13px !important; }
.td-date { width: 100px; color: #bbb !important; font-size: 13px !important; }
.td-views { width: 80px; color: #bbb !important; font-size: 13px !important; }
.icon-image { color: #bbb; font-size: 12px; margin-left: 6px; }

/* ── 빈 상태 ── */
.board-empty {
    text-align: center;
    padding: 80px 0;
    color: #ccc;
    font-family: sans-serif;
    font-size: 14px;
}
.board-empty i { font-size: 34px; display: block; margin-bottom: 12px; color: #e0e0e0; }

/* ── 하단 액션 ── */
.board-actions {
    display: flex;
    justify-content: flex-end;
    margin-top: 24px;
}
.btn-write {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #1a1a1a;
    color: #fff;
    border: 1px solid #1a1a1a;
    border-radius: 999px;
    padding: 8px 24px;
    font-size: 13px;
    text-decoration: none;
    transition: all .15s;
}
.btn-write:hover {
    background: #333;
    color: #fff;
}

/* ── 페이지네이션 ── */
.pagination-wrap { 
    display: flex; 
    justify-content: center; 
    gap: 4px; 
    margin-top: 30px; 
}
.page-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border: 1px solid transparent;
    background: transparent;
    color: #888;
    border-radius: 999px;
    font-size: 13px;
    text-decoration: none;
    transition: all .15s;
}
.page-btn:hover { color: #1a1a1a; background: #f5f5f5; }
.page-btn.active { background: #1a1a1a; color: #fff; }
.page-btn.disabled { opacity: 0.3; pointer-events: none; }

/* ── 모바일 반응형 ── */
@media (max-width: 600px) {
    .board-wrap { margin-top: 80px; padding: 0 15px; }
    .board-top { flex-direction: column; align-items: flex-start; gap: 14px; }
    .board-search-wrap, .board-search-wrap input { width: 100%; }
    
    /* 모바일에서는 불필요한 정보 숨김 */
    .td-num, .td-author, .td-views, .th-num, .th-author, .th-views { display: none; }
    .td-title { padding-left: 0; font-size: 15px; }
    .td-date { text-align: right !important; padding-right: 0; }
}
</style>

<div class="board-wrap">

    <div class="board-top">
        <div>
            <div class="board-title"><?= htmlspecialchars($board_name) ?></div>
            <div class="board-sub">원하는 이야기를 자유롭게 나누어 보세요</div>
        </div>
        
        <form action="list.php" method="GET" class="board-search-wrap">
            <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">
            <input 
                type="text" 
                name="search" 
                value="<?= htmlspecialchars($search) ?>" 
                placeholder="제목/내용 검색">
            <i class="bi bi-search si"></i>
        </form>
    </div>

    <table class="board-table">
        <thead>
            <tr>
                <th class="th-num">#</th>
                <th>제목</th>
                <th class="th-author">작성자</th>
                <th>등록일</th>
                <th class="th-views">조회</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($posts)): ?>
                <tr>
                    <td colspan="5" style="border-bottom: none;">
                        <div class="board-empty">
                            <i class="bi bi-journal-x"></i>
                            <?= $search !== '' ? '검색 결과가 없습니다.' : '등록된 게시글이 없습니다.' ?>
                        </div>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($posts as $post): ?>
                    <tr>
                        <td class="td-num"><?= $post['id'] ?></td>
                        <td class="td-title">
                            <a href="view.php?id=<?= $post['id'] ?>&type=<?= $type ?>">
                                <?= htmlspecialchars($post['title']) ?>
                            </a>
                            <?php if ($post['has_image'] > 0): ?>
                                <i class="bi bi-image icon-image" title="사진 첨부됨"></i>
                            <?php endif; ?>
                        </td>
                        <td class="td-author">
                            <?php echo "익명"; // 또는 작성자 이름 ?>
                        </td>
                        <td class="td-date">
                            <?php 
                            $created = strtotime($post['created_at']);
                            echo (date('Y-m-d') === date('Y-m-d', $created)) ? date('H:i', $created) : date('Y.m.d', $created);
                            ?>
                        </td>
                        <td class="td-views"><?= number_format($post['view_count']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="board-actions">
        <a href="write.php?type=<?= $type ?>" class="btn-write">
            <i class="bi bi-pencil-fill"></i> 글쓰기
        </a>
    </div>

    <?php if ($total_pages > 1): ?>
    <div class="pagination-wrap">
        <a class="page-btn <?= ($page <= 1) ? 'disabled' : '' ?>" 
           href="?type=<?= $type ?>&page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>">
            <i class="bi bi-chevron-left"></i>
        </a>
        
        <?php 
        $block_size = 5;
        $start_page = floor(($page - 1) / $block_size) * $block_size + 1;
        $end_page = min($total_pages, $start_page + $block_size - 1);

        for ($i = $start_page; $i <= $end_page; $i++): 
        ?>
            <a class="page-btn <?= ($i === $page) ? 'active' : '' ?>" 
               href="?type=<?= $type ?>&page=<?= $i ?>&search=<?= urlencode($search) ?>">
                <?= $i ?>
            </a>
        <?php endfor; ?>

        <a class="page-btn <?= ($page >= $total_pages) ? 'disabled' : '' ?>" 
           href="?type=<?= $type ?>&page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>">
            <i class="bi bi-chevron-right"></i>
        </a>
    </div>
    <?php endif; ?>

</div>

<?php include '../includes/footer.php'; ?>
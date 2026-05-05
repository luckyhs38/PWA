<?php
// /board/list.php
require_once '../includes/db.php';

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
.board-wrapper { max-width: 900px; 
    margin: 0 auto; 
    padding: 0 15px 40px 15px; 
    align-self: flex-start; /* 수직 중앙 정렬 해제 */
    margin-top: 120px; /* 상단 여백 (원하는 간격에 맞춰 px 조절 가능) */
}
.board-title { font-family: 'Noto Serif KR', serif; font-weight: 500; font-size: 28px; margin-bottom: 30px; }
.table th { font-weight: 500; color: #555; background-color: #f8f9fa; border-bottom: 2px solid #ddd; }
.table td { vertical-align: middle; font-weight: 300; font-size: 15px; }
.table-hover tbody tr:hover { background-color: #fcfcfc; }
.title-link { color: #1a1a1a; text-decoration: none; transition: color 0.2s; }
.title-link:hover { color: #666; text-decoration: underline; }
.pagination .page-link { color: #555; border: none; font-weight: 300; }
.pagination .page-item.active .page-link { background-color: #333; color: #fff; border-radius: 4px; }
.icon-image { color: #888; font-size: 13px; margin-left: 5px; }
</style>

<div class="board-wrapper w-100">
    <h2 class="board-title"><?= htmlspecialchars($board_name) ?></h2>

    <!-- 게시글 목록 테이블 -->
    <div class="table-responsive mb-4">
        <table class="table table-hover text-center align-middle mb-0">
            <colgroup>
                <col style="width: 8%;">
                <col style="width: 50%;">
                <col style="width: 15%;">
                <col style="width: 15%;">
                <col style="width: 12%;">
            </colgroup>
            <thead>
                <tr>
                    <th>번호</th>
                    <th>제목</th>
                    <th>작성자</th>
                    <th>등록일</th>
                    <th>조회</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($posts)): ?>
                    <tr>
                        <td colspan="5" class="py-5 text-muted">등록된 게시글이 없습니다.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($posts as $post): ?>
                        <tr>
                            <td class="text-muted small"><?= $post['id'] ?></td>
                            <td class="text-start">
                                <a href="view.php?id=<?= $post['id'] ?>&type=<?= $type ?>" class="title-link">
                                    <?= htmlspecialchars($post['title']) ?>
                                </a>
                                <?php if ($post['has_image'] > 0): ?>
                                    <i class="bi bi-image icon-image" title="사진 첨부됨"></i>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                // 익명 게시판 분기 처리 해제
                                // if ($type === 'anonymity') {
                                    echo "익명";
                                // } else {
                                //     echo htmlspecialchars($post['nickname']);
                                // }
                                ?>
                            </td>
                            <td class="text-muted small">
                                <?php 
                                // 오늘 쓴 글은 시간만, 예전 글은 날짜만 표시
                                $created = strtotime($post['created_at']);
                                echo (date('Y-m-d') === date('Y-m-d', $created)) ? date('H:i', $created) : date('Y.m.d', $created);
                                ?>
                            </td>
                            <td class="text-muted small"><?= number_format($post['view_count']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- 하단 버튼 및 검색 영역 -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <!-- 글쓰기 버튼 -->
        <div>
            <a href="write.php?type=<?= $type ?>" class="btn btn-dark px-4 font-weight-light">글쓰기</a>
        </div>

        <!-- 검색 폼 -->
        <form action="list.php" method="GET" class="d-flex" style="max-width: 300px;">
            <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">
            <input type="text" name="search" class="form-control form-control-sm me-2" placeholder="제목/내용 검색" value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="btn btn-outline-secondary btn-sm text-nowrap">검색</button>
        </form>
    </div>

    <!-- 페이징 처리 -->
    <?php if ($total_pages > 1): ?>
    <nav>
        <ul class="pagination justify-content-center">
            <!-- 이전 페이지 -->
            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                <a class="page-link" href="?type=<?= $type ?>&page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>">
                    <i class="bi bi-chevron-left"></i>
                </a>
            </li>
            
            <!-- 페이지 번호 -->
            <?php 
            // 페이징 블록 (5페이지씩 표시)
            $block_size = 5;
            $start_page = floor(($page - 1) / $block_size) * $block_size + 1;
            $end_page = min($total_pages, $start_page + $block_size - 1);

            for ($i = $start_page; $i <= $end_page; $i++): 
            ?>
                <li class="page-item <?= ($i === $page) ? 'active' : '' ?>">
                    <a class="page-link" href="?type=<?= $type ?>&page=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>

            <!-- 다음 페이지 -->
            <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                <a class="page-link" href="?type=<?= $type ?>&page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </li>
        </ul>
    </nav>
    <?php endif; ?>

</div>

<?php include '../includes/footer.php'; ?>
<?php
// /board/view.php
require_once '../includes/db.php';

// 1. 파라미터 검증 (글 번호와 게시판 타입)
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$type = $_GET['type'] ?? 'anonymity';
$allowed_types = ['anonymity' => '익명글', 'writing' => '작가만의 방'];

if ($id === 0 || !array_key_exists($type, $allowed_types)) {
    echo "<script>alert('잘못된 접근입니다.'); history.back();</script>";
    exit;
}

$board_name = $allowed_types[$type];

try {
    // 2. 조회수 1 증가
    $update_sql = "UPDATE boards SET view_count = view_count + 1 WHERE id = :id";
    $stmt = $pdo->prepare($update_sql);
    $stmt->execute([':id' => $id]);

    // 3. 게시글 데이터 가져오기 (삭제되지 않은 글만)
    $sql = $sql = "SELECT * FROM boards WHERE id = :id AND board_type = :type AND hidden_yn = 'N'"; // 수정됨
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id, ':type' => $type]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$post) {
        echo "<script>alert('존재하지 않거나 삭제된 게시글입니다.'); location.href='list.php?type={$type}';</script>";
        exit;
    }

    // 4. 연결된 첨부 이미지들 가져오기
    $img_sql = "SELECT * FROM board_images WHERE board_id = :board_id ORDER BY id ASC";
    $stmt = $pdo->prepare($img_sql);
    $stmt->execute([':board_id' => $id]);
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("게시글 조회 오류: " . $e->getMessage());
}

// 헤더 포함
include '../includes/header.php';
?>

<style>
.board-wrapper { 
    max-width: 900px; 
    margin: 0 auto; 
    padding: 0 15px 40px 15px; 
    align-self: flex-start; /* 수직 중앙 정렬 해제 */
    margin-top: 120px;      /* 상단 여백 유지 */
}
.board-header { border-bottom: 2px solid #1a1a1a; padding-bottom: 20px; margin-bottom: 30px; }
.board-title { font-family: 'Noto Serif KR', serif; font-size: 26px; font-weight: 500; color: #1a1a1a; margin-bottom: 15px; }
.board-info { display: flex; justify-content: space-between; color: #666; font-size: 14px; font-weight: 300; }
.board-content { min-height: 300px; font-size: 16px; font-weight: 300; line-height: 1.8; color: #333; margin-bottom: 40px; }
.attached-image { max-width: 100%; height: auto; margin-bottom: 20px; border-radius: 4px; border: 1px solid #eee; }
.btn-group-custom { display: flex; gap: 10px; justify-content: center; border-top: 1px solid #eee; padding-top: 30px; }
</style>

<div class="board-wrapper w-100">
    <!-- 게시판 이름 표시 및 목록 링크 -->
    <h5 class="text-muted mb-4" style="font-weight: 300;">
        <a href="list.php?type=<?= $type ?>" class="text-decoration-none text-muted">
            <?= htmlspecialchars($board_name) ?>
        </a>
    </h5>

    <!-- 글 제목 및 정보 -->
    <div class="board-header">
        <h2 class="board-title"><?= htmlspecialchars($post['title']) ?></h2>
        <div class="board-info">
            <div>
                <!-- 작성자는 무조건 익명으로 고정 -->
                <span class="me-3"><i class="bi bi-person me-1"></i>익명</span>
                <span><i class="bi bi-clock me-1"></i><?= $post['created_at'] ?></span>
            </div>
            <div>
                <span><i class="bi bi-eye me-1"></i><?= number_format($post['view_count']) ?></span>
            </div>
        </div>
    </div>

    <!-- 글 본문 -->
    <div class="board-content">
        <!-- 텍스트 출력 (nl2br로 엔터 키 줄바꿈 반영) -->
        <div class="mb-5">
            <?= nl2br(htmlspecialchars($post['content'])) ?>
        </div>

        <!-- 첨부 이미지 출력 -->
        <?php if (!empty($images)): ?>
            <div class="image-container text-center">
                <?php foreach ($images as $img): ?>
                    <img src="<?= htmlspecialchars($img['file_path']) ?>" 
                         alt="<?= htmlspecialchars($img['file_name']) ?>" 
                         class="attached-image">
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- 하단 버튼 영역 -->
    <div class="btn-group-custom">
        <button type="button" class="btn btn-outline-secondary px-4" onclick="location.href='list.php?type=<?= $type ?>'">목록</button>
        
        <?php 
        // 현재 로그인한 사용자가 이 글의 작성자인 경우에만 수정/삭제 버튼 노출
        if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $post['user_id']): 
        ?>
            <!-- 글쓰기 페이지를 수정용으로 재활용 (id 값 전달) -->
            <button type="button" class="btn btn-outline-dark px-4" onclick="location.href='write.php?type=<?= $type ?>&id=<?= $post['id'] ?>'">수정</button>
            <button type="button" class="btn btn-danger px-4" onclick="deletePost(<?= $post['id'] ?>, '<?= $type ?>')">삭제</button>
        <?php endif; ?>
    </div>
</div>

<script>
function deletePost(id, type) {
    if (confirm('정말 이 게시글을 삭제하시겠습니까?\n삭제된 글은 복구할 수 없습니다.')) {
        // 추후 생성할 delete.php로 이동
        location.href = 'delete.php?id=' + id + '&type=' + type;
    }
}
</script>

<?php include '../includes/footer.php'; ?>
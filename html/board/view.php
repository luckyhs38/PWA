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

    // 5. 댓글 + 대댓글 목록 가져오기
    $comment_sql = "
        SELECT 
            c.id,
            c.board_id,
            c.user_id,
            c.parent_id,
            c.content,
            c.created_at,
            u.nickname
        FROM comments c
        LEFT JOIN users u ON c.user_id = u.id
        WHERE c.board_id = :board_id
        AND c.hidden_yn = 'N'
        AND c.deleted_at IS NULL
        ORDER BY 
            COALESCE(c.parent_id, c.id) ASC,
            CASE WHEN c.parent_id IS NULL THEN 0 ELSE 1 END ASC,
            c.id ASC
    ";

    $stmt = $pdo->prepare($comment_sql);
    $stmt->execute([':board_id' => $id]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

.post-content {
    font-size: 16px;
    font-weight: 300;
    line-height: 1.9;
    color: #333;
    word-break: break-word;
}

.post-content p {
    margin-bottom: 1rem;
}

.post-content img {
    max-width: 100%;
    height: auto;
    display: block;
    margin: 20px auto;
}

.post-content a {
    color: #1a1a1a;
    text-decoration: underline;
}

.post-content ul,
.post-content ol {
    padding-left: 1.5rem;
    margin-bottom: 1rem;
}

/* 댓글 css */
.comment-section {
    border-top: 1px solid #eee;
    margin-top: 40px;
    padding-top: 30px;
}

.comment-title {
    font-family: 'Noto Serif KR', serif;
    font-size: 20px;
    font-weight: 500;
    margin-bottom: 20px;
}

.comment-item {
    border-bottom: 1px solid #f0f0f0;
    padding: 16px 0;
}

.comment-item.reply {
    margin-left: 42px;
    padding-left: 16px;
    border-left: 2px solid #eee;
}

.comment-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
    font-size: 13px;
    color: #777;
}

.comment-content {
    font-size: 15px;
    font-weight: 300;
    line-height: 1.7;
    color: #333;
    white-space: pre-wrap;
    word-break: break-word;
}

.comment-actions {
    display: flex;
    gap: 10px;
    align-items: center;
}

.comment-action-btn {
    border: none;
    background: none;
    color: #999;
    font-size: 13px;
    padding: 0;
}

.comment-action-btn:hover {
    color: #1a1a1a;
}

.comment-delete-btn:hover {
    color: #dc3545;
}

.comment-form textarea,
.reply-form textarea {
    min-height: 90px;
    resize: vertical;
    font-size: 14px;
    line-height: 1.7;
}

.reply-form {
    margin-top: 12px;
    display: none;
}

.reply-label {
    font-size: 13px;
    color: #999;
    margin-right: 6px;
}

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
        <div class="mb-5 post-content">
            <?= $post['content'] ?>
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

    <!-- 댓글 영역 -->
    <div class="comment-section">
        <h3 class="comment-title">
            댓글 <?= count($comments) ?>
        </h3>

        <!-- 댓글 목록 -->
        <?php if (empty($comments)): ?>
            <div class="text-muted small mb-4">
                아직 등록된 댓글이 없습니다.
            </div>
        <?php else: ?>
            <?php foreach ($comments as $comment): ?>
                <?php
                    $is_reply = !empty($comment['parent_id']);
                    $is_my_comment = isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === (int)$comment['user_id'];
                ?>

                <div class="comment-item <?= $is_reply ? 'reply' : '' ?>">
                    <div class="comment-meta">
                        <div>
                            <?php if ($is_reply): ?>
                                <span class="reply-label">↳ 답글</span>
                            <?php endif; ?>

                            <?php if ($type === 'anonymity'): ?>
                                익명
                            <?php else: ?>
                                <?= htmlspecialchars($comment['nickname'] ?? '알 수 없음') ?>
                            <?php endif; ?>

                            <span class="ms-2">
                                <?= htmlspecialchars($comment['created_at']) ?>
                            </span>
                        </div>

                        <div class="comment-actions">
                            <!-- 일반 댓글에만 답글 버튼 표시 -->
                            <?php if (isset($_SESSION['user_id']) && !$is_reply): ?>
                                <button 
                                    type="button" 
                                    class="comment-action-btn"
                                    onclick="toggleReplyForm(<?= $comment['id'] ?>)">
                                    답글
                                </button>
                            <?php endif; ?>

                            <!-- 본인 댓글만 삭제 가능 -->
                            <?php if ($is_my_comment): ?>
                                <form method="post" action="comment_delete.php" onsubmit="return confirm('댓글을 삭제하시겠습니까?');" style="display:inline;">
                                    <input type="hidden" name="comment_id" value="<?= $comment['id'] ?>">
                                    <input type="hidden" name="board_id" value="<?= $post['id'] ?>">
                                    <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">
                                    <button type="submit" class="comment-action-btn comment-delete-btn">삭제</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="comment-content">
                        <?= nl2br(htmlspecialchars($comment['content'])) ?>
                    </div>

                    <!-- 대댓글 작성 폼: 일반 댓글 아래에만 표시 -->
                    <?php if (isset($_SESSION['user_id']) && !$is_reply): ?>
                        <form 
                            method="post" 
                            action="comment_save.php" 
                            class="reply-form"
                            id="reply-form-<?= $comment['id'] ?>"
                        >
                            <input type="hidden" name="board_id" value="<?= $post['id'] ?>">
                            <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">
                            <input type="hidden" name="parent_id" value="<?= $comment['id'] ?>">

                            <textarea 
                                name="content" 
                                class="form-control mb-2" 
                                rows="2"
                                placeholder="답글을 입력하세요"
                                maxlength="1000"
                                required></textarea>

                            <div class="text-end">
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleReplyForm(<?= $comment['id'] ?>)">
                                    취소
                                </button>
                                <button type="submit" class="btn btn-dark btn-sm">
                                    답글 등록
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>


        <!-- 일반 댓글 작성 폼 -->
        <div class="comment-form mt-4">
            <?php if (isset($_SESSION['user_id'])): ?>
                <form method="post" action="comment_save.php">
                    <input type="hidden" name="board_id" value="<?= $post['id'] ?>">
                    <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">
                    <input type="hidden" name="parent_id" value="">

                    <div class="mb-3">
                        <textarea 
                            name="content" 
                            class="form-control" 
                            placeholder="댓글을 입력하세요"
                            maxlength="1000"
                            required></textarea>
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-dark btn-sm px-4">
                            댓글 등록
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <div class="alert alert-light border small mb-0">
                    댓글 작성은 로그인 후 가능합니다.
                    <a href="../login.php" class="text-dark fw-bold">로그인</a>
                </div>
            <?php endif; ?>
        </div>
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

function toggleReplyForm(commentId) { //답글버튼 눌렀을 때
    var form = document.getElementById('reply-form-' + commentId);

    if (!form) {return;}
    if (form.style.display === 'block') {form.style.display = 'none';} 
        else {form.style.display = 'block';}
}
</script>
</script>

<?php include '../includes/footer.php'; ?>
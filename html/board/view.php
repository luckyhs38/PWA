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
    // 2. [수정] 글 먼저 조회 후 존재 확인 → 그 다음 조회수 증가
    $sql = "SELECT * FROM boards WHERE id = :id AND board_type = :type AND hidden_yn = 'N'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id, ':type' => $type]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$post) {
        echo "<script>alert('존재하지 않거나 삭제된 게시글입니다.'); location.href='list.php?type={$type}';</script>";
        exit;
    }

    // 3. 조회수 1 증가 (글 존재 확인 후)
    $update_sql = "UPDATE boards SET view_count = view_count + 1 WHERE id = :id";
    $stmt = $pdo->prepare($update_sql);
    $stmt->execute([':id' => $id]);

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
    align-self: flex-start;
    margin-top: 120px;
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
.post-content p { margin-bottom: 1rem; }
.post-content img { max-width: 100%; height: auto; display: block; margin: 20px auto; }
.post-content a { color: #1a1a1a; text-decoration: underline; }
.post-content ul,
.post-content ol { padding-left: 1.5rem; margin-bottom: 1rem; }

/* 댓글 */
.comment-section { border-top: 1px solid #ddd; margin-top: 48px; padding-top: 28px; }
.comment-title { font-family: 'Noto Serif KR', serif; font-size: 19px; font-weight: 500; margin-bottom: 18px; color: #1a1a1a; }

.comment-item { border: 1px solid #eee; border-radius: 8px; padding: 14px 16px; margin-bottom: 10px; background: #fff; }
.comment-item.reply { margin-left: 28px; background: #fafafa; border-left: 3px solid #ddd; }

.comment-meta { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 10px; font-size: 13px; color: #777; }
.comment-meta > div:first-child::before { content: none; }
.comment-meta > div:first-child { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; }

.reply-label { font-size: 12px; color: #777; background: #f1f1f1; border-radius: 999px; padding: 2px 7px; }

.comment-content { font-size: 15px; font-weight: 300; line-height: 1.7; color: #333; word-break: break-word; }

.comment-actions { display: flex; gap: 6px; align-items: center; }
.comment-action-btn { border: 1px solid #ddd; background: #fff; color: #666; font-size: 12px; padding: 5px 9px; border-radius: 999px; line-height: 1; cursor: pointer; }
.comment-action-btn:hover { border-color: #111; color: #111; }
.comment-delete-btn:hover { border-color: #dc3545; color: #dc3545; }

.reply-form { margin-top: 12px; padding: 12px; background: #fafafa; border: 1px solid #eee; border-radius: 8px; display: none; }
.comment-form { margin-top: 24px; padding: 16px; background: #fafafa; border: 1px solid #eee; border-radius: 8px; }
.comment-form textarea,
.reply-form textarea { min-height: 80px; resize: vertical; font-size: 14px; font-weight: 300; line-height: 1.7; border: 1px solid #ddd; border-radius: 6px; padding: 10px 12px; }
.comment-form textarea:focus,
.reply-form textarea:focus { border-color: #111; box-shadow: none; }

/* [추가] 댓글 삭제 모달 */
.modal-backdrop-custom {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.35);
    z-index: 1050;
    align-items: center;
    justify-content: center;
}
.modal-backdrop-custom.show {
    display: flex;
}
.modal-box {
    background: #fff;
    border-radius: 12px;
    padding: 28px 28px 20px;
    width: 320px;
    max-width: 90vw;
    box-shadow: 0 8px 30px rgba(0,0,0,0.12);
}
.modal-box-title {
    font-size: 15px;
    font-weight: 500;
    color: #1a1a1a;
    margin-bottom: 8px;
}
.modal-box-desc {
    font-size: 13px;
    color: #888;
    line-height: 1.6;
    margin-bottom: 20px;
}
.modal-box-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}
.modal-btn {
    font-size: 13px;
    padding: 7px 16px;
    border-radius: 6px;
    cursor: pointer;
    border: 1px solid #ddd;
    background: #fff;
    color: #333;
}
.modal-btn:hover { background: #f5f5f5; }
.modal-btn-danger {
    background: #fff0f0;
    border-color: #f5c6c6;
    color: #dc3545;
    font-weight: 500;
}
.modal-btn-danger:hover { background: #ffe0e0; }

/* 드래그 저장 팝업 */
#quote-popup {
    display: none;
    position: absolute;
    z-index: 999;
    transform: translateX(-50%);
    filter: drop-shadow(0 4px 12px rgba(0,0,0,0.15));
}
#quote-popup button {
    background: #1a1a1a;
    color: #fff;
    border: none;
    border-radius: 999px;
    padding: 7px 16px;
    font-size: 12px;
    font-family: sans-serif;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 6px;
}
#quote-popup button:hover { background: #333; }
#quote-popup::after {
    content: '';
    display: block;
    width: 0; height: 0;
    border-left: 7px solid transparent;
    border-right: 7px solid transparent;
    border-top: 7px solid #1a1a1a;
    margin: 0 auto;
}

/* 토스트 */
#quote-toast {
    display: none;
    position: fixed;
    bottom: 32px;
    left: 50%;
    transform: translateX(-50%);
    background: #1a1a1a;
    color: #fff;
    font-size: 13px;
    font-family: sans-serif;
    padding: 10px 20px;
    border-radius: 999px;
    z-index: 9999;
    opacity: 0;
    transition: opacity .2s;
}
#quote-toast.show { opacity: 1; }

/* 모바일 */
@media (max-width: 575.98px) {
    .comment-item { padding: 13px 14px; }
    .comment-item.reply { margin-left: 16px; }
    .comment-meta { flex-direction: column; align-items: flex-start; gap: 8px; }
    .comment-actions { width: 100%; justify-content: flex-end; }
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
                <span class="me-3"><i class="bi bi-person me-1"></i>익명</span>
                <span><i class="bi bi-clock me-1"></i><?= htmlspecialchars($post['created_at']) ?></span>
            </div>
            <div>
                <span><i class="bi bi-eye me-1"></i><?= number_format($post['view_count']) ?></span>
            </div>
        </div>
    </div>

    <!-- 글 본문 -->
    <div class="board-content">
        <div class="mb-5 post-content" id="post-content">
            <?php
            /*
             * [주의] 리치 에디터(TinyMCE 등)로 작성된 HTML을 저장한 경우:
             *   → HTMLPurifier 같은 라이브러리로 화이트리스트 필터링 후 출력하세요.
             *   → 현재는 raw HTML 그대로 출력 중이라 XSS 위험이 있습니다.
             *
             * 일반 텍스트로 저장한 경우:
             *   → 아래 주석 처리된 줄로 교체하세요.
             *   <?= nl2br(htmlspecialchars($post['content'])) ?>
             */
            echo $post['content'];
            ?>
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

                            <!-- [수정] 본인 댓글 삭제: confirm → 모달 -->
                            <?php if ($is_my_comment): ?>
                                <form 
                                    method="post" 
                                    action="comment_delete.php" 
                                    id="comment-delete-form-<?= $comment['id'] ?>"
                                    style="display:inline;">
                                    <input type="hidden" name="comment_id" value="<?= $comment['id'] ?>">
                                    <input type="hidden" name="board_id" value="<?= $post['id'] ?>">
                                    <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">
                                    <button 
                                        type="button" 
                                        class="comment-action-btn comment-delete-btn"
                                        onclick="openCommentDeleteModal(<?= $comment['id'] ?>)">
                                        삭제
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="comment-content"><?= nl2br(htmlspecialchars(trim($comment['content']))) ?></div>

                    <!-- 대댓글 작성 폼 -->
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
        
        <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $post['user_id']): ?>
            <button type="button" class="btn btn-outline-dark px-4" onclick="location.href='write.php?type=<?= $type ?>&id=<?= $post['id'] ?>'">수정</button>
            <button type="button" class="btn btn-danger px-4" onclick="openPostDeleteModal(<?= $post['id'] ?>, '<?= $type ?>')">삭제</button>
        <?php endif; ?>
    </div>
    <!-- 드래그 팝업 -->
    <div id="quote-popup">
        <button type="button" onclick="saveQuote()">
            <i class="bi bi-bookmark"></i> 문장 저장
        </button>
    </div>

    <!-- 토스트 -->
    <div id="quote-toast"></div>

</div>

<!-- [추가] 댓글 삭제 모달 -->
<div class="modal-backdrop-custom" id="commentDeleteModal">
    <div class="modal-box">
        <div class="modal-box-title">댓글을 삭제할까요?</div>
        <div class="modal-box-desc">삭제된 댓글은 복구할 수 없습니다.</div>
        <div class="modal-box-actions">
            <button class="modal-btn" onclick="closeCommentDeleteModal()">취소</button>
            <button class="modal-btn modal-btn-danger" onclick="confirmCommentDelete()">삭제</button>
        </div>
    </div>
</div>

<!-- [추가] 게시글 삭제 모달 -->
<div class="modal-backdrop-custom" id="postDeleteModal">
    <div class="modal-box">
        <div class="modal-box-title">게시글을 삭제할까요?</div>
        <div class="modal-box-desc">삭제된 글은 복구할 수 없습니다.</div>
        <div class="modal-box-actions">
            <button class="modal-btn" onclick="closePostDeleteModal()">취소</button>
            <button class="modal-btn modal-btn-danger" onclick="confirmPostDelete()">삭제</button>
        </div>
    </div>
</div>

<script>
/* ── 댓글 삭제 모달 ── */
var _commentDeleteTargetId = null;

function openCommentDeleteModal(commentId) {
    _commentDeleteTargetId = commentId;
    document.getElementById('commentDeleteModal').classList.add('show');
}

function closeCommentDeleteModal() {
    _commentDeleteTargetId = null;
    document.getElementById('commentDeleteModal').classList.remove('show');
}

function confirmCommentDelete() {
    if (_commentDeleteTargetId !== null) {
        document.getElementById('comment-delete-form-' + _commentDeleteTargetId).submit();
    }
    closeCommentDeleteModal();
}

/* ── 게시글 삭제 모달 ── */
var _postDeleteId   = null;
var _postDeleteType = null;

function openPostDeleteModal(id, type) {
    _postDeleteId   = id;
    _postDeleteType = type;
    document.getElementById('postDeleteModal').classList.add('show');
}

function closePostDeleteModal() {
    document.getElementById('postDeleteModal').classList.remove('show');
}

function confirmPostDelete() {
    if (_postDeleteId !== null) {
        location.href = 'delete.php?id=' + _postDeleteId + '&type=' + _postDeleteType;
    }
    closePostDeleteModal();
}

/* ── 배경 클릭 시 모달 닫기 ── */
document.querySelectorAll('.modal-backdrop-custom').forEach(function(el) {
    el.addEventListener('click', function(e) {
        if (e.target === el) {
            el.classList.remove('show');
        }
    });
});

/* ── 답글 폼 토글 ── */
function toggleReplyForm(commentId) {
    var form = document.getElementById('reply-form-' + commentId);
    if (!form) { return; }
    form.style.display = form.style.display === 'block' ? 'none' : 'block';
}

/* ── 드래그 문장 저장 ── */
<?php if (isset($_SESSION['user_id'])): ?>

var _quoteData = null;

document.getElementById('post-content').addEventListener('mouseup', function() {
    setTimeout(handleSelection, 10); // 선택 확정 후 실행
});

// 모바일 터치 지원
document.getElementById('post-content').addEventListener('touchend', function() {
    setTimeout(handleSelection, 100);
});

function handleSelection() {
    var sel = window.getSelection();
    var text = sel ? sel.toString().trim() : '';

    if (!text || text.length < 5) {
        hideQuotePopup();
        return;
    }
    if (text.length > 300) {
        showToast('300자 이내로 선택해주세요');
        hideQuotePopup();
        return;
    }

    // 선택 범위 위치 계산
    var range    = sel.getRangeAt(0);
    var rect     = range.getBoundingClientRect();
    var content  = document.getElementById('post-content');
    var contRect = content.getBoundingClientRect();

    // offset 계산 (글 본문 텍스트 기준)
    var preRange = document.createRange();
    preRange.selectNodeContents(content);
    preRange.setEnd(range.startContainer, range.startOffset);
    var startOffset = preRange.toString().length;
    var endOffset   = startOffset + text.length;

    _quoteData = {
        board_id:     <?= $post['id'] ?>,
        content:      text,
        start_offset: startOffset,
        end_offset:   endOffset,
    };

    // 팝업 위치: 선택 영역 위쪽 중앙
    var popup = document.getElementById('quote-popup');
    popup.style.display = 'block';
    popup.style.top  = (window.scrollY + rect.top - popup.offsetHeight - 10) + 'px';
    popup.style.left = (window.scrollX + rect.left + rect.width / 2) + 'px';
}

function hideQuotePopup() {
    document.getElementById('quote-popup').style.display = 'none';
    _quoteData = null;
}

function saveQuote() {
    if (!_quoteData) return;

    fetch('quote_save.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(_quoteData)
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        hideQuotePopup();
        if (data.success) {
            showToast(data.already ? '이미 저장한 문장이에요' : '내 서랍에 저장했어요 ✓');
        } else {
            showToast(data.msg || '저장에 실패했습니다');
        }
    })
    .catch(function() {
        showToast('오류가 발생했습니다');
    });

    window.getSelection().removeAllRanges();
}

// 본문 외 클릭 시 팝업 닫기
document.addEventListener('mousedown', function(e) {
    var popup = document.getElementById('quote-popup');
    if (popup.style.display === 'block' && !popup.contains(e.target)) {
        hideQuotePopup();
    }
});

function showToast(msg) {
    var toast = document.getElementById('quote-toast');
    toast.textContent = msg;
    toast.style.display = 'block';
    setTimeout(function() { toast.classList.add('show'); }, 10);
    setTimeout(function() {
        toast.classList.remove('show');
        setTimeout(function() { toast.style.display = 'none'; }, 200);
    }, 2500);
}

<?php endif; ?>

</script>

<?php include '../includes/footer.php'; ?>
<?php
// /qna/qna_view.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    echo "<script>alert('잘못된 접근입니다.'); history.back();</script>";
    exit;
}

// 임시 관리자 기준: users.id = 1
$is_admin = isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === 3;

try {
    $sql = "
        SELECT
            q.*,
            u.nickname,
            u.user_id AS login_id
        FROM qna q
        JOIN users u ON q.user_id = u.id
        WHERE q.id = :id
          AND q.deleted_at IS NULL
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id]);
    $qna = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$qna) {
        echo "<script>alert('존재하지 않는 문의입니다.'); location.href='qna.php';</script>";
        exit;
    }

    $is_writer = isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === (int)$qna['user_id'];
    $is_private = $qna['private_yn'] === 'Y';

    // 비공개 문의 접근 제어
    if ($is_private && !$is_writer && !$is_admin) {
        echo "<script>alert('비공개 문의는 작성자와 관리자만 확인할 수 있습니다.'); location.href='qna.php';</script>";
        exit;
    }

    $pdo->prepare("UPDATE qna SET view_count = view_count + 1 WHERE id = :id")
        ->execute([':id' => $id]);

} catch (PDOException $e) {
    die("문의 조회 오류: " . $e->getMessage());
}

include '../includes/header.php';
?>

<style>
.qna-view-wrap {
    width: 100%;
    max-width: 1100px;
    margin: 110px auto 80px;
    padding: 0 24px;
}

.qna-view-header {
    border-bottom: 2px solid #1a1a1a;
    padding-bottom: 20px;
    margin-bottom: 30px;
}

.badge-line {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    margin-bottom: 12px;
}

.qna-view-status,
.qna-private-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    border-radius: 999px;
    padding: 6px 12px;
    font-size: 12px;
}

.qna-view-status.pending {
    background: #fafafa;
    border: 1px solid #ddd;
    color: #777;
}

.qna-view-status.answered {
    background: #1a1a1a;
    border: 1px solid #1a1a1a;
    color: #fff;
}

.qna-private-badge {
    background: #fafafa;
    border: 1px solid #ddd;
    color: #777;
}

.qna-view-title {
    font-family: 'Noto Serif KR', serif;
    font-size: 26px;
    font-weight: 500;
    color: #1a1a1a;
    margin-bottom: 14px;
    line-height: 1.45;
}

.qna-view-meta {
    font-size: 13px;
    color: #999;
}

.qna-box {
    border-bottom: 1px solid #eee;
    padding: 0 0 34px;
    margin-bottom: 34px;
}

.qna-box-label {
    font-family: 'Noto Serif KR', serif;
    font-size: 18px;
    font-weight: 500;
    margin-bottom: 18px;
    color: #1a1a1a;
}

.qna-content {
    font-size: 15px;
    font-weight: 300;
    line-height: 1.9;
    color: #333;
    white-space: pre-line;
    word-break: break-word;
}

.answer-box {
    background: #fafafa;
    border: 1px solid #eee;
    border-radius: 10px;
    padding: 26px;
}

.answer-empty {
    color: #999;
    font-size: 14px;
    line-height: 1.7;
}

.admin-answer-box {
    border: 1px solid #eee;
    border-radius: 10px;
    padding: 26px;
    margin-bottom: 34px;
}

.admin-answer-form textarea {
    min-height: 180px;
    resize: vertical;
    font-size: 14px;
    line-height: 1.7;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 12px 14px;
}

.admin-answer-form textarea:focus {
    border-color: #1a1a1a;
    box-shadow: none;
}

.qna-view-bottom {
    display: flex;
    justify-content: center;
    gap: 10px;
    border-top: 1px solid #eee;
    padding-top: 28px;
}

@media (max-width: 600px) {
    .qna-view-wrap {
        margin-top: 80px;
        padding: 0 15px;
    }

    .qna-view-title {
        font-size: 22px;
    }

    .answer-box,
    .admin-answer-box {
        padding: 20px;
    }

    .qna-view-bottom {
        flex-direction: column;
    }

    .qna-view-bottom .btn {
        width: 100%;
    }
}

/* 문의 삭제 모달 */
.qna-modal-backdrop {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.35);
    z-index: 1050;
    align-items: center;
    justify-content: center;
}

.qna-modal-backdrop.show {
    display: flex;
}

.qna-modal-box {
    background: #fff;
    border-radius: 12px;
    padding: 28px 28px 20px;
    width: 320px;
    max-width: 90vw;
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
}

.qna-modal-title {
    font-size: 15px;
    font-weight: 500;
    color: #1a1a1a;
    margin-bottom: 8px;
}

.qna-modal-desc {
    font-size: 13px;
    color: #888;
    line-height: 1.6;
    margin-bottom: 20px;
}

.qna-modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}

.qna-modal-btn {
    font-size: 13px;
    padding: 7px 16px;
    border-radius: 6px;
    cursor: pointer;
    border: 1px solid #ddd;
    background: #fff;
    color: #333;
}

.qna-modal-btn:hover {
    background: #f5f5f5;
}

.qna-modal-btn.danger {
    background: #fff0f0;
    border-color: #f5c6c6;
    color: #dc3545;
    font-weight: 500;
}

.qna-modal-btn.danger:hover {
    background: #ffe0e0;
}

</style>

<div class="qna-view-wrap">

    <div class="qna-view-header">
        <div class="badge-line">
            <?php if ($qna['status'] === 'answered'): ?>
                <span class="qna-view-status answered">답변완료</span>
            <?php else: ?>
                <span class="qna-view-status pending">답변대기</span>
            <?php endif; ?>

            <?php if ($qna['private_yn'] === 'Y'): ?>
                <span class="qna-private-badge">
                    <i class="bi bi-lock"></i> 비공개
                </span>
            <?php else: ?>
                <span class="qna-private-badge">
                    공개
                </span>
            <?php endif; ?>
        </div>

        <div class="qna-view-title">
            <?= htmlspecialchars($qna['title']) ?>
        </div>

        <div class="qna-view-meta">
            <?= htmlspecialchars($qna['nickname'] ?? $qna['login_id']) ?>
            · <?= htmlspecialchars($qna['created_at']) ?>
            · 조회 <?= number_format($qna['view_count']) ?>
        </div>
    </div>

    <div class="qna-box">
        <div class="qna-box-label">문의 내용</div>
        <div class="qna-content"><?= htmlspecialchars($qna['content']) ?></div>
    </div>

    <div class="qna-box answer-box">
        <div class="qna-box-label">관리자 답변</div>

        <?php if ($qna['status'] === 'answered' && !empty($qna['answer_content'])): ?>
            <div class="qna-content"><?= htmlspecialchars($qna['answer_content']) ?></div>
            <div class="qna-view-meta mt-3">
                답변일시: <?= htmlspecialchars($qna['answered_at']) ?>
            </div>
        <?php else: ?>
            <div class="answer-empty">
                아직 등록된 답변이 없습니다.<br>
                관리자가 확인 후 답변을 등록하면 답변완료 상태로 변경됩니다.
            </div>
        <?php endif; ?>
    </div>

    <?php if ($is_admin): ?>
        <div class="admin-answer-box">
            <div class="qna-box-label">
                <?= $qna['status'] === 'answered' ? '답변 수정' : '관리자 답변 등록' ?>
            </div>

            <form method="post" action="qna_answer.php" class="admin-answer-form">
                <input type="hidden" name="id" value="<?= (int)$qna['id'] ?>">

                <textarea 
                    name="answer_content" 
                    class="form-control mb-3"
                    placeholder="답변 내용을 입력하세요"><?= htmlspecialchars($qna['answer_content'] ?? '') ?></textarea>

                <div class="text-end">
                    <?php if ($qna['status'] === 'answered'): ?>
                        <button 
                            type="button" 
                            class="btn btn-outline-danger px-4 me-2"
                            onclick="if(confirm('등록된 답변을 정말 삭제하시겠습니까?')) location.href='qna_answer_delete.php?id=<?= $qna['id'] ?>';">
                            답변 삭제
                        </button>
                    <?php endif; ?>

                    <button type="submit" class="btn btn-dark px-4">
                        <?= $qna['status'] === 'answered' ? '답변 수정' : '답변 저장' ?>
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <div class="qna-view-bottom">
        <button type="button" class="btn btn-outline-secondary px-4" onclick="location.href='qna.php'">
            목록
        </button>

        <?php if ($is_writer || $is_admin): ?>
            <button 
                type="button" 
                class="btn btn-outline-danger px-4"
                onclick="openQnaDeleteModal(<?= (int)$qna['id'] ?>)">
                삭제
            </button>
        <?php endif; ?>
    </div>

</div>

<!-- 문의 삭제 모달 -->
<div class="qna-modal-backdrop" id="qnaDeleteModal">
    <div class="qna-modal-box">
        <div class="qna-modal-title">문의를 삭제할까요?</div>
        <div class="qna-modal-desc">
            삭제된 문의는 목록에서 보이지 않습니다.<br>
            계속 진행하시겠습니까?
        </div>

        <div class="qna-modal-actions">
            <button type="button" class="qna-modal-btn" onclick="closeQnaDeleteModal()">
                취소
            </button>
            <button type="button" class="qna-modal-btn danger" onclick="confirmQnaDelete()">
                삭제
            </button>
        </div>
    </div>
</div>

<script>
var qnaDeleteId = null;

function openQnaDeleteModal(id) {
    qnaDeleteId = id;
    document.getElementById('qnaDeleteModal').classList.add('show');
}

function closeQnaDeleteModal() {
    qnaDeleteId = null;
    document.getElementById('qnaDeleteModal').classList.remove('show');
}

function confirmQnaDelete() {
    if (qnaDeleteId === null) {
        return;
    }

    location.href = 'qna_delete.php?id=' + qnaDeleteId;
}

document.getElementById('qnaDeleteModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeQnaDeleteModal();
    }
});
</script>

<?php include '../includes/footer.php'; ?>
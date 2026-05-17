<?php
// /qna/qna_write.php
require_once '../includes/auth_check.php';
require_login(); // 로그인 필수

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    echo "<script>alert('로그인 후 이용 가능합니다.'); location.href='login.php';</script>";
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $private_yn = $_POST['private_yn'] ?? 'N';

    if (!in_array($private_yn, ['Y', 'N'])) {
        $private_yn = 'N';
    }

    if ($title === '') {
        $errors[] = '제목을 입력해주세요.';
    } elseif (mb_strlen($title) > 200) {
        $errors[] = '제목은 200자 이내로 입력해주세요.';
    }

    if ($content === '') {
        $errors[] = '문의 내용을 입력해주세요.';
    }

    if (empty($errors)) {
        try {
            $sql = "
                INSERT INTO qna (
                    user_id,
                    title,
                    content,
                    private_yn,
                    status,
                    created_at
                ) VALUES (
                    :user_id,
                    :title,
                    :content,
                    :private_yn,
                    'pending',
                    NOW()
                )
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':user_id' => $_SESSION['user_id'],
                ':title' => $title,
                ':content' => $content,
                ':private_yn' => $private_yn
            ]);

            $qna_id = $pdo->lastInsertId();

            echo "<script>alert('문의가 등록되었습니다.'); location.href='qna_view.php?id={$qna_id}';</script>";
            exit;

        } catch (PDOException $e) {
            $errors[] = '문의 등록 중 오류가 발생했습니다.';
        }
    }
}

include '../includes/header.php';
?>

<style>
.qna-write-wrap {
    width: 100%;
    max-width: 1100px;
    margin: 110px auto 80px;
    padding: 0 24px;
}

.qna-write-head {
    border-bottom: 2px solid #1a1a1a;
    padding-bottom: 18px;
    margin-bottom: 30px;
}

.qna-write-title {
    font-family: 'Noto Serif KR', serif;
    font-size: 26px;
    font-weight: 500;
    color: #1a1a1a;
}

.qna-write-sub {
    font-size: 13px;
    color: #999;
    margin-top: 5px;
}

.qna-write-form label {
    font-size: 14px;
    color: #333;
    margin-bottom: 8px;
}

.qna-write-form input[type="text"],
.qna-write-form textarea {
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 12px 14px;
    font-size: 14px;
}

.qna-write-form textarea {
    min-height: 260px;
    resize: vertical;
    line-height: 1.7;
}

.qna-write-form input:focus,
.qna-write-form textarea:focus {
    border-color: #1a1a1a;
    box-shadow: none;
}

.qna-visibility-box {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}

.qna-radio {
    display: flex;
    gap: 10px;
    align-items: flex-start;
    border: 1px solid #e5e5e5;
    border-radius: 10px;
    padding: 14px;
    cursor: pointer;
    background: #fff;
    transition: border-color 0.15s ease;
}

.qna-radio:hover {
    border-color: #1a1a1a;
}

.qna-radio input {
    margin-top: 4px;
}

.qna-radio strong {
    display: block;
    font-size: 14px;
    color: #1a1a1a;
    margin-bottom: 4px;
}

.qna-radio small {
    display: block;
    font-size: 12px;
    color: #999;
    line-height: 1.5;
}

@media (max-width: 600px) {
    .qna-write-wrap {
        margin-top: 80px;
        padding: 0 15px;
    }

    .qna-visibility-box {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="qna-write-wrap">

    <div class="qna-write-head">
        <div class="qna-write-title">문의 작성</div>
        <div class="qna-write-sub">문의 공개 여부를 선택한 후 내용을 작성해주세요.</div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $error): ?>
                <div><?= htmlspecialchars($error) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post" action="qna_write.php" class="qna-write-form">
        <div class="mb-3">
            <label class="form-label">제목</label>
            <input 
                type="text" 
                name="title" 
                class="form-control"
                maxlength="200"
                value="<?= htmlspecialchars($_POST['title'] ?? '') ?>"
                placeholder="문의 제목을 입력하세요">
        </div>

        <div class="mb-3">
            <label class="form-label">공개 여부</label>

            <div class="qna-visibility-box">
                <label class="qna-radio">
                    <input 
                        type="radio" 
                        name="private_yn" 
                        value="N"
                        <?= ($_POST['private_yn'] ?? 'N') === 'N' ? 'checked' : '' ?>>
                    <span>
                        <strong>공개 문의</strong>
                        <small>목록에서 제목이 공개됩니다.</small>
                    </span>
                </label>

                <label class="qna-radio">
                    <input 
                        type="radio" 
                        name="private_yn" 
                        value="Y"
                        <?= ($_POST['private_yn'] ?? 'N') === 'Y' ? 'checked' : '' ?>>
                    <span>
                        <strong>비공개 문의</strong>
                        <small>목록에서 제목이 숨겨지고 작성자와 관리자만 확인할 수 있습니다.</small>
                    </span>
                </label>
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label">문의 내용</label>
            <textarea 
                name="content" 
                class="form-control"
                placeholder="문의 내용을 입력하세요"><?= htmlspecialchars($_POST['content'] ?? '') ?></textarea>
        </div>

        <div class="d-flex justify-content-between">
            <button type="button" class="btn btn-outline-secondary px-4" onclick="history.back();">
                취소
            </button>
            <button type="submit" class="btn btn-dark px-4">
                등록
            </button>
        </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?>
<?php
// /board/write.php
require_once '../includes/db.php';
require_once '../includes/auth_check.php'; // 로그인 확인

// 1. 게시판 타입 검증
$allowed_types = ['anonymity' => '익명글', 'writing' => '작가만의 방'];
$type = $_GET['type'] ?? 'anonymity';

if (!array_key_exists($type, $allowed_types)) {
    echo "<script>alert('잘못된 접근입니다.'); history.back();</script>";
    exit;
}

$board_name = $allowed_types[$type];

// 2. POST 처리 (글 저장)
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title   = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $post_type = $_POST['type'] ?? $type;

    // 유효성 검사
    if ($title === '') {
        $errors[] = '제목을 입력해주세요.';
    } elseif (mb_strlen($title) > 100) {
        $errors[] = '제목은 100자 이내로 입력해주세요.';
    }

    if ($content === '') {
        $errors[] = '내용을 입력해주세요.';
    }

    if (!array_key_exists($post_type, $allowed_types)) {
        $errors[] = '잘못된 게시판 타입입니다.';
    }

    // 에러 없으면 DB 저장
    // ※ 이미지는 Summernote가 업로드 시점에 image_upload.php로 먼저 저장하고
    //   content HTML 안에 <img src="..."> 로 포함되어 함께 저장됨
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // 게시글 저장
            $sql = "INSERT INTO boards (user_id, board_type, title, content, hidden_yn, created_at)
                    VALUES (:user_id, :board_type, :title, :content, 'N', NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':user_id'    => $_SESSION['user_id'],
                ':board_type' => $post_type,
                ':title'      => $title,
                ':content'    => $content,
            ]);
            $board_id = $pdo->lastInsertId();

            $pdo->commit();

            echo "<script>alert('게시글이 등록되었습니다.'); location.href='view.php?id={$board_id}&type={$post_type}';</script>";
            exit;

        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = '저장 중 오류가 발생했습니다. 다시 시도해주세요.';
        }
    }
}

include '../includes/header.php';
// ※ header.php 안에 아래 CDN이 포함되어 있어야 합니다:
// Bootstrap CSS/JS, jQuery (필수!)
// <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.css" rel="stylesheet">
// <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.js"></script>
// <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/lang/summernote-ko-KR.min.js"></script>
?>

<style>
.write-wrapper {
    max-width: 900px;
    margin: 0 auto;
    padding: 0 15px 60px 15px;
    align-self: flex-start;
    margin-top: 120px;
}
.write-title {
    font-family: 'Noto Serif KR', serif;
    font-weight: 500;
    font-size: 28px;
    margin-bottom: 30px;
}
.form-label {
    font-weight: 400;
    font-size: 14px;
    color: #555;
    margin-bottom: 6px;
}
.form-control {
    border: 1px solid #ddd;
    border-radius: 4px;
    font-weight: 300;
    font-size: 15px;
    transition: border-color 0.2s;
}
.form-control:focus {
    border-color: #1a1a1a;
    box-shadow: none;
}
textarea.form-control {
    resize: vertical;
    min-height: 300px;
    line-height: 1.8;
}
.error-box {
    background: #fff5f5;
    border: 1px solid #fcc;
    border-radius: 4px;
    padding: 12px 16px;
    margin-bottom: 24px;
    font-size: 14px;
    color: #c0392b;
}
/* Summernote 커스텀 */
.note-editor.note-frame {
    border: 1px solid #ddd;
    border-radius: 4px;
}
.note-editor.note-frame .note-toolbar {
    background: #f8f9fa;
    border-bottom: 1px solid #ddd;
}
.note-editor.note-frame.focused {
    border-color: #1a1a1a;
    box-shadow: none;
}
.btn-submit {
    background: #1a1a1a;
    color: #fff;
    border: none;
    padding: 10px 32px;
    border-radius: 4px;
    font-weight: 300;
    font-size: 15px;
    transition: background 0.2s;
}
.btn-submit:hover { background: #333; color: #fff; }
.btn-cancel {
    border: 1px solid #ddd;
    background: #fff;
    color: #555;
    padding: 10px 24px;
    border-radius: 4px;
    font-weight: 300;
    font-size: 15px;
    transition: border-color 0.2s;
}
.btn-cancel:hover { border-color: #999; color: #333; }
.char-count {
    font-size: 12px;
    color: #999;
    text-align: right;
    margin-top: 4px;
}
</style>

<div class="write-wrapper w-100">
    <h2 class="write-title">글쓰기</h2>

    <!-- 에러 메시지 -->
    <?php if (!empty($errors)): ?>
        <div class="error-box">
            <?php foreach ($errors as $err): ?>
                <div>· <?= htmlspecialchars($err) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="write.php?type=<?= htmlspecialchars($type) ?>" enctype="multipart/form-data">
        <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">

        <!-- 제목 -->
        <div class="mb-4">
            <label class="form-label" for="title">제목</label>
            <input 
                type="text" 
                class="form-control" 
                id="title" 
                name="title" 
                placeholder="제목을 입력하세요"
                maxlength="100"
                value="<?= htmlspecialchars($_POST['title'] ?? '') ?>"
                oninput="updateCharCount(this, 'title-count', 100)"
            >
            <div class="char-count">
                <span id="title-count"><?= mb_strlen($_POST['title'] ?? '') ?></span> / 100
            </div>
        </div>

        <!-- 내용 (Summernote) -->
        <div class="mb-5">
            <label class="form-label" for="content">내용</label>
            <textarea id="content" name="content"><?= $_POST['content'] ?? '' ?></textarea>
        </div>

        <!-- 버튼 -->
        <div class="d-flex gap-2 justify-content-center border-top pt-4">
            <button type="button" class="btn-cancel" onclick="history.back()">취소</button>
            <button type="submit" class="btn-submit">등록</button>
        </div>
    </form>
</div>

<script>
// 제목 글자 수 카운터
function updateCharCount(input, countId, max) {
    const len = input.value.length;
    document.getElementById(countId).textContent = len;
    input.style.borderColor = len >= max ? '#e74c3c' : '';
}

// Summernote 초기화
$('#content').summernote({
    lang: 'ko-KR',
    height: 350,
    placeholder: '내용을 입력하세요',
    toolbar: [
        ['style',  ['bold', 'italic', 'underline', 'strikethrough', 'clear']],
        ['font',   ['fontsize']],
        ['color',  ['color']],
        ['para',   ['ul', 'ol', 'paragraph']],
        ['insert', ['picture', 'link', 'hr']],
        ['view',   ['fullscreen', 'codeview']],
    ],
    callbacks: {
        // 이미지를 에디터에 드래그/붙여넣기/삽입 시 서버에 업로드
        onImageUpload: function(files) {
            Array.from(files).forEach(file => {
                const formData = new FormData();
                formData.append('image', file);

                $.ajax({
                    url: '../board/image_upload.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(res) {
                        try {
                            const data = JSON.parse(res);
                            if (data.url) {
                                $('#content').summernote('insertImage', data.url);
                            } else {
                                alert('이미지 업로드 실패: ' + (data.error || '알 수 없는 오류'));
                            }
                        } catch(e) {
                            alert('이미지 업로드 중 오류가 발생했습니다.');
                        }
                    },
                    error: function() {
                        alert('이미지 업로드 중 오류가 발생했습니다.');
                    }
                });
            });
        }
    }
});

// 폼 제출 전 summernote 내용 동기화 (빈 내용 체크)
$('form').on('submit', function() {
    const content = $('#content').summernote('isEmpty');
    if (content) {
        alert('내용을 입력해주세요.');
        return false;
    }
});
</script>

<?php include '../includes/footer.php'; ?>
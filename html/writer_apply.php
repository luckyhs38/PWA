<?php
// /writer_apply.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'includes/db.php';
require_once 'includes/auth.php';

// 로그인 필수
require_login($pdo);

$role    = get_current_role($pdo);
$user_id = (int)$_SESSION['user_id'];

// 이미 작가/관리자면 접근 불필요
if (in_array($role, ['writer', 'admin'])) {
    echo "<script>alert('이미 작가 권한을 보유하고 있습니다.'); location.href='/';</script>";
    exit;
}

$error   = '';
$success = false;

// 가장 최근 신청 내역 확인
try {
    $stmt = $pdo->prepare("
        SELECT status, reject_reason, created_at
        FROM writer_applications
        WHERE user_id = :uid
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([':uid' => $user_id]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $existing = null;
}

// POST 처리 (신청 제출)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reason = trim($_POST['reason'] ?? '');

    if ($reason === '') {
        $error = '신청 사유를 입력해주세요.';
    } elseif (mb_strlen($reason) < 10) {
        $error = '신청 사유를 10자 이상 입력해주세요.';
    } elseif (mb_strlen($reason) > 1000) {
        $error = '신청 사유는 1000자 이내로 입력해주세요.';
    } else {
        try {
            // 대기 중인 신청이 이미 있으면 차단
            if ($existing && $existing['status'] === 'pending') {
                $error = '이미 심사 중인 신청이 있습니다.';
            } else {
                $pdo->prepare("
                    INSERT INTO writer_applications (user_id, reason)
                    VALUES (:uid, :reason)
                ")->execute([
                    ':uid'    => $user_id,
                    ':reason' => $reason,
                ]);
                $success = true;
            }
        } catch (PDOException $e) {
            $error = '신청 처리 중 오류가 발생했습니다.';
            error_log($e->getMessage());
        }
    }
}

include 'includes/header.php';
?>

<style>
.join-wrapper {
    width: 100%;
    min-height: 80vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px 0;
}
.join-card {
    width: 450px;
    border-radius: 12px;
    background-color: #fff;
    box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
}
@media (max-width: 490px) {
    .join-card { width: calc(100% - 40px); }
}
.status-icon { font-size: 32px; display: block; margin-bottom: 10px; }
.status-pending  { color: #f5a623; }
.status-rejected { color: #dc3545; }
.status-success  { color: #4caf50; }

.reject-box {
    background: #fff5f5;
    border: 1px solid #fcc;
    border-radius: 8px;
    padding: 12px 14px;
    font-size: 13px;
    color: #dc3545;
    margin-bottom: 20px;
    line-height: 1.6;
    font-family: sans-serif;
}
.reject-box strong { display: block; margin-bottom: 4px; }

.notice-box {
    background: #fafafa;
    border: 1px solid #f0f0f0;
    border-radius: 8px;
    padding: 12px 14px;
    font-size: 12px;
    color: #999;
    line-height: 1.8;
    margin-bottom: 20px;
    font-family: sans-serif;
}
.char-count {
    font-size: 12px;
    color: #bbb;
    text-align: right;
    margin-top: 4px;
    font-family: sans-serif;
}
</style>

<div class="join-wrapper">
    <div class="card border-0 join-card">
        <div class="card-body p-4">

            <?php if ($success): ?>
            <!-- ── 신청 완료 ── -->
            <div class="text-center py-3">
                <i class="bi bi-check-circle status-icon status-success"></i>
                <h5 class="mb-2">신청이 완료되었습니다</h5>
                <p class="text-muted small mb-4">
                    관리자 검토 후 승인 처리됩니다.<br>
                    보통 1~3일 내로 결과를 알려드릴게요.
                </p>
                <a href="/" class="btn btn-dark btn-sm px-4">홈으로</a>
            </div>

            <?php elseif ($existing && $existing['status'] === 'pending'): ?>
            <!-- ── 심사 중 ── -->
            <div class="text-center py-3">
                <i class="bi bi-hourglass-split status-icon status-pending"></i>
                <h5 class="mb-2">심사가 진행 중입니다</h5>
                <p class="text-muted small mb-1">
                    신청일: <?= date('Y.m.d', strtotime($existing['created_at'])) ?>
                </p>
                <p class="text-muted small mb-4">
                    관리자 검토 후 결과를 알려드릴게요.
                </p>
                <a href="/" class="btn btn-outline-secondary btn-sm px-4">홈으로</a>
            </div>

            <?php else: ?>
            <!-- ── 신청 폼 (최초 or 거절 후 재신청) ── -->
            <h4 class="text-center mb-1">작가 신청</h4>
            <p class="text-center text-muted small mb-4">심사 후 작가 권한을 부여해드립니다</p>

            <!-- 거절 사유 (재신청 시에만 표시) -->
            <?php if ($existing && $existing['status'] === 'rejected'): ?>
                <div class="reject-box">
                    <strong>이전 신청이 반려되었습니다</strong>
                    <?php if (!empty($existing['reject_reason'])): ?>
                        반려 사유: <?= htmlspecialchars($existing['reject_reason']) ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- 에러 -->
            <?php if ($error): ?>
                <div class="alert alert-danger py-2 small">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- 안내 -->
            <div class="notice-box">
                · 심사는 보통 1~3일 내로 완료됩니다.<br>
                · 승인 후 작가만의 방 게시판에 글을 작성할 수 있습니다.<br>
                · 허위 정보 기재 시 신청이 거절될 수 있습니다.
            </div>

            <form method="post" action="writer_apply.php" id="applyForm">

                <div class="mb-3">
                    <label class="form-label small">신청 사유</label>
                    <textarea
                        class="form-control"
                        name="reason"
                        id="reason"
                        rows="6"
                        placeholder="작가 신청 사유를 자세히 적어주세요. (10자 이상)"
                        maxlength="1000"
                        oninput="updateCount()"
                        required><?= htmlspecialchars($_POST['reason'] ?? '') ?></textarea>
                    <div class="char-count">
                        <span id="reason-count"><?= mb_strlen($_POST['reason'] ?? '') ?></span> / 1000
                    </div>
                </div>

                <button type="submit" class="btn btn-dark w-100 mb-3">신청하기</button>

                <p class="text-center small text-muted mb-0">
                    <a href="/" class="text-muted text-decoration-none">취소하고 돌아가기</a>
                </p>

            </form>
            <?php endif; ?>

        </div>
    </div>
</div>

<script>
function updateCount() {
    var val = document.getElementById('reason').value;
    document.getElementById('reason-count').textContent = [...val].length;
}

document.getElementById('applyForm') &&
document.getElementById('applyForm').addEventListener('submit', function(e) {
    var reason = document.getElementById('reason').value.trim();
    if ([...reason].length < 10) {
        e.preventDefault();
        alert('신청 사유를 10자 이상 입력해주세요.');
        document.getElementById('reason').focus();
    }
});
</script>

<?php include 'includes/footer.php'; ?>
<?php
// mypage.php
require_once 'includes/auth_check.php'; // 세션 시작 및 인증/유효성 검증 완료
require_once 'includes/db.php';         // DB 연결 (이후 쿼리 실행용)

$user_pk = $_SESSION['user_id'];
$error   = '';

// 현재 사용자 정보 불러오기
try {
    $stmt = $pdo->prepare(
        "SELECT user_id, name, nickname, email, phone
         FROM users WHERE id = :id AND status = 1 AND deleted_at IS NULL"
    );
    $stmt->execute(['id' => $user_pk]);
    $current_user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$current_user) {
        session_destroy();
        echo "<script>alert('유효하지 않은 사용자입니다.'); location.href='login.php';</script>";
        exit;
    }
} catch (PDOException $e) {
    die("DB 오류: " . $e->getMessage());
}

// =====================
// 정보 수정 (POST)
// =====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action           = $_POST['action']           ?? 'update';
    $password         = $_POST['password']         ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $name             = trim($_POST['name']         ?? '');
    $nickname         = trim($_POST['nickname']     ?? '');
    $email            = trim($_POST['email']        ?? '');
    $phone            = trim($_POST['phone']        ?? '');

    // =====================
    // 회원탈퇴 처리
    // =====================
    if ($action === 'withdraw') {
        try {
            $stmt = $pdo->prepare(
                "UPDATE users
                 SET status = 0, deleted_at = NOW()
                 WHERE id = :id"
            );
            $stmt->execute(['id' => $user_pk]);

            // 세션 완전 삭제
            $_SESSION = [];
            if (isset($_COOKIE[session_name()])) {
                setcookie(session_name(), '', time() - 3600, '/');
            }
            session_destroy();

            echo "<script>alert('탈퇴가 완료되었습니다. 이용해주셔서 감사합니다.'); location.href='index.php';</script>";
            exit;

        } catch (PDOException $e) {
            $error = "탈퇴 처리 중 오류가 발생했습니다.";
            error_log("탈퇴 오류: " . $e->getMessage());
        }
    }

    // =====================
    // 정보 수정 처리
    // =====================
    if ($action === 'update') {

        if (empty($name) || empty($nickname) || empty($email) || empty($phone)) {
            $error = "이름, 닉네임, 이메일, 연락처는 필수 입력 항목입니다.";

        } else {
            try {
                // 중복 검사 (본인 제외)
                $stmt = $pdo->prepare(
                    "SELECT nickname, email FROM users
                     WHERE (nickname = :nickname OR email = :email)
                       AND id != :id"
                );
                $stmt->execute([
                    'nickname' => $nickname,
                    'email'    => $email,
                    'id'       => $user_pk
                ]);

                $duplicates   = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $is_duplicate = false;

                foreach ($duplicates as $row) {
                    if ($row['nickname'] === $nickname) {
                        $error = "이미 사용 중인 닉네임입니다.";
                        $is_duplicate = true;
                        break;
                    }
                    if ($row['email'] === $email) {
                        $error = "이미 가입된 이메일입니다.";
                        $is_duplicate = true;
                        break;
                    }
                }

                if (!$is_duplicate) {

                    // 비밀번호 변경 포함
                    if (!empty($password)) {
                        if (strlen($password) < 8) {
                            $error = "새 비밀번호는 8자 이상이어야 합니다.";
                        } elseif ($password !== $password_confirm) {
                            $error = "새 비밀번호가 일치하지 않습니다.";
                        } else {
                            $hashed = password_hash($password, PASSWORD_DEFAULT);
                            $stmt   = $pdo->prepare(
                                "UPDATE users
                                 SET password = :password, name = :name,
                                     nickname = :nickname, email = :email, phone = :phone
                                 WHERE id = :id"
                            );
                            $stmt->execute([
                                'password' => $hashed,
                                'name'     => $name,
                                'nickname' => $nickname,
                                'email'    => $email,
                                'phone'    => $phone,
                                'id'       => $user_pk
                            ]);
                        }

                    // 비밀번호 변경 없음
                    } else {
                        $stmt = $pdo->prepare(
                            "UPDATE users
                             SET name = :name, nickname = :nickname,
                                 email = :email, phone = :phone
                             WHERE id = :id"
                        );
                        $stmt->execute([
                            'name'     => $name,
                            'nickname' => $nickname,
                            'email'    => $email,
                            'phone'    => $phone,
                            'id'       => $user_pk
                        ]);
                    }

                    if (empty($error)) {
                        // 세션 닉네임 최신화
                        $_SESSION['nickname'] = $nickname;

                        echo "<script>
                            alert('회원 정보가 수정되었습니다.');
                            location.href='mypage.php';
                        </script>";
                        exit;
                    }
                }

            } catch (PDOException $e) {
                $error = "정보 수정 중 서버 오류가 발생했습니다.";
                error_log("정보 수정 오류: " . $e->getMessage());
            }
        }
    }
}
?>

<?php include 'includes/header.php'; ?>

<style>
.mypage-wrapper {
    width: 100%;
    min-height: 80vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px 0;
}

.mypage-card {
    width: 450px;
    border-radius: 12px;
    background-color: #fff;
    box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
}

@media (max-width: 490px) {
    .mypage-card { width: calc(100% - 40px); }
}

/* 탈퇴 버튼 */
.btn-withdraw {
    background: none;
    border: none;
    color: #bbb;
    font-size: 12px;
    text-decoration: underline;
    cursor: pointer;
    padding: 0;
}

.btn-withdraw:hover { color: #dc3545; }
</style>

<div class="mypage-wrapper">
    <div class="card border-0 mypage-card">
        <div class="card-body p-4">

            <h4 class="text-center mb-1">내 정보 수정</h4>
            <p class="text-center text-muted small mb-4">안전하게 정보를 관리하세요</p>

            <!-- 에러 메시지 -->
            <?php if ($error): ?>
            <div class="alert alert-danger py-2 small">
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <!-- 정보 수정 폼 -->
            <form action="mypage.php" method="POST" id="mypageForm">
                <input type="hidden" name="action" value="update">

                <!-- 아이디 (수정 불가) -->
                <div class="mb-3">
                    <label class="form-label small text-muted">아이디 (수정 불가)</label>
                    <input type="text"
                           class="form-control bg-light"
                           value="<?= htmlspecialchars($current_user['user_id']) ?>"
                           readonly>
                </div>

                <!-- 새 비밀번호 (선택) -->
                <div class="mb-3">
                    <label class="form-label small">새 비밀번호</label>
                    <input type="password"
                           class="form-control"
                           id="password"
                           name="password"
                           placeholder="변경하려면 8자 이상 입력 (선택)">
                    <small id="password_feedback"
                           class="form-text text-muted">
                        비밀번호를 변경하지 않으려면 비워두세요.
                    </small>
                </div>

                <!-- 새 비밀번호 확인 -->
                <div class="mb-3">
                    <label class="form-label small">새 비밀번호 확인</label>
                    <input type="password"
                           class="form-control"
                           id="password_confirm"
                           name="password_confirm"
                           placeholder="새 비밀번호를 한 번 더 입력">
                    <small id="password_confirm_feedback" class="form-text"></small>
                </div>

                <!-- 이름 -->
                <div class="mb-3">
                    <label class="form-label small">이름</label>
                    <input type="text"
                           class="form-control"
                           name="name"
                           value="<?= htmlspecialchars($current_user['name']) ?>"
                           required>
                </div>

                <!-- 닉네임 -->
                <div class="mb-3">
                    <label class="form-label small">닉네임</label>
                    <input type="text"
                           class="form-control"
                           name="nickname"
                           value="<?= htmlspecialchars($current_user['nickname']) ?>"
                           required>
                </div>

                <!-- 이메일 -->
                <div class="mb-3">
                    <label class="form-label small">이메일</label>
                    <input type="email"
                           class="form-control"
                           name="email"
                           value="<?= htmlspecialchars($current_user['email']) ?>"
                           required>
                </div>

                <!-- 연락처 -->
                <div class="mb-4">
                    <label class="form-label small">연락처</label>
                    <input type="tel"
                           class="form-control"
                           name="phone"
                           value="<?= htmlspecialchars($current_user['phone']) ?>"
                           oninput="autoHyphen(this)"
                           maxlength="13"
                           required>
                </div>

                <button type="submit" class="btn btn-dark w-100 mb-3">
                    정보 수정하기
                </button>

            </form>

            <!-- 구분선 + 탈퇴 버튼 -->
            <hr class="my-2">
            <div class="text-center mt-2">
                <button class="btn-withdraw"
                        onclick="confirmWithdraw()">
                    회원탈퇴
                </button>
            </div>

            <!-- 탈퇴용 히든 폼 -->
            <form id="withdrawForm" action="mypage.php" method="POST" style="display:none;">
                <input type="hidden" name="action" value="withdraw">
            </form>

        </div>
    </div>
</div>

<script>
/* 전화번호 자동 하이픈 */
function autoHyphen(input) {
    let val = input.value.replace(/[^0-9]/g, '');
    if (val.length < 4) {
        input.value = val;
    } else if (val.length < 7) {
        input.value = val.slice(0,3) + '-' + val.slice(3);
    } else if (val.length < 11) {
        input.value = val.slice(0,3) + '-' + val.slice(3,6) + '-' + val.slice(6);
    } else {
        input.value = val.slice(0,3) + '-' + val.slice(3,7) + '-' + val.slice(7,11);
    }
}

/* 피드백 표시 헬퍼 */
function setFeedback(id, message, isValid) {
    const el = document.getElementById(id);
    el.textContent = message;
    el.className   = isValid ? 'form-text text-success' : 'form-text text-danger';
}

/* 비밀번호 실시간 검증 */
document.getElementById('password').addEventListener('input', function () {
    const val = this.value;

    if (val.length === 0) {
        const fb = document.getElementById('password_feedback');
        fb.textContent = '비밀번호를 변경하지 않으려면 비워두세요.';
        fb.className   = 'form-text text-muted';
        document.getElementById('password_confirm_feedback').textContent = '';
    } else if (val.length < 8) {
        setFeedback('password_feedback', `${val.length}자 입력 중 (8자 이상 필요)`, false);
    } else {
        setFeedback('password_feedback', '사용 가능한 비밀번호입니다. ✓', true);
    }

    const confirmVal = document.getElementById('password_confirm').value;
    if (confirmVal.length > 0) checkPasswordConfirm(confirmVal, val);
});

/* 비밀번호 확인 실시간 검증 */
document.getElementById('password_confirm').addEventListener('input', function () {
    const pw = document.getElementById('password').value;
    if (pw.length > 0) checkPasswordConfirm(this.value, pw);
});

function checkPasswordConfirm(confirmVal, pw) {
    if (confirmVal.length === 0) {
        document.getElementById('password_confirm_feedback').textContent = '';
    } else if (confirmVal !== pw) {
        setFeedback('password_confirm_feedback', '새 비밀번호가 일치하지 않습니다.', false);
    } else {
        setFeedback('password_confirm_feedback', '비밀번호가 일치합니다. ✓', true);
    }
}

/* 폼 제출 전 검증 */
document.getElementById('mypageForm').addEventListener('submit', function (e) {
    const pw      = document.getElementById('password').value;
    const confirm = document.getElementById('password_confirm').value;

    if (pw.length > 0) {
        if (pw.length < 8) {
            e.preventDefault();
            setFeedback('password_feedback', '새 비밀번호는 8자 이상이어야 합니다.', false);
            document.getElementById('password').focus();
            return;
        }
        if (pw !== confirm) {
            e.preventDefault();
            setFeedback('password_confirm_feedback', '새 비밀번호가 일치하지 않습니다.', false);
            document.getElementById('password_confirm').focus();
            return;
        }
    }
});

/* 회원탈퇴 확인 */
function confirmWithdraw() {
    if (confirm('정말 탈퇴하시겠습니까?\n탈퇴 후에는 복구가 불가능합니다.')) {
        document.getElementById('withdrawForm').submit();
    }
}
</script>

<?php include 'includes/footer.php'; ?>
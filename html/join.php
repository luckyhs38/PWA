<?php
// join.php
session_start();
require_once 'includes/db.php';

// ==========================================
// [추가됨] AJAX 아이디 중복확인 통신 처리
// ==========================================
if (isset($_POST['action']) && $_POST['action'] === 'check_id') {
    header('Content-Type: application/json');
    $check_id = trim($_POST['check_user_id'] ?? '');

    // 형식 검증
    if (!preg_match('/^[a-zA-Z0-9_]{4,20}$/', $check_id)) {
        echo json_encode(['status' => 'invalid', 'message' => '형식에 맞지 않는 아이디입니다.']);
        exit;
    }

    try {
        // DB 중복 조회
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $check_id]);
        $count = $stmt->fetchColumn();

        if ($count > 0) {
            echo json_encode(['status' => 'duplicate', 'message' => '이미 사용 중인 아이디입니다.']);
        } else {
            echo json_encode(['status' => 'available', 'message' => '사용 가능한 아이디입니다.']);
        }
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => '서버 오류가 발생했습니다.']);
    }
    exit; // AJAX 요청이므로 여기서 스크립트 실행 종료 (HTML을 그리지 않음)
}
// ==========================================

$error = '';

// 이미 로그인된 상태면 메인으로
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// POST 요청 (가입하기 버튼 클릭 시 최종 검증 및 저장)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    $user_id          = trim($_POST['user_id']          ?? '');
    $password         = $_POST['password']              ?? '';
    $password_confirm = $_POST['password_confirm']      ?? '';
    $name             = trim($_POST['name']             ?? '');
    $nickname         = trim($_POST['nickname']         ?? '');
    $email            = trim($_POST['email']            ?? '');
    $phone            = trim($_POST['phone']            ?? '');

    // 1. 필수 입력값 검증
    if (empty($user_id) || empty($password) || empty($password_confirm) || empty($name) || empty($nickname) || empty($email) || empty($phone)) {
        $error = "모든 항목을 입력해야 합니다.";
    } elseif (!preg_match('/^[a-zA-Z0-9_]{4,20}$/', $user_id)) {
        $error = "아이디는 영문, 숫자, 언더바(_)만 4~20자로 입력해주세요.";
    } elseif (strlen($password) < 8) {
        $error = "비밀번호는 8자 이상이어야 합니다.";
    } elseif ($password !== $password_confirm) {
        $error = "비밀번호가 일치하지 않습니다.";
    } else {
        try {
            // 서버단 최종 중복 검증 (이메일, 닉네임 등 포함)
            $stmt = $pdo->prepare(
                "SELECT user_id, nickname, email FROM users 
                 WHERE user_id = :user_id OR nickname = :nickname OR email = :email"
            );
            $stmt->execute([
                'user_id'  => $user_id,
                'nickname' => $nickname,
                'email'    => $email
            ]);

            $duplicates   = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $is_duplicate = false;

            foreach ($duplicates as $row) {
                if ($row['user_id'] === $user_id) {
                    $error        = "이미 사용 중인 아이디입니다.";
                    $is_duplicate = true;
                    break;
                }
                if ($row['nickname'] === $nickname) {
                    $error        = "이미 사용 중인 닉네임입니다.";
                    $is_duplicate = true;
                    break;
                }
                if ($row['email'] === $email) {
                    $error        = "이미 가입된 이메일입니다.";
                    $is_duplicate = true;
                    break;
                }
            }

            if (!$is_duplicate) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare(
                    "INSERT INTO users (user_id, password, name, nickname, email, phone, status, created_at) 
                     VALUES (:user_id, :password, :name, :nickname, :email, :phone, 1, NOW())"
                );
                $stmt->execute([
                    'user_id'  => $user_id,
                    'password' => $hashed_password,
                    'name'     => $name,
                    'nickname' => $nickname,
                    'email'    => $email,
                    'phone'    => $phone
                ]);

                echo "<script>
                    alert('회원가입이 완료되었습니다.');
                    location.href='login.php';
                </script>";
                exit;
            }
        } catch (PDOException $e) {
            $error = "회원가입 처리 중 서버 오류가 발생했습니다.";
            error_log("회원가입 오류: " . $e->getMessage());
        }
    }
}
?>

<?php include 'includes/header.php'; ?>

<style>
.join-wrapper { width: 100%; min-height: 80vh; display: flex; align-items: center; justify-content: center; padding: 40px 0; }
.join-card { width: 450px; border-radius: 12px; background-color: #fff; box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075); }
@media (max-width: 490px) { .join-card { width: calc(100% - 40px); } }
</style>

<div class="join-wrapper">
    <div class="card border-0 join-card">
        <div class="card-body p-4">
            <h4 class="text-center mb-1">회원가입</h4>
            <p class="text-center text-muted small mb-4">한글은 늘 도망가</p>

            <?php if ($error): ?>
            <div class="alert alert-danger py-2 small">
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <form action="join.php" method="POST" id="joinForm">

                <!-- [수정됨] 아이디 입력 그룹 (버튼 추가) -->
                <div class="mb-3">
                    <label class="form-label small">아이디</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="user_id" name="user_id" placeholder="영문, 숫자, 언더바 4~20자" value="<?= htmlspecialchars($_POST['user_id'] ?? '') ?>" required>
                        <button class="btn btn-outline-secondary" type="button" id="btn_check_id">중복확인</button>
                    </div>
                    <small id="user_id_feedback" class="form-text"></small>
                </div>

                <div class="mb-3">
                    <label class="form-label small">비밀번호</label>
                    <input type="password" class="form-control" id="password" name="password" placeholder="8자 이상" required>
                    <small id="password_feedback" class="form-text"></small>
                </div>

                <div class="mb-3">
                    <label class="form-label small">비밀번호 확인</label>
                    <input type="password" class="form-control" id="password_confirm" name="password_confirm" required>
                    <small id="password_confirm_feedback" class="form-text"></small>
                </div>

                <div class="mb-3">
                    <label class="form-label small">이름</label>
                    <input type="text" class="form-control" name="name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label small">닉네임</label>
                    <input type="text" class="form-control" name="nickname" value="<?= htmlspecialchars($_POST['nickname'] ?? '') ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label small">이메일</label>
                    <input type="email" class="form-control" name="email" placeholder="example@email.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                </div>

                <div class="mb-4">
                    <label class="form-label small">연락처</label>
                    <input type="tel" class="form-control" name="phone" placeholder="010-0000-0000" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" oninput="autoHyphen(this)" maxlength="13" required>
                </div>

                <button type="submit" class="btn btn-dark w-100 mb-3" id="submitBtn">가입하기</button>

                <p class="text-center small text-muted mb-0">
                    이미 계정이 있으신가요? <a href="login.php" class="text-dark fw-bold text-decoration-none">로그인</a>
                </p>
            </form>
        </div>
    </div>
</div>

<script>
// 중복확인 상태 저장 변수
let isIdChecked = false;

/* 피드백 표시 헬퍼 함수 */
function setFeedback(id, message, isValid) {
    const el = document.getElementById(id);
    el.textContent = message;
    el.className = isValid ? 'form-text text-success' : 'form-text text-danger';
}
function clearFeedback(id) {
    const el = document.getElementById(id);
    el.textContent = '';
    el.className = 'form-text';
}

/* 전화번호 자동 하이픈 */
function autoHyphen(input) {
    let val = input.value.replace(/[^0-9]/g, '');
    if (val.length < 4) { input.value = val; } 
    else if (val.length < 7) { input.value = val.slice(0,3) + '-' + val.slice(3); } 
    else if (val.length < 11) { input.value = val.slice(0,3) + '-' + val.slice(3,6) + '-' + val.slice(6); } 
    else { input.value = val.slice(0,3) + '-' + val.slice(3,7) + '-' + val.slice(7,11); }
}

/* =====================
   [추가됨] 아이디 중복확인 이벤트 (AJAX)
===================== */
document.getElementById('btn_check_id').addEventListener('click', function() {
    const userId = document.getElementById('user_id').value;
    const pattern = /^[a-zA-Z0-9_]{4,20}$/;

    if (!pattern.test(userId)) {
        alert('영문, 숫자, 언더바(_)만 4~20자로 입력해주세요.');
        document.getElementById('user_id').focus();
        return;
    }

    // 서버로 데이터 전송
    const formData = new URLSearchParams();
    formData.append('action', 'check_id');
    formData.append('check_user_id', userId);

    fetch('join.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: formData.toString()
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'available') {
            isIdChecked = true; // 중복확인 통과 상태 저장
            setFeedback('user_id_feedback', data.message, true);
            alert(data.message);
        } else {
            isIdChecked = false;
            setFeedback('user_id_feedback', data.message, false);
            alert(data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('중복 확인 중 통신 오류가 발생했습니다.');
    });
});

/* 아이디 입력값 변경 시 중복확인 초기화 */
document.getElementById('user_id').addEventListener('input', function () {
    isIdChecked = false; // 글자를 하나라도 수정하면 다시 확인받도록 강제
    const val = this.value;
    const pattern = /^[a-zA-Z0-9_]{4,20}$/;

    if (val.length === 0) {
        clearFeedback('user_id_feedback');
    } else if (!pattern.test(val)) {
        setFeedback('user_id_feedback', '영문, 숫자, 언더바(_)만 4~20자 가능합니다.', false);
    } else {
        setFeedback('user_id_feedback', '중복확인 버튼을 눌러주세요.', false);
    }
});

/* 비밀번호 실시간 검증 */
document.getElementById('password').addEventListener('input', function () {
    const val = this.value;
    if (val.length === 0) { clearFeedback('password_feedback'); } 
    else if (val.length < 8) { setFeedback('password_feedback', `${val.length}자 입력 중 (8자 이상 필요)`, false); } 
    else { setFeedback('password_feedback', '사용 가능한 비밀번호입니다. ✓', true); }

    const confirmVal = document.getElementById('password_confirm').value;
    if (confirmVal.length > 0) { checkPasswordConfirm(confirmVal, val); }
});

/* 비밀번호 확인 실시간 검증 */
document.getElementById('password_confirm').addEventListener('input', function () {
    checkPasswordConfirm(this.value, document.getElementById('password').value);
});

function checkPasswordConfirm(confirmVal, pw) {
    if (confirmVal.length === 0) { clearFeedback('password_confirm_feedback'); } 
    else if (confirmVal !== pw) { setFeedback('password_confirm_feedback', '비밀번호가 일치하지 않습니다.', false); } 
    else { setFeedback('password_confirm_feedback', '비밀번호가 일치합니다. ✓', true); }
}

/* =====================
   [수정됨] 제출 전 최종 방어 로직
===================== */
document.getElementById('joinForm').addEventListener('submit', function (e) {
    // 1. 중복확인을 통과하지 못했다면 제출 차단
    if (!isIdChecked) {
        e.preventDefault();
        alert('아이디 중복확인을 먼저 해주세요.');
        document.getElementById('user_id').focus();
        return;
    }

    const password = document.getElementById('password').value;
    const confirm = document.getElementById('password_confirm').value;

    if (password.length < 8) {
        e.preventDefault();
        alert('비밀번호는 8자 이상이어야 합니다.');
        document.getElementById('password').focus();
        return;
    }

    if (password !== confirm) {
        e.preventDefault();
        alert('비밀번호가 일치하지 않습니다.');
        document.getElementById('password_confirm').focus();
        return;
    }
});
</script>

<?php include 'includes/footer.php'; ?>
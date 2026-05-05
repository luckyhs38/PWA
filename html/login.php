<?php
// login.php
session_start();
require_once 'includes/db.php';

$error = '';

// 이미 로그인된 상태면 메인으로
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// POST 요청 (로그인 버튼 클릭)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $user_id  = trim($_POST['user_id'] ?? '');
    $password = $_POST['password'] ?? '';

    // 입력값 비었는지 체크
    if (empty($user_id) || empty($password)) {
        $error = "아이디와 비밀번호를 입력해주세요.";

    } else {
        // DB에서 아이디로 회원 찾기
        $stmt = $pdo->prepare(
            "SELECT * FROM users
             WHERE user_id = ?
             AND status = 1
             AND deleted_at IS NULL"
        );
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // 비밀번호 검증
        if ($user && password_verify($password, $user['password'])) {
            // 세션 고정 공격 방어
            session_regenerate_id(true);

            // 세션에 로그인 정보 저장
            $_SESSION['user_id']       = $user['id'];
            $_SESSION['user_login_id'] = $user['user_id'];
            $_SESSION['nickname']      = $user['nickname'];

            header("Location: index.php");
            exit;

        } else {
            $error = "아이디 또는 비밀번호가 올바르지 않습니다.";
        }
    }
}
?>
<?php include 'includes/header.php'; ?>

<style>
/* 로그인 카드 스타일 */
.login-card {
    /* PC/태블릿: 450px 고정 */
    width: 450px; 
    border-radius: 12px;
    background-color: #fff;
}

/* 모바일 대응: 화면이 카드보다 작아질 때만 유동적 변경 */
@media (max-width: 490px) {
    .login-card {
        width: 90%; 
    }
}
</style>

<div class="container-fluid px-0">
  <div class="row justify-content-center align-items-center g-0" style="min-height: 80vh;">
    <div class="col-md-5 col-sm-8 d-flex justify-content-center">
      <div class="card border-0 shadow-sm login-card">
        <div class="card-body p-4">

          <h4 class="text-center mb-1">로그인</h4>
          <p class="text-center text-muted small mb-4">
            한글은 늘 도망가
          </p>
          <!-- 에러 메시지 -->
          <?php if ($error): ?>
          <div class="alert alert-danger py-2 small">
            <?= htmlspecialchars($error) ?>
          </div>
          <?php endif; ?>

          <!-- 로그인 폼 -->
          <form method="POST" action="login.php">

            <div class="mb-3">
              <label class="form-label">아이디</label>
              <input
                type="text"
                name="user_id"
                class="form-control"
                placeholder="아이디 입력"
                value="<?= htmlspecialchars(
                    $_POST['user_id'] ?? ''
                ) ?>"
                required>
            </div>

            <div class="mb-3">
              <label class="form-label">비밀번호</label>
              <input
                type="password"
                name="password"
                class="form-control"
                placeholder="비밀번호 입력"
                required>
            </div>

            <div class="d-grid mt-4">
              <button type="submit"
                      class="btn btn-dark">
                로그인
              </button>
            </div>

          </form>

          <!-- 회원가입 링크 -->
          <p class="text-center small text-muted mt-3 mb-0">
            계정이 없으신가요?
            <a href="join.php"
               class="text-dark fw-bold">
              회원가입
            </a>
          </p>

        </div>
      </div>
    </div>
  </div>
</div>
</body>
<?php include 'includes/footer.php'; ?>
</html>
<?php
// 1. DB 연결 파일 포함
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/db.php';

// 이미 로그인된 상태라면 메인으로 이동
if (isset($_SESSION['user_id'])) {
    header("Location: /index.php");
    exit;
}

$error_msg = "";

// 2. 로그인 버튼을 눌렀을 때 (POST 방식 데이터 전송 시)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id  = mysqli_real_escape_with_string($conn, $_POST['user_id']);
    $password = $_POST['password'];

    // 3. 사용자 정보 조회 (정상 상태인 회원만)
    $sql = "SELECT * FROM users WHERE user_id = '$user_id' AND status = 1";
    $result = mysqli_query($conn, $sql);
    $user = mysqli_fetch_assoc($result);

    if ($user) {
        // 4. 비밀번호 검증
        // 테스트 데이터('1234')와 실제 암호화된 비밀번호를 모두 대응하기 위한 로직
        if ($password === $user['password'] || password_verify($password, $user['password'])) {
            
            // 로그인 성공: 세션에 사용자 정보 저장
            $_SESSION['id']       = $user['id'];
            $_SESSION['user_id']  = $user['user_id'];
            $_SESSION['nickname'] = $user['nickname'];
            $_SESSION['name']     = $user['name'];

            echo "<script>alert('로그인되었습니다.'); location.href='/index.php';</script>";
            exit;
        } else {
            $error_msg = "비밀번호가 일치하지 않습니다.";
        }
    } else {
        $error_msg = "존재하지 않는 아이디이거나 승인 대기 중인 계정입니다.";
    }
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>로그인</title>
    <!-- Bootstrap 5 적용 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; display: flex; align-items: center; height: 100vh; }
        .login-form { width: 100%; max-width: 400px; margin: auto; padding: 15px; background: #fff; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
    </style>
</head>
<body>

<div class="login-form">
    <h2 class="text-center mb-4">로그인</h2>
    
    <?php if ($error_msg): ?>
        <div class="alert alert-danger text-center"><?php echo $error_msg; ?></div>
    <?php endif; ?>

    <form action="login.php" method="POST">
        <div class="mb-3">
            <label for="user_id" class="form-label">아이디</label>
            <input type="text" name="user_id" id="user_id" class="form-label form-control" required>
        </div>
        <div class="mb-3">
            <label for="password" class="form-label">비밀번호</label>
            <input type="password" name="password" id="password" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary w-100">로그인</button>
    </form>
    
    <div class="mt-3 text-center">
        <a href="join.php" class="text-decoration-none">회원가입 하러가기</a>
    </div>
</div>

</body>
</html>
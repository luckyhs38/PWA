<?php
// 1. 세션 시작 (어떤 출력보다도 가장 위에 있어야 함)
session_start();

// 2. DB 연결 파일 불러오기 (사용자님의 PDO 방식 $pdo 객체)
require_once './includes/db.php';

// 3. POST 방식 접근 확인 (직접 URL을 치고 들어오는 것 방지)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "<script>alert('잘못된 접근입니다.'); location.href='login.php';</script>";
    exit;
}

// 4. 입력값 받기 (login.php의 input name 기준)
$user_id = trim($_POST['user_id'] ?? '');
$user_pw = $_POST['user_pw'] ?? '';

// 빈 값 체크
if (empty($user_id) || empty($user_pw)) {
    echo "<script>alert('아이디와 비밀번호를 모두 입력해주세요.'); history.back();</script>";
    exit;
}

try {
    // 5. DB에서 회원 정보 조회 (상태가 'active'인 회원만)
    $sql = "SELECT id, user_id, password, name, nickname FROM users WHERE user_id = :user_id AND status = 'active'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['user_id' => $user_id]);
    
    // 결과를 연관 배열로 가져오기
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // 6. 계정 존재 여부 및 비밀번호 검증
    // password_verify: 사용자가 입력한 평문 비밀번호와 DB의 해시된 비밀번호를 비교 (실무 표준)
    if ($user && password_verify($user_pw, $user['password'])) {
        
        // [중요 보안] 세션 ID 재생성 (세션 고정 공격 방지)
        session_regenerate_id(true);

        // 7. 세션에 사용자 정보 저장 (비밀번호 같은 민감 정보는 절대 저장하지 않음)
        $_SESSION['user_idx'] = $user['id'];      // 고유 번호 (PK)
        $_SESSION['user_id']  = $user['user_id']; // 아이디
        $_SESSION['name']     = $user['name'];    // 이름
        $_SESSION['nickname'] = $user['nickname'];// 닉네임

        // 8. 로그인 성공 시 메인 페이지로 이동
        echo "<script>location.href='index.php';</script>";
        exit;

    } else {
        // 보안상 아이디가 틀렸는지, 비밀번호가 틀렸는지 구체적으로 알려주지 않음
        echo "<script>alert('아이디 또는 비밀번호가 일치하지 않거나 탈퇴한 계정입니다.'); history.back();</script>";
        exit;
    }

} catch (PDOException $e) {
    // 에러 발생 시 사용자에게는 모호한 메시지를 띄우고, 실제 에러는 서버 로그에 남김
    error_log("로그인 에러: " . $e->getMessage());
    echo "<script>alert('서버 오류가 발생했습니다. 잠시 후 다시 시도해주세요.'); history.back();</script>";
    exit;
}
?>
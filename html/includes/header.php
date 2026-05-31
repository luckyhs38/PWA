<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_check.php';

$current = basename($_SERVER['PHP_SELF']);
$is_logged_in = isset($_SESSION['user_id']);

$unread_notification_count = 0;
$notifications = [];

if ($is_logged_in) {

    // 안 읽은 개수
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM notifications
        WHERE user_id = :user_id
          AND is_read = 0
    ");

    $stmt->execute([
        ':user_id' => $_SESSION['user_id']
    ]);

    $unread_notification_count = (int)$stmt->fetchColumn();


    // 최근 알림 목록
    $stmt = $pdo->prepare("
        SELECT *
        FROM notifications
        WHERE user_id = :user_id
        ORDER BY id DESC
        LIMIT 10
    ");

    $stmt->execute([
        ':user_id' => $_SESSION['user_id']
    ]);

    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>한글은 늘 도망가</title>
    <!-- 파비콘 -->
    <link rel="icon" href="/img/logo.png" type="image/png">
    <link rel="apple-touch-icon" href="/img/logo.png">

    <!-- jQuery (Summernote 및 모바일 기능을 위해 유지) -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <!-- Bootstrap 5 (CSS & JS Bundle) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Noto+Serif+KR:wght@300;400;500&family=Noto+Sans+KR:wght@300;400;500&display=swap" rel="stylesheet">

    <!-- Summernote Lite (BS5 호환 버전) -->
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/lang/summernote-ko-KR.min.js"></script>

    <!-- 커스텀 CSS -->
    <link href="/css/style.css" rel="stylesheet">
    <style>
    body {
      font-family: 'Noto Sans KR', sans-serif;
      font-weight: 300;
      background: #fff;
        /* 화면고정 */
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    /* 로고 이미지 스타일 */
    nav.navbar .navbar-brand {
        padding: 0; /* 패딩 초기화 */
        display: flex;
        align-items: center;
    }

    .header-container {
        width: 100%;
        max-width: 1100px;
        margin: 0 auto;
        padding: 0 24px;
    }

    .navbar-logo {
        height: 40px;      /* 데스크탑 기준 높이 (적절히 조절하세요) */
        width: auto;       /* 가로 비율 유지 */
        object-fit: contain;
        transition: height 0.3s;
    }

    /* 모바일 화면에서 로고 크기 조정 */
    @media (max-width: 991.98px) {
        .navbar-logo {
            
            height: 32px;  /* 모바일에서 조금 더 작게 설정 */
        }
    }

    /* =====================
       네비게이션바
    ===================== */
    .navbar {
      /* padding: 1.2rem 0; */
      min-height: 80px;
      background: #fff;
      border-bottom: 0.5px solid #eee;
      position: relative;
      z-index: 100;
    }

    /* 로고 */
    nav.navbar .navbar-brand {
        font-family: 'Noto Serif KR', serif;
        font-size: 25px;
        font-weight: 400;   /* 조금 더 두껍게 설정 */
        color: #1a1a1a !important;
}

    /* 메뉴 링크 */
    .navbar-nav .nav-link {
      font-family: 'Noto Sans KR', sans-serif;
      font-weight: 400;
      font-size: 15px;
      color: #555 !important;
      padding: 0 15px !important;
      letter-spacing: 0.01em;
      transition: color 0.15s;
    }

    .navbar-nav .nav-link:hover { color: #000 !important; }
    .navbar-nav .nav-link.active {
      color: #000 !important;
      font-weight: 400;
    }

    /* 드롭다운 */
    .dropdown-menu {
      border: 0.5px solid #eee;
      border-radius: 6px;
      padding: 6px 0;
      box-shadow: 0 4px 12px rgba(0,0,0,0.06);
      min-width: 110px;
    }

    .dropdown-item {
      font-family: 'Noto Sans KR', sans-serif;
      font-weight: 300;
      font-size: 13px;
      color: #666;
      padding: 8px 18px;
    }

    .dropdown-item:hover {
      background: #f8f8f8;
      color: #000;
    }

    /* 아이콘 */
    .icon-menu {
      font-size: 1.1rem;
      color: #666;
      cursor: pointer;
      transition: color 0.15s;
    }

    .icon-menu:hover { color: #000; }

    /* 닉네임 */
    .nickname-text {
      font-size: 12px;
      color: #999;
      font-family: 'Noto Sans KR', sans-serif;
      font-weight: 300;
    }

    /* 알림 기능 */
    #notifList .notif-item {
        white-space: normal;
        font-size: 13px;
        line-height: 1.5;
        padding: 10px 14px;
        border-bottom: 1px solid #f5f5f5;
    }

    #notifList .notif-item.unread {
        background: #fafafa;
        font-weight: 400;
    }

    #notifList .notif-time {
        font-size: 11px;
        color: #bbb;
        margin-top: 3px;
    }
    .notification-item {
    display: flex !important;
    justify-content: space-between;
    align-items: center;
    }
    .notification-item .badge {
    font-size: 10px;
    min-width: 18px;
    }
    /* 알림 항목: 메시지 + X 삭제 버튼 */
.notif-row {
    display: flex;
    align-items: stretch;
    border-bottom: 1px solid #f5f5f5;
}

.notif-row .notif-link {
    flex: 1;
    white-space: normal;
    font-size: 13px;
    line-height: 1.5;
    padding: 10px 12px 10px 14px;
    text-decoration: none;
    color: #333;
}

.notif-row.unread .notif-link {
    background: #fafafa;
    font-weight: 400;
}

.notif-delete-form {
    display: flex;
    align-items: center;
    margin: 0;
}

.notif-delete-btn {
    border: none;
    background: transparent;
    color: #bbb;
    width: 34px;
    height: 100%;
    font-size: 14px;
    cursor: pointer;
}

.notif-delete-btn:hover {
    color: #dc3545;
    background: #fff5f5;
}

    /* =====================
       모바일 - 오른쪽 슬라이드 패널
       기존 사이트 m-menu-wrapper right 방식 참고
    ===================== */

    /* 배경 딤처리 */
    .m-menu-bg {
      display: none;
      position: fixed;
      top: 0; left: 0;
      width: 100%; height: 100%;
      background: rgba(0,0,0,0.3);
      z-index: 998;
    }

    .m-menu-bg.active { display: block; }

    /* 오른쪽 슬라이드 패널 */
    .m-menu-panel {
      position: fixed;
      top: 0;
      right: -100%;        /* 기본: 화면 밖 */
      width: 100%;
      height: 100%;
      background: #fff;
      z-index: 999;
      display: flex;
      flex-direction: column;
      transition: right 0.3s ease;
      overflow-y: auto;
    }

    .m-menu-panel.active {
      right: 0;             /* 슬라이드 인 */
    }

    /* 패널 상단 닫기 버튼 */
    .m-panel-top {
      display: flex;
      justify-content: flex-end;
      padding: 16px 18px;
      border-bottom: 0.5px solid #eee;
    }

    .m-panel-close {
      background: none;
      border: none;
      font-size: 18px;
      color: #666;
      cursor: pointer;
      padding: 0;
      line-height: 1;
    }

    /* 검색창 */
    .m-search-box {
      margin: 14px 16px;
      border: 0.5px solid #ddd;
      border-radius: 8px;
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 9px 13px;
    }

    .m-search-box input {
      border: none;
      outline: none;
      font-family: 'Noto Sans KR', sans-serif;
      font-weight: 300;
      font-size: 14px;
      color: #555;
      width: 100%;
      background: transparent;
    }

    .m-search-box input::placeholder { color: #bbb; }

    /* 모바일 메뉴 목록 */
    .m-nav-list {
      list-style: none;
      padding: 6px 0;
      margin: 0;
      flex: 1;
    }

    .m-nav-list > li > a {
      display: block;
      padding: 13px 22px;
      font-family: 'Noto Sans KR', sans-serif;
      font-weight: 300;
      font-size: 15px;
      color: #1a1a1a;
      text-decoration: none;
      transition: color 0.15s;
      border-bottom: 0.5px solid #f5f5f5;
    }

    .m-nav-list > li > a:hover { color: #666; }

    /* 공개글 아코디언 토글 버튼 */
    .m-accordion-btn {
      display: flex;
      justify-content: space-between;
      align-items: center;
      width: 100%;
      padding: 13px 22px;
      font-family: 'Noto Sans KR', sans-serif;
      font-weight: 300;
      font-size: 15px;
      color: #1a1a1a;
      background: none;
      border: none;
      border-bottom: 0.5px solid #f5f5f5;
      text-align: left;
      cursor: pointer;
      transition: color 0.15s;
    }

    .m-accordion-btn:hover { color: #666; }

    .m-accordion-btn .arrow {
      font-size: 11px;
      color: #bbb;
      transition: transform 0.2s;
    }

    .m-accordion-btn.open .arrow {
      transform: rotate(180deg);
    }

    /* 아코디언 서브메뉴 */
    .m-sub-list {
      display: none;
      list-style: none;
      padding: 4px 0;
      margin: 0;
      background: #fafafa;
      border-bottom: 0.5px solid #f5f5f5;
    }

    .m-sub-list.open { display: block; }

    .m-sub-list li a {
      display: block;
      padding: 10px 22px 10px 32px;
      font-family: 'Noto Sans KR', sans-serif;
      font-weight: 300;
      font-size: 13px;
      color: #666;
      text-decoration: none;
      transition: color 0.15s;
    }

    .m-sub-list li a:hover { color: #1a1a1a; }

    /* 모바일 하단 로그인 영역 */
    .m-login-area {
      padding: 18px 22px;
      border-top: 0.5px solid #eee;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .m-login-area a {
      font-family: 'Noto Sans KR', sans-serif;
      font-weight: 300;
      font-size: 13px;
      color: #555;
      text-decoration: none;
      display: flex;
      align-items: center;
      gap: 5px;
      transition: color 0.15s;
    }

    .m-login-area a:hover { color: #000; }

    .m-login-divider {
      color: #ddd;
      font-size: 12px;
    }
    /* 알림 기능 모바일 */
    .mobile-bell {
    position: relative;
    color: #666;
    font-size: 20px;
    text-decoration: none;
    display: flex;
    align-items: center;
}

.mobile-bell:hover {
    color: #000;
}

.mobile-bell-badge {
    position: absolute;
    top: -5px;
    right: -8px;

    min-width: 16px;
    height: 16px;

    border-radius: 999px;
    background: #dc3545;
    color: #fff;

    font-size: 9px;
    font-weight: 600;

    display: flex;
    align-items: center;
    justify-content: center;

    padding: 0 4px;
}

.mobile-notif-dropdown {
    width: 280px;
    max-height: 320px;
    overflow-y: auto;
    border: 1px solid #eee;
    border-radius: 10px;
    box-shadow: 0 6px 20px rgba(0,0,0,0.08);
    padding: 6px 0;
}

    /* =====================
       본문
    ===================== */
    main { padding-top: 2rem; }
  </style>
</head>
<body>

<!-- 네비게이션바 -->
<nav class="navbar navbar-expand-lg">
  <div class="container-fluid header-container">

    <!-- 로고 -->
    <!-- <a class="navbar-brand" href="/index.php">한글은 늘 도망가</a> -->
    <a class="navbar-brand" href="/index.php">
      <img src="/img/logo.png" alt="한글은 늘 도망가" class="navbar-logo">
    </a>
    <!-- 모바일: 햄버거 버튼 (오른쪽) -->
    <div class="d-flex align-items-center d-lg-none">

<?php if ($is_logged_in): ?>
<div class="dropdown">

    <a href="#"
       class="mobile-bell position-relative me-2"
       role="button"
       data-bs-toggle="dropdown"
       aria-expanded="false">

        <i class="bi bi-bell"></i>

        <?php if ($unread_notification_count > 0): ?>
            <span class="mobile-bell-badge">
                <?= $unread_notification_count ?>
            </span>
        <?php endif; ?>
    </a>

    <ul class="dropdown-menu dropdown-menu-end mobile-notif-dropdown">

    <li class="d-flex justify-content-between align-items-center pe-3"> 
        <h6 class="dropdown-header m-0">알림</h6> 
        <?php if (isset($_SESSION['user_id'])): ?>
            <button id="fcm-allow-btn-mobile" class="btn btn-sm btn-outline-secondary" style="font-size:11px; padding:3px 8px; border-radius:999px; line-height:1.2;">
                <i class="bi bi-bell-fill"></i> 푸시 켜기
            </button>
        <?php endif; ?>
    </li>

        <?php if (empty($notifications)): ?>

            <li>
                <span class="dropdown-item-text small text-muted px-3">
                    새로운 알림이 없습니다.
                </span>
            </li>

        <?php else: ?>

          <?php foreach ($notifications as $noti): ?>

              <li class="notif-row <?= !$noti['is_read'] ? 'unread' : '' ?>">

                  <a class="notif-link"
                    href="/ajax/notification_read.php?id=<?= (int)$noti['id'] ?>">

                      <div class="small mb-1">
                          <?= htmlspecialchars($noti['message']) ?>
                      </div>

                      <div class="text-muted" style="font-size:11px;">
                          <?= date('m.d H:i', strtotime($noti['created_at'])) ?>
                      </div>

                  </a>

                  <form method="post"
                        action="/ajax/notification_delete.php"
                        class="notif-delete-form"
                        onsubmit="return confirm('이 알림을 삭제하시겠습니까?');">
                      <input type="hidden" name="action" value="single">
                      <input type="hidden" name="id" value="<?= (int)$noti['id'] ?>">
                      <button type="submit" class="notif-delete-btn" title="알림 삭제">
                          <i class="bi bi-x"></i>
                      </button>
                  </form>

              </li>

          <?php endforeach; ?>

        <?php endif; ?>

<?php if (!empty($notifications)): ?>
    <li><hr class="dropdown-divider"></li>

    <li>
        <form method="post" action="/ajax/notification_delete.php" class="m-0">
            <input type="hidden" name="action" value="all">
            <button 
                type="submit"
                class="dropdown-item text-center small text-danger"
                onclick="return confirm('알림을 모두 삭제하시겠습니까?');">
                알림 전체 삭제
            </button>
        </form>
    </li>
<?php endif; ?>

    </ul>

</div>
<?php endif; ?>

        <button class="navbar-toggler border-0"
                type="button"
                onclick="openMobileMenu()"
                aria-label="메뉴 열기">

            <span class="navbar-toggler-icon"></span>
        </button>

    </div>

    <!-- 데스크탑 메뉴 (ms-auto = 오른쪽 정렬) -->
    <div class="collapse navbar-collapse d-none d-lg-flex" id="mainNav">
      <ul class="navbar-nav ms-auto align-items-center">

        <li class="nav-item">
          <a class="nav-link <?= $current === 'index.php' ? 'active' : '' ?>"
             href="/index.php">홈</a>
        </li>

        <li class="nav-item">
          <a class="nav-link <?= $current === 'intro.php' ? 'active' : '' ?>"
             href="/intro.php">소개</a>
        </li>

        <li class="nav-item">
          <a class="nav-link <?= $current === 'cal.php' ? 'active' : '' ?>"
             href="/cal/cal.php">일정</a>
        </li>

        <!-- 공개글 드롭다운 -->
        <!-- 
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle <?= in_array($current, ['public.php','notice.php']) ? 'active' : '' ?>"
             href="#"
             role="button"
             data-bs-toggle="dropdown"
             aria-expanded="false">
            공개글
          </a>
          <ul class="dropdown-menu">
            <li>
              <a class="dropdown-item <?= $current === 'notice.php' ? 'active' : '' ?>"
                 href="/notice.php">└ 최신정보</a>
            </li>
            <li>
              <a class="dropdown-item"
                 href="https://linkareer.com/list/contest?filterBy_categoryIDs=38&filterType=CATEGORY&orderBy_direction=DESC&orderBy_field=VIEW_COUNT&page=1"
                 target="_blank">└ 공모전</a>
            </li>
            <li>
              <a class="dropdown-item"
                 href="https://www.sinchun.co.kr/"
                 target="_blank">└ 신춘문예</a>
            </li>
          </ul>
        </li> -->

        <li class="nav-item">
          <a class="nav-link <?= strpos($_SERVER['REQUEST_URI'], 'type=anonymity') !== false ? 'active' : '' ?>"
             href="/board/list.php?type=anonymity">익명글</a>
        </li>

        <li class="nav-item">
          <a class="nav-link <?= strpos($_SERVER['REQUEST_URI'], 'type=writing') !== false ? 'active' : '' ?>"
             href="/board/list.php?type=writing">작가만의 방</a>
        </li>

        <li class="nav-item">
          <a class="nav-link <?= $current === 'archive.php' ? 'active' : '' ?>"
             href="/board/archive.php">사랑 아카이브</a>
        </li>

        <li class="nav-item">
          <a class="nav-link <?= $current === 'qna.php' ? 'active' : '' ?>"
             href="/qna/qna.php">문의</a>
        </li>

        <!-- 로그인/회원가입 드롭다운 (사람 아이콘) -->
        <li class="nav-item dropdown ms-2">
          <a class="nav-link px-1"
             href="#"
             role="button"
             data-bs-toggle="dropdown"
             aria-expanded="false">
            <?php if ($is_logged_in): ?>
              <span class="nickname-text me-1">
                <?= htmlspecialchars($_SESSION['nickname']) ?>님
              </span>
            <?php endif; ?>
            <i class="bi bi-person icon-menu"></i>
          </a>
          <ul class="dropdown-menu dropdown-menu-end">
            <?php if ($is_logged_in): ?>
              <?php if (is_admin()): ?>
              <li>
                <a class="dropdown-item" href="/admin/index.php">
                  <i class="bi bi-speedometer2 me-2"></i>관리자 대시보드
                </a>
              </li>
              <?php endif; ?>
              <li>
                <a class="dropdown-item" href="/mypage.php">
                  <i class="bi bi-person-gear me-2"></i>내 정보 수정
                </a>
              </li>
              <li>
                <a class="dropdown-item" href="/logout.php">
                  <i class="bi bi-box-arrow-right me-2"></i>로그아웃
                </a>
              </li>
            <?php else: ?>
              <li>
                <a class="dropdown-item" href="/login.php">
                  <i class="bi bi-box-arrow-in-right me-2"></i>로그인
                </a>
              </li>
              <li>
                <a class="dropdown-item" href="/join.php">
                  <i class="bi bi-person-plus me-2"></i>회원가입
                </a>
              </li>
            <?php endif; ?>
          </ul>
        </li>

        <?php if ($is_logged_in): ?>

        <li class="nav-item dropdown me-2">

            <a class="nav-link position-relative px-1"
              href="#"
              role="button"
              data-bs-toggle="dropdown"
              aria-expanded="false">

                <i class="bi bi-bell icon-menu"></i>

                <?php if ($unread_notification_count > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"
                          style="font-size:10px;">

                        <?= $unread_notification_count ?>

                    </span>
                <?php endif; ?>

            </a>

            <ul class="dropdown-menu dropdown-menu-end"
                style="width:320px; max-height:400px; overflow-y:auto;">

            <li class="d-flex justify-content-between align-items-center pe-3">
                <h6 class="dropdown-header m-0">알림</h6> 
                <?php if (isset($_SESSION['user_id'])): ?>
                    <button id="fcm-allow-btn" class="btn btn-sm btn-outline-secondary" style="font-size:11px; padding:3px 8px; border-radius:999px; line-height:1.2;">
                        <i class="bi bi-bell-fill"></i> 푸시 켜기
                    </button>
                <?php endif; ?>
            </li>

                <?php if (empty($notifications)): ?>

                    <li>
                        <span class="dropdown-item-text small text-muted px-3">
                            새로운 알림이 없습니다.
                        </span>
                    </li>

                <?php else: ?>

                  <?php foreach ($notifications as $noti): ?>

                      <li class="notif-row <?= !$noti['is_read'] ? 'unread' : '' ?>">

                          <a class="notif-link"
                            href="/ajax/notification_read.php?id=<?= (int)$noti['id'] ?>">

                              <div class="small mb-1">
                                  <?= htmlspecialchars($noti['message']) ?>
                              </div>

                              <div class="text-muted" style="font-size:11px;">
                                  <?= date('m.d H:i', strtotime($noti['created_at'])) ?>
                              </div>

                          </a>

                          <form method="post"
                                action="/ajax/notification_delete.php"
                                class="notif-delete-form"
                                onsubmit="return confirm('이 알림을 삭제하시겠습니까?');">
                              <input type="hidden" name="action" value="single">
                              <input type="hidden" name="id" value="<?= (int)$noti['id'] ?>">
                              <button type="submit" class="notif-delete-btn" title="알림 삭제">
                                  <i class="bi bi-x"></i>
                              </button>
                          </form>

                      </li>

                  <?php endforeach; ?>

                <?php endif; ?>
                
<?php if (!empty($notifications)): ?>
    <li><hr class="dropdown-divider"></li>

    <li>
        <form method="post" action="/ajax/notification_delete.php" class="m-0">
            <input type="hidden" name="action" value="all">
            <button 
                type="submit"
                class="dropdown-item text-center small text-danger"
                onclick="return confirm('알림을 모두 삭제하시겠습니까?');">
                알림 전체 삭제
            </button>
        </form>
    </li>
<?php endif; ?>



            </ul>

        </li>

        <?php endif; ?>

        <!-- 검색 아이콘 -->
        <!-- <li class="nav-item ms-1">
          <a class="nav-link px-1" href="#">
            <i class="bi bi-search icon-menu"></i>
          </a>
        </li> -->

      </ul>
    </div>

  </div>
</nav>

<!-- =====================
     모바일 딤 배경
===================== -->
<div class="m-menu-bg" id="mMenuBg" onclick="closeMobileMenu()"></div>

<!-- =====================
     모바일 오른쪽 슬라이드 패널
     기존 사이트 m-menu-wrapper right 방식
===================== -->
<div class="m-menu-panel" id="mMenuPanel">

  <!-- 상단 닫기 버튼 -->
  <div class="m-panel-top">
    <button class="m-panel-close" onclick="closeMobileMenu()">
      <i class="bi bi-x" style="font-size:22px;"></i>
    </button>
  </div>

  <!-- 검색창 -->
  <div class="m-search-box">
    <i class="bi bi-search" style="color:#bbb;font-size:13px;"></i>
    <input type="text" placeholder="검색어를 입력하세요.">
  </div>

  <!-- 메뉴 목록 -->
  <ul class="m-nav-list">

    <li>
      <a href="/index.php" onclick="closeMobileMenu()">홈</a>
    </li>

    <li>
      <a href="/intro.php" onclick="closeMobileMenu()">소개</a>
    </li>

    <li>
      <a href="/cal/cal.php" onclick="closeMobileMenu()">일정</a>
    </li>

    <!-- 공개글 -->
    <!-- <li>
      <button class="m-accordion-btn" onclick="toggleAccordion(this)">
        공개글
        <span class="arrow"><i class="bi bi-chevron-down"></i></span>
      </button>
      <ul class="m-sub-list">
        <li>
          <a href="/notice.php" onclick="closeMobileMenu()">└ 최신정보</a>
        </li>
        <li>
          <a href="https://linkareer.com/list/contest?filterBy_categoryIDs=38&filterType=CATEGORY&orderBy_direction=DESC&orderBy_field=VIEW_COUNT&page=1"
             target="_blank"
             onclick="closeMobileMenu()">└ 공모전</a>
        </li>
        <li>
          <a href="https://www.sinchun.co.kr/"
             target="_blank"
             onclick="closeMobileMenu()">└ 신춘문예</a>
        </li>
      </ul>
    </li> -->

    <li>
      <a href="/board/list.php?type=anonymity" onclick="closeMobileMenu()">익명글</a>
    </li>

    <li>
      <a href="/board/list.php?type=writing" onclick="closeMobileMenu()">작가만의 방</a>
    </li>

    <li>
      <a href="/board/archive.php" onclick="closeMobileMenu()">사랑 아카이브</a>
    </li>

    <li>
      <a href="/qna/qna.php" onclick="closeMobileMenu()">문의</a>
    </li>

  </ul>

  <!-- 하단 로그인/로그아웃 -->
  <div class="m-login-area">
    <?php if ($is_logged_in): ?>
      <span class="nickname-text">
        <?= htmlspecialchars($_SESSION['nickname']) ?>님
      </span>
      <span class="m-login-divider">|</span>
          <?php if (is_admin()): ?>
              <a href="/admin/index.php">
                  <i class="bi bi-speedometer2"></i> 관리자
              </a>
              <span class="m-login-divider">|</span>
          <?php endif; ?>

              <a href="/mypage.php">
                  <i class="bi bi-person-gear"></i> 마이페이지
              </a>
              <span class="m-login-divider">|</span>
                  
              <a href="/logout.php">
                  <i class="bi bi-box-arrow-right"></i> 로그아웃
              </a>
            <?php else: ?>
            <a href="/login.php">
                <i class="bi bi-box-arrow-in-right"></i> 로그인
            </a>
            <span class="m-login-divider">|</span>
            <a href="/join.php">
                <i class="bi bi-person-plus"></i> 회원가입
            </a>
            <?php endif; ?>
        </div>

</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
  // 모바일 메뉴 열기
  function openMobileMenu() {
    document.getElementById('mMenuPanel').classList.add('active');
    document.getElementById('mMenuBg').classList.add('active');
    document.body.style.overflow = 'hidden';
  }

  // 모바일 메뉴 닫기
  function closeMobileMenu() {
    document.getElementById('mMenuPanel').classList.remove('active');
    document.getElementById('mMenuBg').classList.remove('active');
    document.body.style.overflow = '';
  }

  // 공개글 아코디언 토글 (기존 사이트 방식)
  function toggleAccordion(btn) {
    const subList = btn.nextElementSibling;
    const isOpen  = subList.classList.contains('open');

    // 열려있으면 닫기, 닫혀있으면 열기
    subList.classList.toggle('open', !isOpen);
    btn.classList.toggle('open', !isOpen);
  }

  // ESC 키로 메뉴 닫기
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeMobileMenu();
  });

</script>
<!-- FCM 초기화 -->
<?php include __DIR__ . '/fcm_script.php'; ?>
<!-- 본문 시작 -->
<!-- <main class="flex-fill d-flex align-items-center"> -->
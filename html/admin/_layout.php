<?php
// /admin/_layout.php
// 각 관리자 페이지에서 include해서 사용

// 미처리 건수 (사이드바 뱃지용)
try {
    $pending_qna     = (int)$pdo->query("SELECT COUNT(*) FROM qna WHERE status='pending' AND deleted_at IS NULL")->fetchColumn();
    $pending_writers = (int)$pdo->query("SELECT COUNT(*) FROM writer_applications WHERE status='pending'")->fetchColumn();
} catch (PDOException $e) {
    $pending_qna = $pending_writers = 0;
}

$menus = [
    'dashboard' => ['label' => '대시보드',     'icon' => 'bi-speedometer2',  'url' => '/admin/index.php'],
    'users'     => ['label' => '회원 관리',     'icon' => 'bi-people',        'url' => '/admin/users.php'],
    'writers'   => ['label' => '작가 신청',     'icon' => 'bi-pencil-square', 'url' => '/admin/writer_list.php', 'badge' => $pending_writers],
    'qna'       => ['label' => '문의 관리',     'icon' => 'bi-chat-square-text', 'url' => '/admin/qna_list.php', 'badge' => $pending_qna],
    'posts'     => ['label' => '게시글 관리',   'icon' => 'bi-file-text',     'url' => '/admin/posts.php'],
    'stats'     => ['label' => '통계',          'icon' => 'bi-bar-chart',     'url' => '/admin/stats.php'],
];
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($menus[$admin_menu]['label'] ?? '관리자') ?> — 관리자</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
        font-family: -apple-system, BlinkMacSystemFont, 'Noto Sans KR', sans-serif;
        background: #f5f5f5;
        color: #1a1a1a;
        font-size: 14px;
        -webkit-font-smoothing: antialiased;
        overflow-x: hidden; /* 💡 전체 페이지 가로 스크롤 원천 차단 */
    }

    /* ── 스크롤바 숨김 처리 (모바일 앱처럼 깔끔하게) ── */
    .adm-tabs::-webkit-scrollbar,
    .adm-table::-webkit-scrollbar { display: none; }
    .adm-tabs, .adm-table { -ms-overflow-style: none; scrollbar-width: none; }

    /* ── 전체 레이아웃 ── */
    .adm-shell {
        display: grid;
        grid-template-columns: 220px 1fr;
        min-height: 100vh;
    }

    /* ── 사이드바 (PC 전용) ── */
    .adm-side {
        background: #1a1a1a; display: flex; flex-direction: column;
        position: sticky; top: 0; height: 100vh; overflow-y: auto; z-index: 100;
    }
    .adm-logo { padding: 1.25rem 1.25rem 1rem; border-bottom: 1px solid rgba(255,255,255,.08); flex-shrink: 0; }
    .adm-logo-title { font-family: 'Noto Serif KR', serif; font-size: 15px; font-weight: 500; color: #fff; letter-spacing: -.3px; }
    .adm-logo-sub { font-size: 11px; color: rgba(255,255,255,.35); margin-top: 3px; }
    .adm-nav { padding: .75rem; flex: 1; }
    .adm-nav-section { font-size: 10px; color: rgba(255,255,255,.3); letter-spacing: .5px; padding: 12px 8px 5px; margin-top: 4px; }
    .adm-nav-item {
        display: flex; align-items: center; gap: 9px; padding: 9px 10px; border-radius: 7px;
        font-size: 13px; color: rgba(255,255,255,.55); cursor: pointer; border: none; background: none;
        width: 100%; text-align: left; font-family: inherit; text-decoration: none; transition: all .15s; margin-bottom: 2px;
    }
    .adm-nav-item:hover  { background: rgba(255,255,255,.07); color: rgba(255,255,255,.9); }
    .adm-nav-item.active { background: rgba(255,255,255,.12); color: #fff; }
    .adm-nav-item i { font-size: 16px; flex-shrink: 0; }
    .adm-nav-badge {
        margin-left: auto; background: #e53935; color: #fff; border-radius: 999px;
        font-size: 10px; padding: 1px 6px; font-weight: 600; min-width: 18px; text-align: center;
    }
    .adm-bottom { padding: .75rem; border-top: 1px solid rgba(255,255,255,.08); flex-shrink: 0; }

    /* ── 메인 콘텐츠 ── */
    .adm-main { padding: 2rem 2.25rem; min-height: 100vh; min-width: 0; }
    .adm-topbar { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.75rem; }
    .adm-page-title { font-size: 22px; font-weight: 500; color: #1a1a1a; letter-spacing: -.3px; }
    .adm-page-sub { font-size: 13px; color: #aaa; margin-top: 4px; }
    .adm-admin-tag {
        display: flex; align-items: center; gap: 6px; font-size: 13px; color: #888;
        background: #fff; border: 1px solid #eee; border-radius: 999px; padding: 6px 14px;
    }
    .adm-online-dot { width: 7px; height: 7px; border-radius: 50%; background: #4caf50; flex-shrink: 0; }

    /* ── 공통 카드 ── */
    .adm-card { background: #fff; border: 1px solid #eee; border-radius: 10px; padding: 20px 22px; margin-bottom: 20px; }
    .adm-card-hd { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid #f0f0f0; }
    .adm-card-title { font-size: 14px; font-weight: 500; color: #1a1a1a; }

    /* ── 공통 테이블 ── */
    .adm-table { width: 100%; border-collapse: collapse; }
    .adm-table th { font-size: 11px; font-weight: 500; color: #bbb; padding: 10px; text-align: left; border-bottom: 1px solid #eee; white-space: nowrap; }
    .adm-table td { font-size: 13px; color: #333; padding: 13px 10px; border-bottom: 1px solid #f8f8f8; vertical-align: middle; }
    .adm-table tbody tr:hover { background: #fafafa; }
    .adm-table tr:last-child td { border-bottom: none; }

    /* 버튼, 뱃지, 필터 등 */
    .adm-btn { display: inline-flex; align-items: center; gap: 5px; border: 1px solid #ddd; background: #fff; border-radius: 6px; padding: 6px 14px; font-size: 12px; color: #555; cursor: pointer; text-decoration: none; transition: all .15s; white-space: nowrap; }
    .adm-btn:hover { border-color: #aaa; color: #1a1a1a; }
    .adm-btn.dark { background: #1a1a1a; color: #fff; border-color: #1a1a1a; }
    .adm-btn.success { background: #f0f7f0; border-color: #d4ead4; color: #4caf50; }
    .adm-btn.danger { background: #fff5f5; border-color: #fcc; color: #dc3545; }
    .adm-btn.warning { background: #fffbf0; border-color: #ffe8a0; color: #f5a623; }
    
    .adm-badge { display: inline-block; font-size: 11px; padding: 3px 9px; border-radius: 999px; white-space: nowrap; }
    .adm-badge.pending { background: #fafafa; border: 1px solid #eee; color: #999; }
    .adm-badge.answered { background: #1a1a1a; color: #fff; }
    .adm-badge.approved, .adm-badge.active { background: #f0f7f0; border: 1px solid #d4ead4; color: #4caf50; }
    .adm-badge.rejected, .adm-badge.inactive { background: #fff5f5; border: 1px solid #fcc; color: #dc3545; }
    .adm-badge.writer { background: #f0f4ff; border: 1px solid #c8d5f5; color: #4a6fa5; }
    .adm-badge.user { background: #fafafa; border: 1px solid #eee; color: #aaa; }
    .adm-badge.admin { background: #1a1a1a; color: #fff; }

    .adm-filter-bar { display: flex; gap: 10px; align-items: center; margin-bottom: 16px; flex-wrap: wrap; }
    .adm-search { position: relative; flex: 1; min-width: 200px; }
    .adm-search input { width: 100%; border: 1px solid #ddd; border-radius: 8px; padding: 8px 34px 8px 12px; font-size: 13px; outline: none; }
    .adm-search i { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); font-size: 15px; color: #ccc; }
    .adm-select { border: 1px solid #ddd; border-radius: 8px; padding: 8px 12px; font-size: 13px; color: #555; outline: none; background: #fff; }

    .adm-pagination { display: flex; justify-content: center; gap: 4px; margin-top: 20px; }
    .adm-page-btn { border: 1px solid #eee; background: #fff; color: #888; border-radius: 6px; padding: 6px 12px; font-size: 13px; text-decoration: none; min-width: 34px; text-align: center; }
    .adm-page-btn.active { background: #1a1a1a; color: #fff; border-color: #1a1a1a; }
    .adm-page-btn.disabled { opacity: .35; pointer-events: none; }

    .adm-alert { padding: 12px 16px; border-radius: 8px; font-size: 13px; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
    .adm-alert.success { background: #f0f7f0; border: 1px solid #d4ead4; color: #4caf50; }
    .adm-alert.error { background: #fff5f5; border: 1px solid #fcc; color: #dc3545; }

    .adm-modal-bg { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.35); z-index: 1050; align-items: center; justify-content: center; padding: 15px; }
    .adm-modal-bg.show { display: flex; }
    .adm-modal { background: #fff; border-radius: 12px; padding: 24px; width: 400px; max-width: 100%; box-shadow: 0 8px 30px rgba(0,0,0,.12); }
    .adm-modal-title { font-size: 16px; font-weight: 500; color: #1a1a1a; margin-bottom: 8px; }
    .adm-modal-desc { font-size: 13px; color: #888; line-height: 1.6; margin-bottom: 20px; }
    .adm-modal-textarea { width: 100%; border: 1px solid #ddd; border-radius: 8px; padding: 10px 12px; font-size: 13px; outline: none; min-height: 90px; margin-bottom: 16px;}
    .adm-modal-btns { display: flex; justify-content: flex-end; gap: 8px; }

    .adm-empty { text-align: center; padding: 60px 0; color: #ccc; font-size: 13px; }
    .adm-empty i { font-size: 32px; display: block; margin-bottom: 10px; }

    .stat-grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 20px; }
    .stat-card { background: #fff; border: 1px solid #eee; border-radius: 10px; padding: 18px 20px; }
    .stat-label { font-size: 11px; color: #bbb; margin-bottom: 6px; }
    .stat-val { font-size: 26px; font-weight: 500; color: #1a1a1a; margin-bottom: 4px; }
    .stat-diff { font-size: 11px; color: #4caf50; }
    .stat-diff.down { color: #dc3545; }

    .adm-tabs { display: flex; border-bottom: 1px solid #eee; margin-bottom: 24px; overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .adm-tab { padding: 13px 20px; font-size: 14px; color: #bbb; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; border-bottom: 2px solid transparent; margin-bottom: -1px; white-space: nowrap; }
    .adm-tab.active { color: #1a1a1a; border-bottom-color: #1a1a1a; }
    .adm-tab-cnt { font-size: 11px; background: #f0f0f0; color: #888; border-radius: 999px; padding: 1px 7px; font-weight: 500; }
    .adm-tab.active .adm-tab-cnt { background: #1a1a1a; color: #fff; }

    /* ── 모바일 하단 네비게이션 (기본 숨김) ── */
    .adm-mobile-nav { display: none; }

    /* ==============================================
       📱 반응형 모바일 최적화 영역 (960px 이하)
       ============================================== */
    @media (max-width: 960px) {
        .adm-shell { 
            grid-template-columns: 1fr; 
            padding-bottom: 70px; 
        }
        .adm-side { display: none; }
        
        .adm-main { padding: 1.2rem 1rem; }
        .adm-topbar { flex-direction: column; gap: 12px; align-items: flex-start; margin-bottom: 1.25rem; }
        
        .stat-grid-4 { grid-template-columns: 1fr 1fr; gap: 10px; }
        .adm-card { padding: 16px 14px; }
        
        /* 🚨 핵심: HTML 수정 없이 테이블 자체를 스와이프 가능하게 만듦! */
        .adm-table {
            display: block; 
            width: 100%; 
            overflow-x: auto; 
            -webkit-overflow-scrolling: touch; 
            white-space: nowrap;
        }
        
        .adm-filter-bar { flex-direction: column; align-items: stretch; }
        .adm-search, .adm-select { width: 100%; }

        .adm-mobile-nav {
            display: flex; position: fixed; bottom: 0; left: 0; right: 0;
            background: #fff; border-top: 1px solid #eee; box-shadow: 0 -4px 12px rgba(0,0,0,0.03);
            z-index: 1050; justify-content: space-around; padding: 8px 5px;
            padding-bottom: env(safe-area-inset-bottom, 8px); 
        }
        .adm-mob-item {
            flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center;
            gap: 4px; text-decoration: none; color: #bbb; font-size: 10px; position: relative;
        }
        .adm-mob-item.active { color: #1a1a1a; font-weight: 600; }
        .adm-mob-item i { font-size: 20px; margin-bottom: -2px; }
        .adm-mob-badge { position: absolute; top: 2px; right: 20%; width: 6px; height: 6px; background: #e53935; border-radius: 50%; }
    }
    </style>
</head>
<body>
<div class="adm-shell">

    <nav class="adm-side">
        <div class="adm-logo">
            <div class="adm-logo-title">한글은 늘 도망가</div>
            <div class="adm-logo-sub">관리자 패널</div>
        </div>
        <div class="adm-nav">
            <div class="adm-nav-section">MAIN</div>
            <?php foreach ($menus as $key => $menu): ?>
                <?php if ($key === 'users'): ?>
                    <div class="adm-nav-section">관리</div>
                <?php elseif ($key === 'stats'): ?>
                    <div class="adm-nav-section">분석</div>
                <?php endif; ?>
                <a href="<?= $menu['url'] ?>"
                   class="adm-nav-item <?= ($admin_menu === $key) ? 'active' : '' ?>">
                    <i class="bi <?= $menu['icon'] ?>"></i>
                    <?= $menu['label'] ?>
                    <?php if (!empty($menu['badge']) && $menu['badge'] > 0): ?>
                        <span class="adm-nav-badge"><?= $menu['badge'] ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
        <div class="adm-bottom">
            <a href="/" class="adm-nav-item">
                <i class="bi bi-arrow-left"></i> 메인으로 가기
            </a>
            <a href="/logout.php" class="adm-nav-item" style="color:rgba(255,255,255,.35);">
                <i class="bi bi-box-arrow-right"></i> 로그아웃
            </a>
        </div>
    </nav>

    <nav class="adm-mobile-nav">
        <?php foreach ($menus as $key => $menu): ?>
            <?php if($key === 'stats') continue; ?>
            <a href="<?= $menu['url'] ?>" class="adm-mob-item <?= ($admin_menu === $key) ? 'active' : '' ?>">
                <i class="bi <?= $menu['icon'] ?>"></i>
                <span><?= $menu['label'] ?></span>
                <?php if (!empty($menu['badge']) && $menu['badge'] > 0): ?>
                    <div class="adm-mob-badge"></div>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <main class="adm-main">
        <div class="adm-topbar">
            <div>
                <div class="adm-page-title">
                    <?= htmlspecialchars($menus[$admin_menu]['label'] ?? '관리자') ?>
                </div>
                <div class="adm-page-sub" id="adm-date-sub"></div>
            </div>
            <div class="adm-admin-tag">
                <div class="adm-online-dot"></div>
                <?= htmlspecialchars($_SESSION['nickname'] ?? '관리자') ?>님
            </div>
        </div>
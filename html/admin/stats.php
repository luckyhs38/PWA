<?php
// /admin/stats.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_admin($pdo);

$admin_menu = 'stats';

$period = $_GET['period'] ?? '7'; // 7 | 30 | 90
$allowed_periods = ['7', '30', '90'];
if (!in_array($period, $allowed_periods)) $period = '7';

try {
    // ── 전체 요약 통계 ────────────────────────────────────────
    $summary = $pdo->query("
        SELECT
            (SELECT COUNT(*) FROM users WHERE deleted_at IS NULL)          AS total_users,
            (SELECT COUNT(*) FROM users WHERE status = 1
             AND deleted_at IS NULL)                                        AS active_users,
            (SELECT COUNT(*) FROM users WHERE role = 'writer'
             AND deleted_at IS NULL)                                        AS writer_count,
            (SELECT COUNT(*) FROM boards WHERE hidden_yn = 'N')            AS total_posts,
            (SELECT COUNT(*) FROM boards WHERE board_type = 'writing'
             AND hidden_yn = 'N')                                           AS writing_posts,
            (SELECT COUNT(*) FROM comments WHERE deleted_at IS NULL
             AND hidden_yn = 'N')                                           AS total_comments,
            (SELECT COUNT(*) FROM quotes)                                   AS total_quotes,
            (SELECT COUNT(*) FROM qna WHERE deleted_at IS NULL)            AS total_qna,
            (SELECT COUNT(*) FROM qna WHERE status = 'answered'
             AND deleted_at IS NULL)                                        AS answered_qna
    ")->fetch(PDO::FETCH_ASSOC);

    // ── 일별 가입자 수 (최근 N일) ─────────────────────────────
    $daily_users = $pdo->prepare("
        SELECT DATE(created_at) AS date, COUNT(*) AS cnt
        FROM users
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
          AND deleted_at IS NULL
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
    $daily_users->execute([':days' => (int)$period]);
    $daily_users = $daily_users->fetchAll(PDO::FETCH_ASSOC);

    // ── 일별 게시글 수 ────────────────────────────────────────
    $daily_posts = $pdo->prepare("
        SELECT DATE(created_at) AS date, COUNT(*) AS cnt
        FROM boards
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
          AND hidden_yn = 'N'
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
    $daily_posts->execute([':days' => (int)$period]);
    $daily_posts = $daily_posts->fetchAll(PDO::FETCH_ASSOC);

    // ── 일별 문장 저장 수 ─────────────────────────────────────
    $daily_quotes = $pdo->prepare("
        SELECT DATE(created_at) AS date, COUNT(*) AS cnt
        FROM quotes
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
    $daily_quotes->execute([':days' => (int)$period]);
    $daily_quotes = $daily_quotes->fetchAll(PDO::FETCH_ASSOC);

    // ── 게시판 타입 비율 ──────────────────────────────────────
    $board_ratio = $pdo->query("
        SELECT board_type, COUNT(*) AS cnt
        FROM boards WHERE hidden_yn = 'N'
        GROUP BY board_type
    ")->fetchAll(PDO::FETCH_ASSOC);

    // ── 작가 신청 처리 현황 ───────────────────────────────────
    $apply_stats = $pdo->query("
        SELECT status, COUNT(*) AS cnt
        FROM writer_applications
        GROUP BY status
    ")->fetchAll(PDO::FETCH_KEY_PAIR);

    // ── 날짜 배열 생성 (빈 날짜 채우기) ──────────────────────
    function fill_dates(array $data, int $days): array {
        $map = [];
        foreach ($data as $row) $map[$row['date']] = (int)$row['cnt'];
        $result = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $result[] = ['date' => $date, 'cnt' => $map[$date] ?? 0];
        }
        return $result;
    }

    $daily_users  = fill_dates($daily_users,  (int)$period);
    $daily_posts  = fill_dates($daily_posts,  (int)$period);
    $daily_quotes = fill_dates($daily_quotes, (int)$period);

    // 최대값 (막대 높이 계산용)
    $max_users  = max(array_column($daily_users,  'cnt') ?: [1]);
    $max_posts  = max(array_column($daily_posts,  'cnt') ?: [1]);
    $max_quotes = max(array_column($daily_quotes, 'cnt') ?: [1]);

} catch (PDOException $e) {
    die("오류: " . $e->getMessage());
}

// 문의 처리율
$total_qna    = (int)$summary['total_qna'];
$answered_qna = (int)$summary['answered_qna'];
$qna_rate     = $total_qna > 0 ? round($answered_qna / $total_qna * 100) : 0;

// 게시판 비율 계산
$board_map   = array_column($board_ratio, 'cnt', 'board_type');
$total_board = array_sum($board_map) ?: 1;
$anon_pct    = round(($board_map['anonymity'] ?? 0) / $total_board * 100);
$writing_pct = 100 - $anon_pct;

include '_layout.php';
?>

<style>
/* 기간 탭 */
.period-tabs { display:flex; gap:6px; margin-bottom:20px; }
.period-tab {
    border:1px solid #eee; background:#fff; border-radius:6px;
    padding:7px 16px; font-size:13px; color:#888;
    cursor:pointer; text-decoration:none; transition:all .15s;
    font-family:inherit;
}
.period-tab:hover  { border-color:#aaa; color:#333; }
.period-tab.active { background:#1a1a1a; color:#fff; border-color:#1a1a1a; }

/* 요약 카드 */
.stats-summary {
    display:grid;
    grid-template-columns: repeat(3, 1fr);
    gap:14px;
    margin-bottom:20px;
}
.summary-card {
    background:#fff;
    border:1px solid #eee;
    border-radius:10px;
    padding:18px 20px;
}
.summary-label { font-size:11px; color:#bbb; margin-bottom:6px; letter-spacing:.2px; }
.summary-val   { font-size:26px; font-weight:500; color:#1a1a1a; margin-bottom:4px; }
.summary-sub   { font-size:12px; color:#aaa; }

/* 차트 공통 */
.chart-wrap { margin-bottom:20px; }
.chart-title {
    font-size:14px; font-weight:500; color:#1a1a1a;
    margin-bottom:14px; padding-bottom:10px;
    border-bottom:1px solid #f0f0f0;
    display:flex; justify-content:space-between; align-items:center;
}
.chart-total { font-size:12px; color:#bbb; font-weight:400; }

/* 막대 차트 */
.bar-chart {
    display:flex;
    align-items:flex-end;
    gap:2px;
    height:120px;
    padding-bottom:24px;
    position:relative;
    overflow-x:auto;
}
.bar-col {
    display:flex;
    flex-direction:column;
    align-items:center;
    flex:1;
    min-width:12px;
    height:100%;
    justify-content:flex-end;
    position:relative;
}
.bar-fill {
    width:100%;
    border-radius:3px 3px 0 0;
    min-height:2px;
    transition:height .3s;
    position:relative;
}
.bar-fill:hover::after {
    content: attr(data-val);
    position:absolute;
    top:-22px; left:50%;
    transform:translateX(-50%);
    background:#1a1a1a; color:#fff;
    font-size:10px; padding:2px 6px;
    border-radius:4px; white-space:nowrap;
    z-index:10;
}
.bar-label {
    position:absolute;
    bottom:-20px;
    font-size:9px; color:#ccc;
    white-space:nowrap;
    transform:rotate(-30deg);
    transform-origin:top center;
}

/* 가로 비율 바 */
.ratio-bar-wrap { margin-bottom:14px; }
.ratio-bar-label {
    display:flex; justify-content:space-between;
    font-size:12px; color:#555; margin-bottom:6px;
}
.ratio-bar {
    height:10px; background:#f0f0f0; border-radius:999px; overflow:hidden;
}
.ratio-bar-fill {
    height:100%; border-radius:999px; transition:width .4s;
}

/* 원형 진행률 */
.circle-stat {
    display:flex; flex-direction:column; align-items:center;
    padding:20px;
}
.circle-num  { font-size:28px; font-weight:500; color:#1a1a1a; }
.circle-label { font-size:12px; color:#bbb; margin-top:4px; }

/* 2컬럼 */
.stats-grid-2 {
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:16px;
    margin-bottom:20px;
}

/* 반응형 */
@media (max-width: 768px) {
    .stats-summary  { grid-template-columns:1fr 1fr; }
    .stats-grid-2   { grid-template-columns:1fr; }
    .bar-chart      { height:100px; }
    .bar-label      { font-size:8px; }
}
@media (max-width: 480px) {
    .stats-summary { grid-template-columns:1fr 1fr; }
    .summary-val   { font-size:22px; }
}
</style>

<!-- 기간 탭 -->
<div class="period-tabs">
    <?php foreach (['7' => '최근 7일', '30' => '최근 30일', '90' => '최근 90일'] as $val => $label): ?>
        <a href="?period=<?= $val ?>"
           class="period-tab <?= $period === $val ? 'active' : '' ?>">
            <?= $label ?>
        </a>
    <?php endforeach; ?>
</div>

<!-- 요약 통계 -->
<div class="stats-summary">
    <div class="summary-card">
        <div class="summary-label">전체 회원</div>
        <div class="summary-val"><?= number_format($summary['total_users']) ?></div>
        <div class="summary-sub">활성 <?= number_format($summary['active_users']) ?>명 · 작가 <?= number_format($summary['writer_count']) ?>명</div>
    </div>
    <div class="summary-card">
        <div class="summary-label">전체 게시글</div>
        <div class="summary-val"><?= number_format($summary['total_posts']) ?></div>
        <div class="summary-sub">작가방 <?= number_format($summary['writing_posts']) ?>건</div>
    </div>
    <div class="summary-card">
        <div class="summary-label">저장된 문장</div>
        <div class="summary-val"><?= number_format($summary['total_quotes']) ?></div>
        <div class="summary-sub">댓글 <?= number_format($summary['total_comments']) ?>개</div>
    </div>
</div>

<!-- 일별 가입자 차트 -->
<div class="adm-card chart-wrap" style="margin-bottom:16px;">
    <div class="chart-title">
        일별 신규 가입자
        <span class="chart-total">
            총 <?= number_format(array_sum(array_column($daily_users, 'cnt'))) ?>명
        </span>
    </div>
    <div class="bar-chart">
        <?php foreach ($daily_users as $row):
            $height = $max_users > 0 ? max(2, round($row['cnt'] / $max_users * 96)) : 2;
            $label  = date('m/d', strtotime($row['date']));
        ?>
            <div class="bar-col">
                <div class="bar-fill"
                     style="height:<?= $height ?>px; background:#1a1a1a;"
                     data-val="<?= $row['cnt'] ?>명"></div>
                <?php if ((int)$period <= 30): ?>
                    <span class="bar-label"><?= $label ?></span>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- 일별 게시글 + 문장 저장 차트 -->
<div class="stats-grid-2">
    <div class="adm-card" style="margin-bottom:0;">
        <div class="chart-title">
            일별 게시글
            <span class="chart-total">
                총 <?= number_format(array_sum(array_column($daily_posts, 'cnt'))) ?>건
            </span>
        </div>
        <div class="bar-chart">
            <?php foreach ($daily_posts as $row):
                $height = $max_posts > 0 ? max(2, round($row['cnt'] / $max_posts * 96)) : 2;
                $label  = date('m/d', strtotime($row['date']));
            ?>
                <div class="bar-col">
                    <div class="bar-fill"
                         style="height:<?= $height ?>px; background:#4a6fa5;"
                         data-val="<?= $row['cnt'] ?>건"></div>
                    <?php if ((int)$period <= 14): ?>
                        <span class="bar-label"><?= $label ?></span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="adm-card" style="margin-bottom:0;">
        <div class="chart-title">
            일별 문장 저장
            <span class="chart-total">
                총 <?= number_format(array_sum(array_column($daily_quotes, 'cnt'))) ?>건
            </span>
        </div>
        <div class="bar-chart">
            <?php foreach ($daily_quotes as $row):
                $height = $max_quotes > 0 ? max(2, round($row['cnt'] / $max_quotes * 96)) : 2;
                $label  = date('m/d', strtotime($row['date']));
            ?>
                <div class="bar-col">
                    <div class="bar-fill"
                         style="height:<?= $height ?>px; background:#4caf50;"
                         data-val="<?= $row['cnt'] ?>건"></div>
                    <?php if ((int)$period <= 14): ?>
                        <span class="bar-label"><?= $label ?></span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- 게시판 비율 + 문의 처리율 + 작가 신청 현황 -->
<div class="stats-grid-2" style="margin-bottom:0;">

    <div class="adm-card" style="margin-bottom:0;">
        <div class="chart-title">게시판 비율</div>

        <div class="ratio-bar-wrap">
            <div class="ratio-bar-label">
                <span>익명글</span>
                <span><?= number_format($board_map['anonymity'] ?? 0) ?>건 (<?= $anon_pct ?>%)</span>
            </div>
            <div class="ratio-bar">
                <div class="ratio-bar-fill"
                     style="width:<?= $anon_pct ?>%; background:#1a1a1a;"></div>
            </div>
        </div>

        <div class="ratio-bar-wrap">
            <div class="ratio-bar-label">
                <span>작가방</span>
                <span><?= number_format($board_map['writing'] ?? 0) ?>건 (<?= $writing_pct ?>%)</span>
            </div>
            <div class="ratio-bar">
                <div class="ratio-bar-fill"
                     style="width:<?= $writing_pct ?>%; background:#4a6fa5;"></div>
            </div>
        </div>

        <div style="margin-top:24px;">
            <div class="chart-title" style="margin-bottom:12px;">문의 처리율</div>
            <div class="ratio-bar-wrap">
                <div class="ratio-bar-label">
                    <span>답변 완료</span>
                    <span><?= $answered_qna ?>건 / <?= $total_qna ?>건 (<?= $qna_rate ?>%)</span>
                </div>
                <div class="ratio-bar">
                    <div class="ratio-bar-fill"
                         style="width:<?= $qna_rate ?>%; background:#4caf50;"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="adm-card" style="margin-bottom:0;">
        <div class="chart-title">작가 신청 현황</div>

        <?php
        $apply_total    = array_sum($apply_stats) ?: 1;
        $apply_statuses = [
            'pending'  => ['label' => '대기 중',  'color' => '#f5a623'],
            'approved' => ['label' => '승인됨',   'color' => '#4caf50'],
            'rejected' => ['label' => '거절됨',   'color' => '#dc3545'],
        ];
        foreach ($apply_statuses as $key => $info):
            $cnt = (int)($apply_stats[$key] ?? 0);
            $pct = round($cnt / $apply_total * 100);
        ?>
            <div class="ratio-bar-wrap">
                <div class="ratio-bar-label">
                    <span><?= $info['label'] ?></span>
                    <span><?= number_format($cnt) ?>건 (<?= $pct ?>%)</span>
                </div>
                <div class="ratio-bar">
                    <div class="ratio-bar-fill"
                         style="width:<?= $pct ?>%; background:<?= $info['color'] ?>;"></div>
                </div>
            </div>
        <?php endforeach; ?>

        <div style="margin-top:24px; display:grid; grid-template-columns:repeat(3,1fr); gap:10px;">
            <?php foreach ($apply_statuses as $key => $info): ?>
                <div style="text-align:center; padding:14px 10px; background:#fafafa;
                            border:1px solid #eee; border-radius:8px;">
                    <div style="font-size:20px; font-weight:500; color:#1a1a1a;">
                        <?= number_format((int)($apply_stats[$key] ?? 0)) ?>
                    </div>
                    <div style="font-size:11px; color:#bbb; margin-top:3px;">
                        <?= $info['label'] ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

</div>

<?php include '_layout_end.php'; ?>

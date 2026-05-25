<?php
// /intro.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'includes/db.php';

try {
    // 현재 월 주제 조회
    // 1순위: is_current = 1
    // 2순위: 현재 연월과 일치
    // 3순위: 가장 최신 주제
    $now_year  = (int)date('Y');
    $now_month = (int)date('n');

    $topic = $pdo->prepare("
        SELECT * FROM monthly_topics
        WHERE is_current = 1
        LIMIT 1
    ");
    $topic->execute();
    $current_topic = $topic->fetch(PDO::FETCH_ASSOC);

    // is_current가 없으면 현재 연월로 조회
    if (!$current_topic) {
        $topic = $pdo->prepare("
            SELECT * FROM monthly_topics
            WHERE theme_year = :year AND theme_month = :month
            LIMIT 1
        ");
        $topic->execute([':year' => $now_year, ':month' => $now_month]);
        $current_topic = $topic->fetch(PDO::FETCH_ASSOC);
    }

    // 그것도 없으면 가장 최신 주제
    if (!$current_topic) {
        $current_topic = $pdo->query("
            SELECT * FROM monthly_topics
            ORDER BY theme_year DESC, theme_month DESC
            LIMIT 1
        ")->fetch(PDO::FETCH_ASSOC);
    }

    // 지난 주제 목록 (현재 주제 제외, 최근 4개)
    $past_params = [];
    $past_exclude = '';
    if ($current_topic) {
        $past_exclude = "WHERE id != :current_id";
        $past_params[':current_id'] = $current_topic['id'];
    }
    $past_topics = $pdo->prepare("
        SELECT * FROM monthly_topics
        {$past_exclude}
        ORDER BY theme_year DESC, theme_month DESC
        LIMIT 4
    ");
    $past_topics->execute($past_params);
    $past_topics = $past_topics->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $current_topic = null;
    $past_topics   = [];
}

// 월 영문 변환
$month_names = [
    1=>'JAN', 2=>'FEB', 3=>'MAR', 4=>'APR',
    5=>'MAY', 6=>'JUN', 7=>'JUL', 8=>'AUG',
    9=>'SEP', 10=>'OCT', 11=>'NOV', 12=>'DEC'
];

include 'includes/header.php';
?>

<style>
    /* 전체 배경 설정 */
    @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@400;500;700&family=Noto+Serif+KR:wght@400;500;600;700&display=swap');

    body {
        background-color: #ffffff;
        margin: 0;
        padding: 0;
        -webkit-text-size-adjust: 100%; /* 💡 모바일 글씨 커짐 방지용 추가 */
    }

    .intro-body {
        color: #000000;
        font-family: 'Noto Serif KR', serif;
        width: 100%;
        display: flex;
        flex-direction: column;
        align-items: center;
    }

    .intro-container {
        width: 100%;
        max-width: 1100px;
        margin: 0 auto;
        padding: 0 20px;
    }

    /* 히어로 섹션 */
    .intro-hero {
        padding: 140px 0 100px;
        text-align: center;
        width: 100%;
    }

    .intro-hero h1 {
        font-size: 3.5rem;
        font-weight: 800;
        margin-bottom: 24px;
        letter-spacing: -1px;
        line-height: 1.3;
        word-break: keep-all;
    }

    .intro-hero p {
        font-size: 1.15rem;
        color: #444;
        margin-bottom: 40px;
        font-family: sans-serif;
    }

    .intro-cta {
        display: inline-block;
        background-color: #000;
        color: #fff;
        padding: 18px 40px;
        text-decoration: none;
        font-weight: 600;
        font-family: sans-serif;
        transition: background-color 0.3s;
    }

    .intro-cta:hover {
        background-color: #333;
    }

    /* 특징 섹션 */
    .intro-features {
        padding: 100px 0;
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 40px;
        border-top: 1px solid #000;
        width: 100%;
    }

    .feature-item {
        text-align: left;
    }

    .feature-item h2 {
        font-size: 1.8rem;
        margin-bottom: 15px;
    }

    .feature-item p {
        color: #555;
        font-size: 0.95rem;
        font-family: sans-serif;
    }

    /* 현재 주제 섹션 */
    .current-theme-box {
        background-color: #f9f9f9;
        padding: 80px 0;
        text-align: center;
        width: 100%;
    }

    .theme-label {
        display: inline-block;
        border: 1px solid #000;
        padding: 4px 12px;
        font-size: 0.8rem;
        margin-bottom: 20px;
        font-family: sans-serif;
    }

    .theme-title {
        font-size: 2.2rem;
        margin-bottom: 15px;
    }

    .theme-desc {
        font-family: sans-serif;
        color: #666;
        font-size: 0.95rem;
        margin-bottom: 0;
    }

    .theme-no-topic {
        font-family: sans-serif;
        color: #aaa;
        font-size: 1rem;
    }

    /* 아카이브 */
    .intro-archive {
        padding: 100px 0;
        width: 100%;
    }

    .intro-archive h2 {
        font-size: 1.8rem;
        margin-bottom: 0;
    }

    .archive-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-top: 40px;
    }

    .archive-card {
        border: 1px solid #eee;
        padding: 30px;
        font-family: sans-serif;
        text-align: left;
        transition: border-color .2s;
    }

    .archive-card:hover {
        border-color: #aaa;
    }

    .archive-card h3 {
        font-family: 'Noto Serif KR', serif;
        margin-top: 10px;
        font-size: 1.2rem;
    }

    .archive-card-desc {
        color: #888;
        font-size: 0.85rem;
        margin-top: 8px;
        line-height: 1.6;
    }

    .archive-empty {
        grid-column: 1 / -1;
        text-align: center;
        color: #bbb;
        font-family: sans-serif;
        padding: 40px 0;
        font-size: 0.9rem;
    }

    @media (max-width: 768px) {
        .intro-features, .archive-grid { grid-template-columns: 1fr; }
        .intro-hero h1 { font-size: 2.5rem; }
        .feature-item { text-align: center; }
    }
</style>

<div class="intro-body">

    <!-- 히어로 -->
    <div class="intro-container">
        <section class="intro-hero">
            <h1>잡으려 할수록,<br>한글은 늘 도망가.</h1>
            <p>한 달에 한 번, 하나의 주제.<br>이름 뒤에 숨어 자유롭게 기록하는 우리들의 문장들.</p>
            <a href="board/list.php?type=writing" class="intro-cta">오늘의 문장 남기기</a>
        </section>

        <section class="intro-features">
            <div class="feature-item">
                <h2>01</h2>
                <p><strong>Monthly Topic</strong><br>매달 친구들과 함께 쓸 새로운 주제가 선정됩니다.</p>
            </div>
            <div class="feature-item">
                <h2>02</h2>
                <p><strong>Fully Anonymous</strong><br>익명으로 작성되어 오직 문장에만 집중할 수 있습니다.</p>
            </div>
            <div class="feature-item">
                <h2>03</h2>
                <p><strong>Free Form</strong><br>형식은 상관없습니다. 도망가는 생각을 자유롭게 붙잡으세요.</p>
            </div>
        </section>
    </div>

    <!-- 현재 주제 -->
    <section class="current-theme-box">
        <div class="intro-container">
            <?php if ($current_topic): ?>
                <?php
                    $mon  = (int)$current_topic['theme_month'];
                    $year = (int)$current_topic['theme_year'];
                    $mon_str = ($month_names[$mon] ?? '') . ' ' . $year;
                    $is_current_month = ($year === $now_year && $mon === $now_month);
                ?>
                <span class="theme-label">
                    <?= htmlspecialchars($mon_str) ?> TOPIC
                    <?php if (!$is_current_month): ?>
                        <span style="color:#aaa; margin-left:6px; font-size:0.75rem;">
                            (<?= $now_month ?>월 주제 준비 중)
                        </span>
                    <?php endif; ?>
                </span>
                <h2 class="theme-title">"<?= htmlspecialchars($current_topic['title']) ?>"</h2>
                <?php if (!empty($current_topic['description'])): ?>
                    <p class="theme-desc"><?= htmlspecialchars($current_topic['description']) ?></p>
                <?php else: ?>
                    <p class="theme-desc">이번 달 주제에 대해 당신의 솔직한 글을 남겨주세요.</p>
                <?php endif; ?>
            <?php else: ?>
                <span class="theme-label">TOPIC</span>
                <p class="theme-no-topic">이번 달 주제가 아직 준비 중입니다.</p>
            <?php endif; ?>
        </div>
    </section>

    <!-- 지난 기록 -->
    <div class="intro-container">
        <section class="intro-archive">
            <h2>지난 기록</h2>
            <div class="archive-grid">
                <?php if (empty($past_topics)): ?>
                    <div class="archive-empty">아직 지난 주제가 없습니다.</div>
                <?php else: ?>
                    <?php foreach ($past_topics as $t): ?>
                        <div class="archive-card">
                            <span style="color:#999; font-size:0.8rem;">
                                <?= $t['theme_year'] ?>.<?= str_pad($t['theme_month'], 2, '0', STR_PAD_LEFT) ?>
                            </span>
                            <h3>주제: <?= htmlspecialchars($t['title']) ?></h3>
                            <?php if (!empty($t['description'])): ?>
                                <p class="archive-card-desc"><?= htmlspecialchars($t['description']) ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </div>

</div>

<?php include 'includes/footer.php'; ?>
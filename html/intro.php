<?php include 'includes/header.php'; ?>
<?php include "./includes/db.php"; ?>

<style>
    /* 전체 배경 설정 */
    body {
        background-color: #ffffff;
        margin: 0;
        padding: 0;
    }

    .intro-body {
        color: #000000;
        font-family: 'Nanum Myeongjo', serif;
        /* 중앙 정렬을 위한 핵심 설정 */
        width: 100%;
        display: flex;
        flex-direction: column;
        align-items: center;
    }

    .intro-container {
        width: 100%;
        max-width: 1100px; /* 컨텐츠 최대 너비 */
        margin: 0 auto;    /* 좌우 여백 자동 계산 (가운데 정렬) */
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

    /* 현재 주제 섹션 (화면 전체 너비 배경) */
    .current-theme-box {
        background-color: #f9f9f9;
        padding: 80px 0;
        text-align: center;
        width: 100%; /* 부모가 flex-center이므로 전체 너비 차지 */
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

    /* 아카이브 */
    .intro-archive {
        padding: 100px 0;
        width: 100%;
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
    }

    .archive-card h3 {
        font-family: 'Nanum Myeongjo', serif;
        margin-top: 10px;
    }

    @media (max-width: 768px) {
        .intro-features, .archive-grid { grid-template-columns: 1fr; }
        .intro-hero h1 { font-size: 2.5rem; }
        .feature-item { text-align: center; }
    }
</style>

<div class="intro-body">
    <!-- 상단 섹션들 -->
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

    <!-- 배경이 꽉 차야 하는 섹션 -->
    <section class="current-theme-box">
        <div class="intro-container">
            <span class="theme-label">MAY 2026 TOPIC</span>
            <h2 class="theme-title">"디저트"</h2>
            <p style="font-family: sans-serif; color: #666;">이번 달 주제에 대해 당신의 솔직한 글을 남겨주세요.</p>
        </div>
    </section>

    <!-- 하단 섹션 -->
    <div class="intro-container">
        <section class="intro-archive">
            <h2>지난 기록</h2>
            <div class="archive-grid">
                <div class="archive-card">
                    <span style="color:#999; font-size:0.8rem;">2026.04</span>
                    <h3>주제: 거짓말</h3>
                </div>
                <div class="archive-card">
                    <span style="color:#999; font-size:0.8rem;">2026.03</span>
                    <h3>주제: 편지</h3>
                </div>
            </div>
        </section>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
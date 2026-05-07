<?php
// intro.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include './includes/header.php';
?>
 
<main class="intro-page">

    <!-- Hero Section -->
    <section class="intro-hero">
        <div class="container">
            <div class="intro-hero-inner">
                <p class="intro-eyebrow">Monthly Anonymous Writing Club</p>

                <h1 class="intro-title">
                    한글은 늘 도망가
                </h1>

                <p class="intro-subtitle">
                    한 달에 하나의 주제.<br>
                    우리는 이름을 잠시 내려놓고,<br class="d-none d-md-block">
                    각자의 방식으로 글을 씁니다.
                </p>

                <div class="intro-buttons">
                    <a href="/board/list.php?type=anonymity" class="btn btn-dark">
                        익명글 보러가기
                    </a>
                    <a href="/cal/cal.php" class="btn btn-outline-dark">
                        이번 달 일정 보기
                    </a>
                </div>
            </div>
        </div>
    </section>


    <!-- About Section -->
    <section class="intro-section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-9">
                    <h2 class="section-title">우리가 쓰는 방식</h2>

                    <p class="section-text">
                        <strong>한글은 늘 도망가</strong>는 친구들이 모여 매달 하나의 주제를 정하고,
                        그 주제에 대해 익명으로 글을 남기는 작은 글쓰기 공간입니다.
                    </p>

                    <p class="section-text">
                        글의 형식은 정해져 있지 않습니다.
                        일기여도 좋고, 편지여도 좋고, 시나 소설, 짧은 메모여도 괜찮습니다.
                        중요한 건 잘 쓰는 것이 아니라, 각자의 마음을 자기 방식으로 남기는 것입니다.
                    </p>
                </div>
            </div>
        </div>
    </section>


    <!-- Rule Section -->
    <section class="intro-rule-section">
        <div class="container">
            <div class="row g-4">

                <div class="col-md-4">
                    <div class="intro-card">
                        <span class="card-number">01</span>
                        <h3>매달 하나의 주제</h3>
                        <p>
                            한 달에 한 번, 모두가 같은 주제를 받습니다.
                            같은 문장에서 시작해도 각자의 글은 전혀 다른 곳으로 흘러갑니다.
                        </p>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="intro-card">
                        <span class="card-number">02</span>
                        <h3>익명으로 쓰기</h3>
                        <p>
                            이름을 지우면 더 솔직해질 때가 있습니다.
                            누가 썼는지보다 어떤 마음이 담겼는지를 먼저 봅니다.
                        </p>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="intro-card">
                        <span class="card-number">03</span>
                        <h3>형식은 자유롭게</h3>
                        <p>
                            긴 글도, 짧은 글도 괜찮습니다.
                            시, 산문, 편지, 독백, 기록 등 어떤 형태든 자유롭게 남깁니다.
                        </p>
                    </div>
                </div>

            </div>
        </div>
    </section>


    <!-- Monthly Topic Section -->
    <section class="intro-topic-section">
        <div class="container">
            <div class="topic-box">
                <p class="topic-label">This Month's Topic</p>
                <h2>이번 달 주제</h2>
                <p class="topic-title">
                    “아직 말하지 못한 것”
                </p>
                <p class="topic-desc">
                    마음속에 남아 있었지만 차마 꺼내지 못했던 말,
                    지나고 나서야 알게 된 감정,
                    혹은 지금도 망설이고 있는 이야기를 자유롭게 써보세요.
                </p>

                <a href="/board/write.php?type=anonymity" class="btn btn-dark">
                    이 주제로 글쓰기
                </a>
            </div>
        </div>
    </section>


    <!-- Closing Section -->
    <section class="intro-closing">
        <div class="container">
            <p>
                글은 늘 도망가지만,<br>
                우리는 또 붙잡아 적어봅니다.
            </p>
        </div>
    </section>

</main>


<style>
/* ==============================
   Intro Page
============================== */

.intro-page {
    background-color: #fff;
    color: #111;
    font-family: 'Noto Sans KR', sans-serif;
}

/* Hero */
.intro-hero {
    min-height: 72vh;
    display: flex;
    align-items: center;
    border-bottom: 1px solid #eee;
}

.intro-hero-inner {
    max-width: 780px;
    padding: 80px 0;
}

.intro-eyebrow {
    font-size: 0.8rem;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: #777;
    margin-bottom: 18px;
}

.intro-title {
    font-family: 'Noto Serif KR', serif;
    font-size: clamp(2.4rem, 6vw, 5rem);
    font-weight: 500;
    letter-spacing: -0.04em;
    margin-bottom: 28px;
}

.intro-subtitle {
    font-family: 'Noto Serif KR', serif;
    font-size: clamp(1.15rem, 2.2vw, 1.6rem);
    line-height: 1.9;
    color: #333;
    margin-bottom: 36px;
}

.intro-buttons {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

/* Section */
.intro-section {
    padding: 90px 0;
}

.section-title {
    font-family: 'Noto Serif KR', serif;
    font-size: 2rem;
    font-weight: 500;
    margin-bottom: 32px;
}

.section-text {
    font-size: 1.05rem;
    line-height: 2;
    color: #333;
    margin-bottom: 18px;
}

/* Rule Cards */
.intro-rule-section {
    padding: 80px 0;
    background-color: #fafafa;
    border-top: 1px solid #eee;
    border-bottom: 1px solid #eee;
}

.intro-card {
    height: 100%;
    background-color: #fff;
    border: 1px solid #e5e5e5;
    padding: 34px 30px;
    transition: all 0.25s ease;
}

.intro-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 14px 30px rgba(0, 0, 0, 0.06);
}

.card-number {
    display: inline-block;
    font-family: 'Noto Serif KR', serif;
    font-size: 0.85rem;
    color: #999;
    margin-bottom: 20px;
}

.intro-card h3 {
    font-family: 'Noto Serif KR', serif;
    font-size: 1.35rem;
    font-weight: 500;
    margin-bottom: 18px;
}

.intro-card p {
    color: #555;
    line-height: 1.8;
    font-size: 0.95rem;
    margin-bottom: 0;
}

/* Topic */
.intro-topic-section {
    padding: 100px 0;
}

.topic-box {
    border: 1px solid #111;
    padding: 56px 48px;
    max-width: 860px;
    margin: 0 auto;
    text-align: center;
}

.topic-label {
    font-size: 0.75rem;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: #777;
    margin-bottom: 18px;
}

.topic-box h2 {
    font-family: 'Noto Serif KR', serif;
    font-size: 1.4rem;
    font-weight: 500;
    margin-bottom: 24px;
}

.topic-title {
    font-family: 'Noto Serif KR', serif;
    font-size: clamp(1.7rem, 4vw, 2.8rem);
    font-weight: 500;
    margin-bottom: 28px;
}

.topic-desc {
    max-width: 620px;
    margin: 0 auto 34px;
    line-height: 1.9;
    color: #444;
}

/* Closing */
.intro-closing {
    padding: 90px 0 110px;
    text-align: center;
    border-top: 1px solid #eee;
}

.intro-closing p {
    font-family: 'Noto Serif KR', serif;
    font-size: clamp(1.5rem, 3vw, 2.3rem);
    line-height: 1.8;
    color: #111;
}

/* Mobile */
@media (max-width: 768px) {
    .intro-hero {
        min-height: auto;
    }

    .intro-hero-inner {
        padding: 64px 0;
    }

    .intro-section,
    .intro-rule-section,
    .intro-topic-section {
        padding: 64px 0;
    }

    .topic-box {
        padding: 40px 24px;
    }

    .intro-buttons .btn {
        width: 100%;
    }
}
</style>

<?php include './includes/footer.php'; ?>
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include './includes/header.php';
?>

<main class="home-typing-page">
    <section class="home-typing-hero">

        <div class="bg-type bg-type-1">write anonymously</div>
        <div class="bg-type bg-type-2">monthly topic</div>
        <div class="bg-type bg-type-3">한 달에 하나의 주제</div>

        <div class="home-center-box">

            <p class="home-small-text">
                Monthly Anonymous Writing Club
            </p>

            <h1 class="home-main-title">
                <span id="typingTitle"></span><span class="typing-cursor"></span>
            </h1>

            <p class="home-description">
                한 달에 하나의 주제로<br>
                이름을 잠시 내려놓고<br>
                각자의 방식으로 글을 남기는 공간
            </p>

            <div class="home-topic-box">
                <span class="topic-label">이번 달의 문장</span>
                <span id="typingTopic" class="topic-text"></span>
            </div>

            <div class="home-btn-area">
                <a href="/board/list.php?type=anonymity" class="btn btn-dark home-btn">
                    익명글 보러가기
                </a>
                <a href="/intro.php" class="btn btn-outline-dark home-btn">
                    소개 보기
                </a>
            </div>

        </div>

    </section>
</main>

<style>
/* ==============================
   Home Typing Page
============================== */
.home-typing-page {
    width: 100%;
    background: #fff;
    color: #111;
    overflow: hidden;
    padding : 0px;
}

/* 핵심: 화면 전체를 잡고 가운데 정렬 */
.home-typing-hero {
    position: relative;

    width: 100vw;
    min-height: calc(100vh - 80px);

    margin-left: calc(50% - 50vw);

    display: flex;
    justify-content: center;
    align-items: center;

    text-align: center;
    padding: 80px 20px;

    background:
        radial-gradient(circle at 20% 20%, rgba(0,0,0,0.035), transparent 24%),
        radial-gradient(circle at 80% 80%, rgba(0,0,0,0.03), transparent 26%),
        #fff;
}

/* 실제 가운데 내용 박스 */
.home-center-box {
position: relative;
    z-index: 2;
    width: 100%;
    max-width: 820px;
    margin-left: auto;
    margin-right: auto;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;

}

/* 작은 영문 */
.home-small-text {
    font-family: 'Noto Sans KR', sans-serif;
    font-size: 0.78rem;
    letter-spacing: 0.22em;
    text-transform: uppercase;
    color: #777;
    margin-bottom: 24px;
}

/* 메인 제목 */
.home-main-title {
    min-height: 1.25em;
    font-family: 'Noto Serif KR', serif;
    font-size: clamp(3.2rem, 8vw, 6.8rem);
    font-weight: 500;
    line-height: 1.12;
    letter-spacing: -0.06em;
    margin-bottom: 34px;
    text-align: center;
}

/* 커서 */
.typing-cursor {
    display: inline-block;
    width: 2px;
    height: 0.85em;
    background: #111;
    margin-left: 8px;
    vertical-align: -0.05em;
    animation: blink 0.8s infinite;
}

/* 설명 */
.home-description {
    font-family: 'Noto Serif KR', serif;
    font-size: clamp(1.1rem, 2.1vw, 1.45rem);
    line-height: 1.9;
    color: #333;
    margin-bottom: 34px;
    text-align: center;
}

/* 이번 달 문장 */
.home-topic-box {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;

    border-top: 1px solid #111;
    border-bottom: 1px solid #111;

    padding: 14px 4px;
    margin-bottom: 38px;

    width: min(520px, 100%);
    text-align: center;
}

.topic-label {
    font-family: 'Noto Sans KR', sans-serif;
    font-size: 0.82rem;
    color: #777;
    white-space: nowrap;
}

.topic-text {
    font-family: 'Noto Serif KR', serif;
    font-size: 1.05rem;
    color: #111;
    min-height: 1.6em;
}

/* 버튼 */
.home-btn-area {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.home-btn {
    min-width: 150px;
    border-radius: 0;
    padding: 11px 24px;
    font-size: 0.92rem;
}

/* 배경 타이포 장식 */
.bg-type {
    position: absolute;
    z-index: 1;
    font-family: 'Noto Serif KR', serif;
    color: rgba(0, 0, 0, 0.035);
    pointer-events: none;
    user-select: none;
    white-space: nowrap;
}

.bg-type-1 {
    top: 18%;
    left: 7%;
    font-size: clamp(2rem, 5vw, 4.5rem);
    animation: floatText 8s ease-in-out infinite;
}

.bg-type-2 {
    bottom: 16%;
    right: 8%;
    font-size: clamp(2rem, 5vw, 4.5rem);
    animation: floatText 9s ease-in-out infinite reverse;
}

.bg-type-3 {
    top: 62%;
    left: 8%;
    font-size: clamp(1.6rem, 4vw, 3.5rem);
    animation: floatText 10s ease-in-out infinite;
}

/* 애니메이션 */
@keyframes blink {
    0%, 45% {
        opacity: 1;
    }
    46%, 100% {
        opacity: 0;
    }
}

@keyframes floatText {
    0%, 100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-18px);
    }
}

/* 모바일 */
@media (max-width: 768px) {
    .home-typing-hero {
        min-height: calc(100vh - 70px);
        padding: 64px 18px;
    }

    .home-main-title {
        font-size: 3.0rem;
        margin-bottom: 28px;
    }

    .home-description {
        font-size: 1.05rem;
    }

    .home-topic-box {
        flex-direction: column;
        gap: 6px;
        padding: 14px 0;
    }

    .home-btn {
        width: 100%;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var titleTarget = document.getElementById('typingTitle');
    var topicTarget = document.getElementById('typingTopic');

    var titleText = '한글은 늘 도망가';

    var topics = [
        '아직 말하지 못한 것',
        '사라지기 전에 붙잡고 싶은 것',
        '나만 알고 있던 마음',
        '다시 돌아가고 싶은 문장'
    ];

    var titleIndex = 0;
    var topicIndex = 0;
    var topicCharIndex = 0;
    var isDeleting = false;

    function typeTitle() {
        var chars = Array.from(titleText);

        titleTarget.textContent = chars.slice(0, titleIndex).join('');

        if (titleIndex < chars.length) {
            titleIndex++;
            setTimeout(typeTitle, 130);
        } else {
            setTimeout(typeTopic, 500);
        }
    }

    function typeTopic() {
        var currentTopic = topics[topicIndex];
        var chars = Array.from(currentTopic);

        if (!isDeleting) {
            topicTarget.textContent = chars.slice(0, topicCharIndex).join('');
            topicCharIndex++;

            if (topicCharIndex <= chars.length) {
                setTimeout(typeTopic, 80);
            } else {
                isDeleting = true;
                setTimeout(typeTopic, 1800);
            }
        } else {
            topicTarget.textContent = chars.slice(0, topicCharIndex).join('');
            topicCharIndex--;

            if (topicCharIndex >= 0) {
                setTimeout(typeTopic, 45);
            } else {
                isDeleting = false;
                topicIndex = (topicIndex + 1) % topics.length;
                topicCharIndex = 0;
                setTimeout(typeTopic, 300);
            }
        }
    }

    typeTitle();
});
</script>

<?php include './includes/footer.php'; ?>
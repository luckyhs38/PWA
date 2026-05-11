<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include './includes/header.php';
?>


<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Noto+Serif+KR:wght@300;400;600;700&family=Noto+Sans+KR:wght@300;400;500&display=swap" rel="stylesheet">
<style>
  /* ── 리셋 & 기본 ── */
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  html { font-size: 16px; -webkit-font-smoothing: antialiased; }

  /* ── 디자인 토큰 ── */
  :root {
    --ink:       #1a1208;
    --ink-soft:  #5a5040;
    --ink-muted: #9c8f7d;
    --cream:     #faf7f2;
    --warm:      #f3ede2;
    --border:    #e2d9cb;
    --gold:      #b8922a;
    --gold-bg:   #fdf3dc;
    --serif:     'Noto Serif KR', Georgia, serif;
    --sans:      'Noto Sans KR', sans-serif;
  }

  body {
    background: var(--cream);
    color: var(--ink);
    font-family: var(--sans);
    min-height: 100vh;
  }

  /* ── 네비게이션 ── */
  .nav {
    position: sticky; top: 0; z-index: 50;
    background: rgba(250,247,242,0.92);
    backdrop-filter: blur(8px);
    border-bottom: 1px solid var(--border);
    display: flex; align-items: center; justify-content: space-between;
    padding: 0 2rem; height: 54px;
  }
  .nav-logo {
    font-family: var(--serif);
    font-size: 1rem; font-weight: 600;
    color: var(--ink); text-decoration: none;
    letter-spacing: -0.02em;
  }
  .nav-links { display: flex; gap: 0; }
  .nav-a {
    font-size: 0.8rem; font-weight: 400;
    color: var(--ink-muted); text-decoration: none;
    padding: 0 1rem; height: 54px;
    display: flex; align-items: center;
    border-bottom: 2px solid transparent;
    transition: color 0.2s, border-color 0.2s;
    letter-spacing: 0.01em;
  }
  .nav-a:hover { color: var(--ink); }
  .nav-a.active { color: var(--ink); border-bottom-color: var(--ink); font-weight: 500; }

  /* ── 레이아웃 ── */
  .page { max-width: 720px; margin: 0 auto; padding: 3rem 2rem 6rem; }

  /* ── 페이지 헤더 ── */
  .page-header { margin-bottom: 2.5rem; }
  .page-eyebrow {
    font-size: 0.72rem; letter-spacing: 0.12em;
    color: var(--ink-muted); font-weight: 400;
    text-transform: uppercase; margin-bottom: 0.5rem;
  }
  .page-title {
    font-family: var(--serif);
    font-size: 2rem; font-weight: 700;
    letter-spacing: -0.04em; line-height: 1.2;
    color: var(--ink);
  }
  .page-desc {
    margin-top: 0.5rem;
    font-size: 0.83rem; color: var(--ink-muted); font-weight: 300;
    line-height: 1.6;
  }

  /* ── 탭 ── */
  .tab-bar {
    display: flex;
    border-bottom: 1px solid var(--border);
    margin-bottom: 1.75rem;
  }
  .tab-btn {
    background: none; border: none; cursor: pointer;
    font-family: var(--sans); font-size: 0.85rem; font-weight: 400;
    color: var(--ink-muted);
    padding: 0.65rem 1.1rem;
    border-bottom: 2px solid transparent; margin-bottom: -1px;
    transition: all 0.2s; letter-spacing: 0.01em;
  }
  .tab-btn:hover { color: var(--ink); }
  .tab-btn.active { color: var(--ink); border-bottom-color: var(--ink); font-weight: 500; }

  /* ── 탭 패널 ── */
  .tab-panel { display: none; }
  .tab-panel.active { display: block; }

  /* ── 월 정보 바 ── */
  .month-bar {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 1.5rem;
    flex-wrap: wrap; gap: 0.5rem;
  }
  .month-info { display: flex; align-items: center; gap: 0.6rem; }
  .month-chip {
    background: var(--warm); border: 1px solid var(--border);
    border-radius: 20px; padding: 0.28rem 0.85rem;
    font-size: 0.76rem; color: var(--ink-soft); font-weight: 400;
  }
  .month-sub { font-size: 0.78rem; color: var(--ink-muted); font-weight: 300; }
  .total-count { font-size: 0.78rem; color: var(--ink-muted); font-weight: 300; }

  /* ── 순위 리스트 ── */
  .rank-list { display: flex; flex-direction: column; }

  /* TOP 3 카드 */
  .rank-top {
    background: white;
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 1.3rem 1.4rem 1.1rem;
    margin-bottom: 0.6rem;
    display: flex; align-items: flex-start; gap: 1.1rem;
    transition: box-shadow 0.2s;
    animation: fadeUp 0.35s ease both;
  }
  .rank-top:hover { box-shadow: 0 3px 16px rgba(26,18,8,0.07); }
  .rank-top.first { border-color: #d4b96a55; background: #fffef9; }

  /* 4위 이하 */
  .rank-row {
    display: flex; align-items: flex-start; gap: 1.1rem;
    padding: 1.15rem 0;
    border-bottom: 1px solid var(--border);
    transition: background 0.15s;
    animation: fadeUp 0.35s ease both;
  }
  .rank-row:last-child { border-bottom: none; }
  .rank-row:hover { background: var(--warm); margin: 0 -0.75rem; padding-left: 0.75rem; padding-right: 0.75rem; border-radius: 6px; }

  /* 구분선 헤더 */
  .rank-divider {
    font-size: 0.7rem; letter-spacing: 0.1em; text-transform: uppercase;
    color: var(--ink-muted); font-weight: 400;
    padding: 1.1rem 0 0.7rem;
    border-top: 1px solid var(--border);
    margin-top: 0.2rem;
  }

  /* 순위 번호 */
  .rank-num-col { width: 34px; flex-shrink: 0; padding-top: 2px; }
  .rank-num {
    font-family: var(--sans);
    font-size: 0.78rem; font-weight: 500; color: var(--ink-muted);
    display: block; text-align: center;
  }
  .rank-num.n1 { color: var(--gold); font-size: 1rem; }
  .rank-num.n2 { color: #7a8a99; font-size: 1rem; }
  .rank-num.n3 { color: #9c6b3c; font-size: 1rem; }
  .rank-dot {
    width: 4px; height: 4px; border-radius: 50%;
    margin: 5px auto 0;
  }
  .rank-dot.d1 { background: var(--gold); }
  .rank-dot.d2 { background: #7a8a99; }
  .rank-dot.d3 { background: #9c6b3c; }

  /* 문장 본문 */
  .rank-body { flex: 1; min-width: 0; }
  .rank-sentence {
    font-family: var(--serif);
    font-size: 1rem; line-height: 1.85;
    color: var(--ink); font-weight: 400;
    margin-bottom: 0.55rem;
    word-break: keep-all;
  }
  .rank-top .rank-sentence { font-size: 1.02rem; }
  .rank-from {
    font-size: 0.76rem; color: var(--ink-muted); font-weight: 300;
  }
  .rank-from a {
    color: var(--ink-muted); text-decoration: underline;
    text-decoration-color: var(--border);
    text-underline-offset: 3px;
    transition: color 0.15s;
  }
  .rank-from a:hover { color: var(--ink); }

  /* 수집 수 / 바 */
  .rank-stat {
    flex-shrink: 0; display: flex; flex-direction: column;
    align-items: flex-end; gap: 5px; padding-top: 2px;
  }
  .stat-num {
    font-size: 0.82rem; font-weight: 500; color: var(--ink);
    white-space: nowrap;
  }
  .stat-bar {
    width: 60px; height: 2px;
    background: var(--border); border-radius: 2px; overflow: hidden;
  }
  .stat-fill {
    height: 100%; border-radius: 2px;
    background: var(--ink-muted);
    transition: width 0.6s cubic-bezier(.4,0,.2,1);
  }
  .stat-fill.gold { background: var(--gold); }
  .stat-label { font-size: 0.7rem; color: var(--ink-muted); font-weight: 300; }

  /* ── 빈 상태 ── */
  .empty {
    text-align: center; padding: 5rem 0;
    font-size: 0.88rem; color: var(--ink-muted);
    font-weight: 300; line-height: 2;
  }

  /* ── 애니메이션 ── */
  @keyframes fadeUp {
    from { opacity: 0; transform: translateY(10px); }
    to   { opacity: 1; transform: translateY(0); }
  }
  .rank-top:nth-child(1) { animation-delay: 0.05s; }
  .rank-top:nth-child(2) { animation-delay: 0.1s; }
  .rank-top:nth-child(3) { animation-delay: 0.15s; }
  .rank-row:nth-child(1) { animation-delay: 0.18s; }
  .rank-row:nth-child(2) { animation-delay: 0.22s; }
  .rank-row:nth-child(3) { animation-delay: 0.26s; }
  .rank-row:nth-child(4) { animation-delay: 0.3s; }

  /* ── 반응형 ── */
  @media (max-width: 600px) {
    .nav { padding: 0 1rem; }
    .page { padding: 2rem 1.1rem 5rem; }
    .page-title { font-size: 1.6rem; }
    .stat-bar { width: 44px; }
    .nav-links .nav-a:not(.active) { display: none; }
  }
</style>
</head>
<body>

<!-- 네비게이션 -->
<nav class="nav">
  <a href="index.html" class="nav-logo">한글은 늘 도망가</a>
  <div class="nav-links">
    <a href="index.html" class="nav-a">글 읽기</a>
    <a href="ranking.html" class="nav-a active">문장 순위</a>
    <a href="collection.html" class="nav-a">내 컬렉션</a>
  </div>
</nav>

<!-- 메인 -->
<main class="page">

  <header class="page-header">
    <div class="page-eyebrow">독자들이 수집한</div>
    <h1 class="page-title">문장 순위</h1>
    <p class="page-desc">마음에 닿은 문장을 가장 많이 담아간 순서예요.</p>
  </header>

  <!-- 탭 -->
  <div class="tab-bar">
    <button class="tab-btn active" onclick="switchTab('month', this)">이달의 문장</button>
    <button class="tab-btn" onclick="switchTab('all', this)">역대 명문장</button>
  </div>

  <!-- ── 이달의 문장 ── -->
  <div id="panel-month" class="tab-panel active">

    <div class="month-bar">
      <div class="month-info">
        <span class="month-chip">2026년 5월</span>
        <span class="month-sub">마감까지 22일 남았어요</span>
      </div>
      <span class="total-count">총 874명이 수집했어요</span>
    </div>

    <div class="rank-list" id="list-month"></div>
  </div>

  <!-- ── 역대 명문장 ── -->
  <div id="panel-all" class="tab-panel">

    <div class="month-bar">
      <div class="month-info">
        <span class="month-chip">전체 기간</span>
        <span class="month-sub">누적 수집 기준이에요</span>
      </div>
      <span class="total-count">총 6,152명이 수집했어요</span>
    </div>

    <div class="rank-list" id="list-all"></div>
  </div>

</main>

<script>
/* ── 임시 데이터 (DB 연결 전) ── */
const DATA = {
  month: [
    { rank:1, sentence:"우리는 서로를 오해하는 방식으로 사랑했다.",                                                                    from:"오해의 문법",          count:203, max:203 },
    { rank:2, sentence:"봄은 늘 예고 없이 도망간다. 우리가 잡으려 손을 내밀 때쯤, 이미 여름의 냄새가 창문 틈으로 비집고 들어온다.", from:"봄의 언어로 쓴 이별",   count:142, max:203 },
    { rank:3, sentence:"기억은 사진이 아니라 날씨를 닮았다. 정확하지 않고, 그래서 더 오래 남는다.",                                  from:"기억의 형태",           count:119, max:203 },
    { rank:4, sentence:"말은 늘 조금 늦게 도착한다. 마음이 이미 떠난 자리에, 말만 덩그러니 남아 창문을 두드린다.",                  from:"봄의 언어로 쓴 이별",   count:87,  max:203 },
    { rank:5, sentence:"어떤 이름들은 부르는 순간 공기의 온도가 달라진다.",                                                            from:"이름의 무게",           count:78,  max:203 },
    { rank:6, sentence:"그 골목을 지날 때마다 나는 잠깐, 아주 잠깐 다른 사람이 된다.",                                                from:"골목의 기억",           count:56,  max:203 },
    { rank:7, sentence:"오래된 것들만이 가진 침묵의 무게가 있다. 금방 사라질 것들은 그렇게 무겁지 않다.",                            from:"낡은 것들에 대하여",    count:34,  max:203 },
    { rank:8, sentence:"우리가 나눈 말 중에 가장 진심이었던 건 아마 침묵이었을 거야.",                                                from:"침묵의 대화",           count:28,  max:203 },
    { rank:9, sentence:"사랑은 늘 조금 늦게 이름을 갖는다.",                                                                          from:"이름의 무게",           count:21,  max:203 },
    { rank:10, sentence:"버티는 것도 용기지만, 멈추는 것도 용기다.",                                                                   from:"멈춤에 대하여",         count:16,  max:203 },
  ],
  all: [
    { rank:1, sentence:"우리는 서로를 오해하는 방식으로 사랑했다.",                                                                    from:"오해의 문법",          count:1203, max:1203 },
    { rank:2, sentence:"봄은 늘 예고 없이 도망간다. 우리가 잡으려 손을 내밀 때쯤, 이미 여름의 냄새가 창문 틈으로 비집고 들어온다.", from:"봄의 언어로 쓴 이별",  count:942,  max:1203 },
    { rank:3, sentence:"기억은 사진이 아니라 날씨를 닮았다. 정확하지 않고, 그래서 더 오래 남는다.",                                  from:"기억의 형태",          count:819,  max:1203 },
    { rank:4, sentence:"외로움은 혼자 있을 때가 아니라 함께 있어도 혼자인 순간에 온다.",                                              from:"밤의 문장들",          count:744,  max:1203 },
    { rank:5, sentence:"끝이 있어야 좋은 것들이 있다. 계절이 그렇고, 이야기가 그렇고, 어떤 사람들이 그렇다.",                        from:"유한의 미학",          count:621,  max:1203 },
    { rank:6, sentence:"어떤 이름들은 부르는 순간 공기의 온도가 달라진다.",                                                            from:"이름의 무게",          count:580,  max:1203 },
    { rank:7, sentence:"말은 늘 조금 늦게 도착한다. 마음이 이미 떠난 자리에, 말만 덩그러니 남아 창문을 두드린다.",                  from:"봄의 언어로 쓴 이별",  count:431,  max:1203 },
    { rank:8, sentence:"사랑은 늘 조금 늦게 이름을 갖는다.",                                                                          from:"이름의 무게",          count:312,  max:1203 },
    { rank:9, sentence:"버티는 것도 용기지만, 멈추는 것도 용기다.",                                                                   from:"멈춤에 대하여",        count:298,  max:1203 },
    { rank:10, sentence:"그 골목을 지날 때마다 나는 잠깐, 아주 잠깐 다른 사람이 된다.",                                               from:"골목의 기억",          count:202,  max:1203 },
  ]
};

/* ── 렌더링 ── */
function renderList(key) {
  const el = document.getElementById('list-' + key);
  const items = DATA[key];
  const top3 = items.slice(0, 3);
  const rest  = items.slice(3);

  let html = '';

  /* 1~3위 카드 */
  top3.forEach(d => {
    const numClass = ['','n1','n2','n3'][d.rank];
    const dotClass = ['','d1','d2','d3'][d.rank];
    const isFirst  = d.rank === 1;
    const pct      = Math.round((d.count / d.max) * 100);
    html += `
    <div class="rank-top ${isFirst ? 'first' : ''}">
      <div class="rank-num-col">
        <span class="rank-num ${numClass}">${d.rank}</span>
        <div class="rank-dot ${dotClass}"></div>
      </div>
      <div class="rank-body">
        <p class="rank-sentence">${d.sentence}</p>
        <p class="rank-from">수록 · <a href="#">${d.from}</a></p>
      </div>
      <div class="rank-stat">
        <span class="stat-num">${d.count.toLocaleString()}명</span>
        <div class="stat-bar"><div class="stat-fill ${isFirst ? 'gold' : ''}" style="width:${pct}%"></div></div>
        <span class="stat-label">${key === 'all' ? '누적 ' : ''}수집</span>
      </div>
    </div>`;
  });

  /* 구분선 */
  if (rest.length) {
    html += `<div class="rank-divider">4위 이하</div>`;
  }

  /* 4위~ */
  rest.forEach(d => {
    const pct = Math.round((d.count / d.max) * 100);
    html += `
    <div class="rank-row">
      <div class="rank-num-col">
        <span class="rank-num">${d.rank}</span>
      </div>
      <div class="rank-body">
        <p class="rank-sentence">${d.sentence}</p>
        <p class="rank-from">수록 · <a href="#">${d.from}</a></p>
      </div>
      <div class="rank-stat">
        <span class="stat-num">${d.count.toLocaleString()}명</span>
        <div class="stat-bar"><div class="stat-fill" style="width:${pct}%"></div></div>
      </div>
    </div>`;
  });

  el.innerHTML = html;
}

/* ── 탭 전환 ── */
function switchTab(key, btn) {
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById('panel-' + key).classList.add('active');
}

/* ── 초기 렌더 ── */
renderList('month');
renderList('all');
</script>

<?php include './includes/footer.php'; ?>
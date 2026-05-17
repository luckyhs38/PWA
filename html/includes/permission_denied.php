
<?php
// /includes/permission_denied.php

$title = $title ?? '접근 제한';

$message = $message ?? '접근 권한이 없습니다.';

$link = $link ?? '/';

$link_text = $link_text ?? '메인으로';

include __DIR__ . '/header.php';
?>

<div style="
    max-width:640px;
    margin:120px auto;
    padding:48px;
    background:#fff;
    border-radius:24px;
    text-align:center;
    box-shadow:0 4px 20px rgba(0,0,0,0.04);
">

    <div style="
        font-size:42px;
        margin-bottom:18px;
    ">
        🔒
    </div>

    <h2 style="
        font-size:30px;
        margin-bottom:18px;
        color:#111;
    ">
        <?= htmlspecialchars($title) ?>
    </h2>

    <p style="
        color:#777;
        line-height:1.9;
        margin-bottom:36px;
        font-size:15px;
    ">
        <?= nl2br(htmlspecialchars($message)) ?>
    </p>

    <div style="
        display:flex;
        justify-content:center;
        gap:12px;
        flex-wrap:wrap;
    ">

        <!-- 메인 버튼 -->
        <a
            href="<?= htmlspecialchars($link) ?>"
            style="
                display:inline-flex;
                align-items:center;
                justify-content:center;
                padding:14px 24px;
                background:#111;
                color:#fff;
                border-radius:12px;
                text-decoration:none;
                font-size:14px;
                font-weight:600;
            "
        >
            <?= htmlspecialchars($link_text) ?>
        </a>


        <!-- 뒤로가기 버튼 -->
        <button
            type="button"
            onclick="history.back()"
            style="
                display:inline-flex;
                align-items:center;
                justify-content:center;
                padding:14px 24px;
                background:#f3f3f3;
                color:#555;
                border:none;
                border-radius:12px;
                cursor:pointer;
                font-size:14px;
                font-weight:600;
            "
        >
            뒤로 가기
        </button>

    </div>

</div>

<?php include __DIR__ . '/footer.php'; ?>
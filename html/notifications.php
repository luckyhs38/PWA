<?php
// /notifications.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once './includes/db.php';
require_once './includes/auth_check.php';

if (!isset($_SESSION['user_id'])) {
    echo "<script>alert('로그인 후 이용 가능합니다.'); location.href='login.php';</script>";
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// 전체 읽음 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'read_all') {
    $stmt = $pdo->prepare("
        UPDATE notifications
        SET is_read = 1
        WHERE user_id = :user_id
          AND is_read = 0
    ");

    $stmt->execute([
        ':user_id' => $user_id
    ]);

    echo "<script>location.href='notifications.php';</script>";
    exit;
}

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$page_size = 15;
$offset = ($page - 1) * $page_size;

try {
    // 전체 알림 수
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM notifications
        WHERE user_id = :user_id
    ");

    $stmt->execute([
        ':user_id' => $user_id
    ]);

    $total_count = (int)$stmt->fetchColumn();
    $total_page = max(1, (int)ceil($total_count / $page_size));

    // 읽지 않은 알림 수
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM notifications
        WHERE user_id = :user_id
          AND is_read = 0
    ");

    $stmt->execute([
        ':user_id' => $user_id
    ]);

    $unread_count = (int)$stmt->fetchColumn();

    // 알림 목록
    $stmt = $pdo->prepare("
        SELECT
            id,
            type,
            target_id,
            message,
            url,
            is_read,
            created_at
        FROM notifications
        WHERE user_id = :user_id
        ORDER BY created_at DESC
        LIMIT :limit OFFSET :offset
    ");

    $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $page_size, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("알림 조회 오류: " . $e->getMessage());
}

function notification_type_label($type) {
    switch ($type) {
        case 'comment':
            return '댓글';
        case 'reply':
            return '답글';
        case 'qna_answer':
            return '문의 답변';
        default:
            return '알림';
    }
}

include './includes/header.php';
?>

<style>
.noti-page-wrap {
    max-width: 860px;
    margin: 110px auto 80px;
    padding: 0 24px;
}

.noti-page-head {
    border-bottom: 2px solid #1a1a1a;
    padding-bottom: 18px;
    margin-bottom: 28px;
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    gap: 20px;
}

.noti-page-title {
    font-family: 'Noto Serif KR', serif;
    font-size: 26px;
    font-weight: 500;
    color: #1a1a1a;
}

.noti-page-sub {
    font-size: 13px;
    color: #aaa;
    margin-top: 4px;
}

.noti-count {
    font-size: 13px;
    color: #999;
}

.noti-count strong {
    color: #1a1a1a;
    font-weight: 500;
}

.noti-list {
    border-top: 1px solid #eee;
}

.noti-item {
    display: grid;
    grid-template-columns: 90px 1fr 110px;
    gap: 16px;
    align-items: center;
    padding: 18px 0;
    border-bottom: 1px solid #f0f0f0;
    text-decoration: none;
    color: inherit;
}

.noti-item:hover {
    background: #fafafa;
}

.noti-item.unread .noti-message {
    font-weight: 500;
    color: #1a1a1a;
}

.noti-type {
    display: inline-flex;
    justify-content: center;
    align-items: center;
    border-radius: 999px;
    padding: 6px 0;
    width: 74px;
    font-size: 12px;
    background: #fafafa;
    border: 1px solid #ddd;
    color: #777;
}

.noti-item.unread .noti-type {
    background: #1a1a1a;
    border-color: #1a1a1a;
    color: #fff;
}

.noti-message {
    font-size: 14px;
    color: #555;
    line-height: 1.6;
}

.noti-date {
    font-size: 12px;
    color: #aaa;
    text-align: right;
    white-space: nowrap;
}

.noti-empty {
    padding: 70px 0;
    text-align: center;
    color: #ccc;
    border-bottom: 1px solid #eee;
}

.noti-empty i {
    display: block;
    font-size: 34px;
    margin-bottom: 12px;
}

.noti-bottom {
    display: flex;
    justify-content: center;
    margin-top: 26px;
}

.noti-pagination {
    display: flex;
    gap: 6px;
}

.noti-pagination a {
    min-width: 32px;
    height: 32px;
    border: 1px solid #ddd;
    color: #777;
    text-decoration: none;
    font-size: 13px;
    display: inline-flex;
    justify-content: center;
    align-items: center;
}

.noti-pagination a.active {
    background: #1a1a1a;
    border-color: #1a1a1a;
    color: #fff;
}

@media (max-width: 600px) {
    .noti-page-wrap {
        margin-top: 80px;
        padding: 0 15px;
    }

    .noti-page-head {
        flex-direction: column;
        align-items: flex-start;
    }

    .noti-page-title {
        font-size: 22px;
    }

    .noti-item {
        grid-template-columns: 1fr;
        gap: 8px;
        padding: 16px 0;
    }

    .noti-date {
        text-align: left;
    }
}
</style>

<div class="noti-page-wrap">

    <div class="noti-page-head">
        <div>
            <div class="noti-page-title">알림</div>
            <div class="noti-page-sub">
                댓글, 답글, 문의 답변 알림을 확인할 수 있습니다.
            </div>
        </div>

        <div>
            <div class="noti-count mb-2">
                읽지 않은 알림 <strong><?= number_format($unread_count) ?></strong>개
            </div>

            <?php if ($unread_count > 0): ?>
                <form method="post" action="notifications.php" class="text-end">
                    <input type="hidden" name="action" value="read_all">
                    <button type="submit" class="btn btn-outline-dark btn-sm">
                        모두 읽음
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="noti-list">
        <?php if (empty($notifications)): ?>
            <div class="noti-empty">
                <i class="bi bi-bell"></i>
                알림이 없습니다.
            </div>
        <?php else: ?>
            <?php foreach ($notifications as $noti): ?>
                <a 
                    href="notification_read.php?id=<?= (int)$noti['id'] ?>"
                    class="noti-item <?= ((int)$noti['is_read'] === 0) ? 'unread' : '' ?>"
                >
                    <div>
                        <span class="noti-type">
                            <?= htmlspecialchars(notification_type_label($noti['type'])) ?>
                        </span>
                    </div>

                    <div class="noti-message">
                        <?= htmlspecialchars($noti['message']) ?>
                    </div>

                    <div class="noti-date">
                        <?= htmlspecialchars($noti['created_at']) ?>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php if ($total_page > 1): ?>
        <div class="noti-bottom">
            <div class="noti-pagination">
                <?php for ($i = 1; $i <= $total_page; $i++): ?>
                    <a 
                        href="notifications.php?page=<?= $i ?>"
                        class="<?= $page === $i ? 'active' : '' ?>"
                    >
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
            </div>
        </div>
    <?php endif; ?>

</div>

<?php include './includes/footer.php'; ?>
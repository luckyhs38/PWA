<?php
// /admin/topics.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_admin($pdo);

$admin_menu = 'topics';
$msg        = $_GET['msg'] ?? '';

// POST 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        // ── 주제 추가/수정 ──────────────────────────────────────
        if ($action === 'save') {
            $id          = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            $year        = (int)($_POST['theme_year']  ?? date('Y'));
            $month       = (int)($_POST['theme_month'] ?? date('n'));
            $title       = trim($_POST['title']       ?? '');
            $description = trim($_POST['description'] ?? '');
            $is_current  = isset($_POST['is_current']) ? 1 : 0;

            if ($title === '' || $year < 2020 || $month < 1 || $month > 12) {
                header('Location: topics.php?msg=invalid');
                exit;
            }

            // is_current = 1 설정 시 기존 current 초기화
            if ($is_current) {
                $pdo->query("UPDATE monthly_topics SET is_current = 0");
            }

            if ($id > 0) {
                // 수정
                $pdo->prepare("
                    UPDATE monthly_topics
                    SET theme_year   = :year,
                        theme_month  = :month,
                        title        = :title,
                        description  = :desc,
                        is_current   = :current
                    WHERE id = :id
                ")->execute([
                    ':year'    => $year,
                    ':month'   => $month,
                    ':title'   => $title,
                    ':desc'    => $description ?: null,
                    ':current' => $is_current,
                    ':id'      => $id,
                ]);
            } else {
                // 추가
                $pdo->prepare("
                    INSERT INTO monthly_topics
                        (theme_year, theme_month, title, description, is_current)
                    VALUES
                        (:year, :month, :title, :desc, :current)
                    ON DUPLICATE KEY UPDATE
                        title       = VALUES(title),
                        description = VALUES(description),
                        is_current  = VALUES(is_current)
                ")->execute([
                    ':year'    => $year,
                    ':month'   => $month,
                    ':title'   => $title,
                    ':desc'    => $description ?: null,
                    ':current' => $is_current,
                ]);
            }

            header('Location: topics.php?msg=saved');
            exit;

        // ── 현재 주제 설정 ──────────────────────────────────────
        } elseif ($action === 'set_current') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $pdo->query("UPDATE monthly_topics SET is_current = 0");
                $pdo->prepare("UPDATE monthly_topics SET is_current = 1 WHERE id = :id")
                    ->execute([':id' => $id]);
            }
            header('Location: topics.php?msg=set_current');
            exit;

        // ── 삭제 ────────────────────────────────────────────────
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $pdo->prepare("DELETE FROM monthly_topics WHERE id = :id")
                    ->execute([':id' => $id]);
            }
            header('Location: topics.php?msg=deleted');
            exit;
        }

    } catch (PDOException $e) {
        error_log($e->getMessage());
        header('Location: topics.php?msg=error');
        exit;
    }
}

// 주제 목록
try {
    $topics = $pdo->query("
        SELECT * FROM monthly_topics
        ORDER BY theme_year DESC, theme_month DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // 수정 대상 (edit_id 파라미터)
    $edit_id    = isset($_GET['edit_id']) ? (int)$_GET['edit_id'] : 0;
    $edit_topic = null;
    if ($edit_id > 0) {
        foreach ($topics as $t) {
            if ((int)$t['id'] === $edit_id) { $edit_topic = $t; break; }
        }
    }

} catch (PDOException $e) {
    die("오류: " . $e->getMessage());
}

$month_names = [
    1=>'1월', 2=>'2월', 3=>'3월', 4=>'4월',
    5=>'5월', 6=>'6월', 7=>'7월', 8=>'8월',
    9=>'9월', 10=>'10월', 11=>'11월', 12=>'12월',
];

// _layout.php 메뉴에 topics 추가 필요 (아래 주석 참고)
include '_layout.php';
?>

<?php if ($msg === 'saved'): ?>
    <div class="adm-alert success"><i class="bi bi-check-circle"></i> 주제가 저장되었습니다.</div>
<?php elseif ($msg === 'deleted'): ?>
    <div class="adm-alert warning"><i class="bi bi-trash"></i> 주제가 삭제되었습니다.</div>
<?php elseif ($msg === 'set_current'): ?>
    <div class="adm-alert success"><i class="bi bi-check-circle"></i> 현재 주제로 설정되었습니다.</div>
<?php elseif ($msg === 'invalid'): ?>
    <div class="adm-alert error"><i class="bi bi-exclamation-circle"></i> 입력값을 확인해주세요.</div>
<?php elseif ($msg === 'error'): ?>
    <div class="adm-alert error"><i class="bi bi-exclamation-circle"></i> 처리 중 오류가 발생했습니다.</div>
<?php endif; ?>

<div style="display:grid; grid-template-columns:340px 1fr; gap:24px; align-items:start;">

    <!-- 주제 추가/수정 폼 -->
    <div class="adm-card" style="margin-bottom:0; position:sticky; top:20px;">
        <div class="adm-card-hd">
            <span class="adm-card-title">
                <?= $edit_topic ? '주제 수정' : '새 주제 추가' ?>
            </span>
            <?php if ($edit_topic): ?>
                <a href="topics.php" class="adm-btn" style="font-size:11px; padding:4px 10px;">
                    <i class="bi bi-x"></i> 취소
                </a>
            <?php endif; ?>
        </div>

        <form method="post" action="topics.php">
            <input type="hidden" name="action" value="save">
            <?php if ($edit_topic): ?>
                <input type="hidden" name="id" value="<?= $edit_topic['id'] ?>">
            <?php endif; ?>

            <!-- 연도 + 월 -->
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:14px;">
                <div>
                    <label style="font-size:12px; color:#888; display:block; margin-bottom:6px;">연도</label>
                    <select name="theme_year" class="adm-select" style="width:100%;">
                        <?php for ($y = (int)date('Y') + 1; $y >= 2024; $y--): ?>
                            <option value="<?= $y ?>"
                                <?= ($edit_topic ? (int)$edit_topic['theme_year'] : (int)date('Y')) === $y ? 'selected' : '' ?>>
                                <?= $y ?>년
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div>
                    <label style="font-size:12px; color:#888; display:block; margin-bottom:6px;">월</label>
                    <select name="theme_month" class="adm-select" style="width:100%;">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>"
                                <?= ($edit_topic ? (int)$edit_topic['theme_month'] : (int)date('n')) === $m ? 'selected' : '' ?>>
                                <?= $m ?>월
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>

            <!-- 주제 제목 -->
            <div style="margin-bottom:14px;">
                <label style="font-size:12px; color:#888; display:block; margin-bottom:6px;">
                    주제 제목 <span style="color:#dc3545;">*</span>
                </label>
                <input type="text"
                       name="title"
                       class="adm-search"
                       style="width:100%; border:1px solid #ddd; border-radius:8px;
                              padding:9px 12px; font-size:13px; outline:none;
                              font-family:inherit; box-sizing:border-box;"
                       placeholder="예: 디저트"
                       maxlength="100"
                       value="<?= htmlspecialchars($edit_topic['title'] ?? '') ?>"
                       required>
            </div>

            <!-- 주제 설명 -->
            <div style="margin-bottom:14px;">
                <label style="font-size:12px; color:#888; display:block; margin-bottom:6px;">
                    주제 설명 <span style="color:#bbb; font-weight:400;">(선택)</span>
                </label>
                <textarea name="description"
                          style="width:100%; border:1px solid #ddd; border-radius:8px;
                                 padding:9px 12px; font-size:13px; outline:none;
                                 font-family:inherit; resize:vertical; min-height:80px;
                                 box-sizing:border-box; line-height:1.6;"
                          placeholder="이번 달 주제에 대해 당신의 솔직한 글을 남겨주세요."
                          maxlength="255"><?= htmlspecialchars($edit_topic['description'] ?? '') ?></textarea>
            </div>

            <!-- 현재 주제 설정 -->
            <div style="margin-bottom:20px;">
                <label style="display:flex; align-items:center; gap:8px; cursor:pointer;
                              font-size:13px; color:#555; padding:10px 14px;
                              border:1px solid #eee; border-radius:8px;">
                    <input type="checkbox"
                           name="is_current"
                           value="1"
                           <?= ($edit_topic && $edit_topic['is_current']) ? 'checked' : '' ?>>
                    인트로 페이지에 현재 주제로 표시
                </label>
                <p style="font-size:11px; color:#bbb; margin-top:6px; padding-left:2px;">
                    체크하면 기존 현재 주제 설정이 해제됩니다.
                </p>
            </div>

            <button type="submit" class="adm-btn dark" style="width:100%; justify-content:center; padding:10px;">
                <i class="bi bi-<?= $edit_topic ? 'check-lg' : 'plus-lg' ?>"></i>
                <?= $edit_topic ? '수정 완료' : '주제 추가' ?>
            </button>
        </form>
    </div>

    <!-- 주제 목록 -->
    <div>
        <div class="adm-card" style="margin-bottom:0;">
            <div class="adm-card-hd">
                <span class="adm-card-title">
                    주제 목록
                    <span style="font-size:12px; color:#bbb; font-weight:400; margin-left:6px;">
                        총 <?= count($topics) ?>개
                    </span>
                </span>
            </div>

            <?php if (empty($topics)): ?>
                <div class="adm-empty">
                    <i class="bi bi-calendar-x"></i>
                    등록된 주제가 없습니다.
                </div>
            <?php else: ?>
                <table class="adm-table">
                    <thead>
                        <tr>
                            <th style="width:100px;">연월</th>
                            <th>주제</th>
                            <th style="width:90px; text-align:center;">상태</th>
                            <th style="width:160px; text-align:center;">관리</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topics as $t): ?>
                            <tr <?= $edit_id === (int)$t['id'] ? 'style="background:#fafafa;"' : '' ?>>
                                <td style="font-size:13px; color:#555; white-space:nowrap;">
                                    <?= $t['theme_year'] ?>.<?= str_pad($t['theme_month'], 2, '0', STR_PAD_LEFT) ?>
                                </td>
                                <td>
                                    <div style="font-size:14px; font-weight:500; color:#1a1a1a; margin-bottom:2px;">
                                        <?= htmlspecialchars($t['title']) ?>
                                    </div>
                                    <?php if (!empty($t['description'])): ?>
                                        <div style="font-size:12px; color:#aaa; white-space:nowrap;
                                                    overflow:hidden; text-overflow:ellipsis; max-width:280px;">
                                            <?= htmlspecialchars($t['description']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;">
                                    <?php if ($t['is_current']): ?>
                                        <span class="adm-badge approved">현재 주제</span>
                                    <?php else: ?>
                                        <!-- 현재 주제로 설정 버튼 -->
                                        <form method="post" action="topics.php" style="display:inline;">
                                            <input type="hidden" name="action" value="set_current">
                                            <input type="hidden" name="id"     value="<?= $t['id'] ?>">
                                            <button type="submit" class="adm-btn"
                                                    style="font-size:11px; padding:4px 10px;"
                                                    onclick="return confirm('현재 주제로 설정하시겠습니까?')">
                                                설정
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;">
                                    <!-- 수정 -->
                                    <a href="topics.php?edit_id=<?= $t['id'] ?>"
                                       class="adm-btn"
                                       style="font-size:11px; padding:4px 10px; margin-right:4px;">
                                        <i class="bi bi-pencil"></i> 수정
                                    </a>
                                    <!-- 삭제 -->
                                    <?php if (!$t['is_current']): ?>
                                        <form method="post" action="topics.php" style="display:inline;">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id"     value="<?= $t['id'] ?>">
                                            <button type="submit"
                                                    class="adm-btn danger"
                                                    style="font-size:11px; padding:4px 10px;"
                                                    onclick="return confirm('정말 삭제하시겠습니까?')">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span style="font-size:11px; color:#ddd; padding:4px 10px;">삭제불가</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '_layout_end.php'; ?>
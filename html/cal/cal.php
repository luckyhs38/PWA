<?php
// cal.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include '../includes/header.php';

$is_login   = isset($_SESSION['user_id']);
$login_uid  = $is_login ? (int)$_SESSION['user_id'] : 0;
$login_name = $is_login ? ($_SESSION['user_name'] ?? '') : '';
?>


<style>
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(10px); }
    to   { opacity: 1; transform: translateY(0); }
}

#calendar {
    min-height: 650px;
}
/* ── 캘린더 공통 래퍼 ── */
.cal-wrapper { 
    width: 100%;
    max-width: 1100px;
    margin: 110px auto 80px;
    padding: 0 24px;
}
.cal-title {
    font-family: 'Noto Serif KR', serif;
    font-size: 26px;
    font-weight: 500;
    color: #1a1a1a;
    letter-spacing: -.4px;
}
.cal-sub {
    font-size: 13px;
    color: #aaa;
    margin-top: 4px;
}

/* FullCalendar 링크 스타일 초기화 */
#calendar a {
    color: inherit;
    text-decoration: none;
}
/* ── 툴바 ── */
.fc .fc-toolbar-title {
    font-size: 1.4rem;
    font-weight: 600;
}

.fc .fc-button {
    background-color: #212529;
    border-color: #212529;
}

.fc .fc-button:hover,
.fc .fc-button:focus {
    background-color: #343a40;
    border-color: #343a40;
    box-shadow: none;
}

.fc .fc-button-primary:not(:disabled).fc-button-active {
    background-color: #343a40;
    border-color: #343a40;
}

.fc-event {
    cursor: pointer;
}

.fc-daygrid-day:hover {
    background-color: #f8f9fa;
    cursor: pointer;
}
/* ── 모바일 ── */
@media (max-width: 575.98px) {
    /* 툴바 타이틀 */
    .fc .fc-toolbar-title {
        font-size: 1rem;
    }

    /* 툴바 버튼 */
    .fc .fc-button {
        font-size: 0.75rem;
        padding: 4px 8px;
    }

    /* 날짜 숫자 */
    .fc .fc-daygrid-day-number {
        font-size: 0.75rem;
        padding: 2px 4px;
    }

    /* 이벤트 텍스트 */
    .fc-event-title,
    .fc-event-time {
        font-size: 0.7rem !important;
    }

    /* 요일 헤더 */
    .fc .fc-col-header-cell-cushion {
        font-size: 0.72rem;
        padding: 4px 2px;
    }

    /* 캘린더 최소 높이 줄이기 */
    #calendar {
        min-height: 360px;
    }

    /* 카드 패딩 */
    .card-body {
        padding: 0.5rem !important;
    }

    /* 토스트 위치 */
    #calToastWrap {
        bottom: 16px !important;
        right: 16px !important;
        left: 16px !important;
    }
}

/* ── 태블릿 ── */
@media (min-width: 576px) and (max-width: 767.98px) {
    .fc .fc-toolbar-title {
        font-size: 1.1rem;
    }

    .fc .fc-button {
        font-size: 0.8rem;
        padding: 5px 10px;
    }
}

</style>


<div class="cal-wrapper">
    <div class="d-flex justify-content-between align-items-center mb-3 mb-md-4">
        <div>
            <h3 class="cal-title">일정</h3>
            <p class="cal-sub">등록된 전체 일정을 확인할 수 있습니다.</p>
        </div>

        <?php if ($is_login): ?>
            <button type="button" class="btn btn-dark btn-sm" data-bs-toggle="modal" data-bs-target="#scheduleModal">
                <span class="d-none d-sm-inline">일정 등록</span>
                <span class="d-inline d-sm-none">+ 등록</span>
            </button>
        <?php else: ?>
            <a href="../login.php" class="btn btn-outline-dark btn-sm">
                <span class="d-none d-sm-inline">로그인 후 일정 등록</span>
                <span class="d-inline d-sm-none">로그인</span>
            </a>
        <?php endif; ?>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-2 p-md-3">
            <div id="calendar"></div>
        </div>
    </div>
</div>



<!-- ==================== 일정 등록 모달 ==================== -->
<?php if ($is_login): ?>
<div class="modal fade" id="scheduleModal" tabindex="-1" aria-labelledby="scheduleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form id="scheduleForm" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="scheduleModalLabel">일정 등록</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="닫기"></button>
            </div>

            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">일정 제목 <span class="text-danger">*</span></label>
                    <input type="text" name="title" id="title" class="form-control" placeholder="제목을 입력하세요" maxlength="100" required>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">내용</label>
                    <textarea name="content" id="content" class="form-control" rows="3" placeholder="내용을 입력하세요 (선택)"></textarea>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">시작일시 <span class="text-danger">*</span></label>
                        <input type="datetime-local" name="start_date" id="start_date" class="form-control" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">종료일시</label>
                        <input type="datetime-local" name="end_date" id="end_date" class="form-control">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">일정 색상</label>
                    <select name="color" id="color" class="form-select">
                        <option value="#212529">⚫ 검정</option>
                        <option value="#0d6efd">🔵 파랑</option>
                        <option value="#198754">🟢 초록</option>
                        <option value="#dc3545">🔴 빨강</option>
                        <option value="#fd7e14">🟠 주황</option>
                        <option value="#6f42c1">🟣 보라</option>
                    </select>
                </div>

                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="allday_yn" name="allday_yn" checked>
                    <label class="form-check-label" for="allday_yn">종일 일정</label>
                </div>
                <div class="form-text mt-1">종일 일정 체크 시 시간은 00:00 기준으로 저장됩니다.</div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">취소</button>
                <button type="submit" class="btn btn-dark" id="submitBtn">등록</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>


<!-- ==================== 일정 상세 모달 ==================== -->
<div class="modal fade" id="detailModal" tabindex="-1" aria-labelledby="detailModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="detailModalLabel">일정 상세</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="닫기"></button>
            </div>
            <div class="modal-body" id="detailBody">
                <!-- JS로 채워짐 -->
            </div>
            <div class="modal-footer" id="detailFooter">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">닫기</button>
            </div>
        </div>
    </div>
</div>


<!-- ==================== FullCalendar ==================== -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.20/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.20/locales-all.global.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const calendarEl = document.getElementById('calendar');
    const IS_LOGIN   = <?= $is_login ? 'true' : 'false' ?>;
    const LOGIN_UID  = <?= $login_uid ?>;

    // ── FullCalendar 초기화 ──────────────────────────────
    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'ko',
        height: 'auto',

        headerToolbar: {
            left:   'prev,next today',
            center: 'title',
            right:  'dayGridMonth,timeGridWeek,listWeek'
        },

        buttonText: {
            today: '오늘',
            month: '월',
            week:  '주',
            list:  '목록'
        },

        events: {
            url: '/cal/cal_events.php',
            method: 'GET',
            failure: function() {
                alert('일정을 불러오지 못했습니다.');
             }
        },

        // 날짜 클릭 — 비로그인이면 등록 안 함
        dateClick: IS_LOGIN ? function(info) {
            resetForm();
            document.getElementById('start_date').value = info.dateStr + 'T00:00';
            document.getElementById('end_date').value   = '';
            bootstrap.Modal.getOrCreateInstance(document.getElementById('scheduleModal')).show();
        } : null,

        // 일정 클릭 — 상세 모달
        eventClick: function(info) {
            showDetail(info.event);
        },

        // ✅ 드래그&드롭 + 리사이즈 활성화
        editable: IS_LOGIN,          // 로그인한 사람만 드래그 가능
        eventStartEditable: true,    // 시작일 드래그 이동
        eventDurationEditable: true, // 우측 끝 드래그로 기간 조절

        // ✅ 드래그 후 날짜 변경 → DB 업데이트
        eventDrop: function(info) {
            const event = info.event;
            // 본인 일정만 드래그 가능하게
            if (event.extendedProps.user_id != LOGIN_UID) {
                info.revert(); // 되돌리기
                showToast('본인 일정만 이동할 수 있습니다.', true);
                return;
            }
            updateEventDate(event, info.revert);
        },

        // ✅ 리사이즈 후 기간 변경 → DB 업데이트
        eventResize: function(info) {
            const event = info.event;
            if (event.extendedProps.user_id != LOGIN_UID) {
                info.revert();
                showToast('본인 일정만 수정할 수 있습니다.', true);
                return;
            }
            updateEventDate(event, info.revert);
        },
    });

    calendar.render();


    // ── 일정 등록 폼 submit ──────────────────────────────
    const form = document.getElementById('scheduleForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.textContent = '등록 중...';

            const formData = new FormData(form);
            // 체크박스 Y/N 명시적으로 set
            formData.set('allday_yn', document.getElementById('allday_yn').checked ? 'Y' : 'N');

            fetch('cal_save.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(result => {
                if (result.success) {
                    bootstrap.Modal.getInstance(document.getElementById('scheduleModal'))?.hide();
                    calendar.refetchEvents();
                    showToast('일정이 등록되었습니다.');
                } else {
                    showToast(result.message || '등록에 실패했습니다.', true);
                }
            })
            .catch(() => showToast('서버 오류가 발생했습니다.', true))
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.textContent = '등록';
            });
        });
    }


    // ── 일정 상세 모달 ──────────────────────────────────
    function showDetail(event) {
        const props   = event.extendedProps;
        const color   = event.backgroundColor || '#212529';
        const isOwner = IS_LOGIN && props.user_id == LOGIN_UID;

        const startStr = formatDatetime(event.start, props.allday_yn === 'Y');
        const endStr   = event.end ? formatDatetime(event.end, props.allday_yn === 'Y') : '';

        let html = `
            <div class="d-flex align-items-center gap-2 mb-3">
                <span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:${color};flex-shrink:0"></span>
                <strong style="font-size:1.05rem">${escHtml(event.title)}</strong>
            </div>
            <dl class="row mb-0 small">
                <dt class="col-sm-3 text-muted">등록자</dt>
                <dd class="col-sm-9">${escHtml(props.user_name || '-')}</dd>

                <dt class="col-sm-3 text-muted">시작</dt>
                <dd class="col-sm-9">${startStr}</dd>
        `;

        if (endStr && endStr !== startStr) {
            html += `
                <dt class="col-sm-3 text-muted">종료</dt>
                <dd class="col-sm-9">${endStr}</dd>
            `;
        }

        if (props.content) {
            html += `
                <dt class="col-sm-3 text-muted">내용</dt>
                <dd class="col-sm-9" style="white-space:pre-wrap">${escHtml(props.content)}</dd>
            `;
        }

        html += `</dl>`;

        document.getElementById('detailBody').innerHTML = html;

        // 내 일정이면 삭제 버튼 추가
        const footer = document.getElementById('detailFooter');
        if (isOwner) {
            footer.innerHTML = `
                <button type="button" class="btn btn-outline-danger btn-sm me-auto"
                    onclick="deleteEvent(${event.id})">
                    삭제
                </button>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">닫기</button>
            `;
        } else {
            footer.innerHTML = `
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">닫기</button>
            `;
        }

        bootstrap.Modal.getOrCreateInstance(document.getElementById('detailModal')).show();
    }




    // ── 일정 삭제 ────────────────────────────────────────
    window.deleteEvent = function(id) {
        if (!confirm('이 일정을 삭제하시겠습니까?')) return;

        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('id', id);

        fetch('cal_save.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(result => {
            if (result.success) {
                bootstrap.Modal.getInstance(document.getElementById('detailModal'))?.hide();
                calendar.refetchEvents();
                showToast('일정이 삭제되었습니다.');
            } else {
                showToast(result.message || '삭제에 실패했습니다.', true);
            }
        })
        .catch(() => showToast('서버 오류가 발생했습니다.', true));
    };

    // ── 드래그&리사이즈 후 날짜 DB 업데이트 ──────────────
        function updateEventDate(event, revertFn) {
        const allday = event.allDay;

        const pad = n => String(n).padStart(2, '0');

        // ✅ 한국/브라우저 로컬 날짜 기준으로 변환
        const formatLocalDate = (date) => {
            if (!date) return '';

            return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
        };

        const formatLocalDateTime = (date) => {
            if (!date) return '';

            return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
                + `T${pad(date.getHours())}:${pad(date.getMinutes())}`;
        };

        let startValue = '';
        let endValue = '';

        if (allday) {
            startValue = formatLocalDate(event.start);

            // FullCalendar의 종일 일정 end는 실제 종료일 다음날이라서 -1일 처리
            if (event.end) {
                const endDate = new Date(event.end);
                endDate.setDate(endDate.getDate() - 1);
                endValue = formatLocalDate(endDate);
            }
        } else {
            startValue = formatLocalDateTime(event.start);
            endValue = event.end ? formatLocalDateTime(event.end) : '';
        }

        const formData = new FormData();
        formData.append('action', 'update_date');
        formData.append('id', event.id);
        formData.append('start_date', startValue);
        formData.append('end_date', endValue);
        formData.append('allday_yn', allday ? 'Y' : 'N');

        fetch('/cal/cal_save.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(result => {
            if (result.success) {
                calendar.refetchEvents();
                showToast('일정이 업데이트되었습니다.');
            } else {
                revertFn();
                showToast(result.message || '업데이트에 실패했습니다.', true);
            }
        })
        .catch(() => {
            revertFn();
            showToast('서버 오류가 발생했습니다.', true);
        });
    }


    // ── 폼 초기화 ────────────────────────────────────────
    function resetForm() {
        document.getElementById('title').value   = '';
        document.getElementById('content').value = '';
        document.getElementById('color').value   = '#212529';
        document.getElementById('allday_yn').checked = true;
        document.getElementById('start_date').value  = '';
        document.getElementById('end_date').value    = '';
    }


    // ── 토스트 메시지 ─────────────────────────────────────
    function showToast(msg, isError = false) {
        const existing = document.getElementById('calToastWrap');
        const wrap = existing || (() => {
            const el = document.createElement('div');
            el.id = 'calToastWrap';
            el.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:9999;display:flex;flex-direction:column;gap:8px';
            document.body.appendChild(el);
            return el;
        })();

        const toast = document.createElement('div');
        toast.className = `alert ${isError ? 'alert-danger' : 'alert-dark'} py-2 px-3 mb-0 shadow-sm`;
        toast.style.cssText = 'font-size:.875rem;animation:fadeInUp .25s ease;min-width:220px';
        toast.textContent = msg;
        wrap.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }


    // ── 유틸 ─────────────────────────────────────────────
    function escHtml(str) {
        return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    function formatDatetime(date, allday) {
        if (!date) return '';
        const d   = new Date(date);
        const ymd = `${d.getFullYear()}.${pad(d.getMonth()+1)}.${pad(d.getDate())}`;
        if (allday) return ymd;
        return `${ymd} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
    }

    function pad(n) { return String(n).padStart(2, '0'); }
});
</script>

<?php include '../includes/footer.php'; ?>
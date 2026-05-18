</main><!-- /.adm-main -->
</div><!-- /.adm-shell -->

<script>
// 현재 날짜 표시
document.getElementById('adm-date-sub').textContent =
    new Date().toLocaleDateString('ko-KR', { year:'numeric', month:'long', day:'numeric', weekday:'long' });

// 배경 클릭 시 모달 닫기 (공통)
document.querySelectorAll('.adm-modal-bg').forEach(function(el) {
    el.addEventListener('click', function(e) {
        if (e.target === el) el.classList.remove('show');
    });
});
</script>
</body>
</html>
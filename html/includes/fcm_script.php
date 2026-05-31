<?php
// /includes/fcm_script.php
// header.php 하단 </body> 직전에 include
// 로그인한 사용자에게만 FCM 초기화

if (!isset($_SESSION['user_id'])) return;
?>
<!-- Firebase FCM 초기화 -->
<script type="module">
import { initializeApp }  from 'https://www.gstatic.com/firebasejs/10.12.0/firebase-app.js';
import { getMessaging, getToken, onMessage }
    from 'https://www.gstatic.com/firebasejs/10.12.0/firebase-messaging.js';
 
const firebaseConfig = {
    apiKey:            "AIzaSyDinmZLLyPIT1P8o7lpSMuivSpwD22Il0k",
    authDomain:        "hangul-dothome-co-kr-cb0fb.firebaseapp.com",
    projectId:         "hangul-dothome-co-kr-cb0fb",
    storageBucket:     "hangul-dothome-co-kr-cb0fb.firebasestorage.app",
    messagingSenderId: "325608235835",
    appId:             "1:325608235835:web:044034d0339ac22aae4d1e"
};

const VAPID_KEY = 'BMdICxG5citb3mTKzL--m5W1nFnnkvDjllaryQ1qmWWT_mQy6-L9Y3mAb__9JmgGqCM4x_4w1eeB2chI6Pnml0o';

const app       = initializeApp(firebaseConfig);
const messaging = getMessaging(app);

// ── 알림 권한 요청 + 토큰 저장 ──────────────────────────────
async function requestAndSaveToken() {
    try {
        const permission = await Notification.requestPermission();
        if (Notification.permission !== 'granted') {
            alert('알림 권한이 거부되었습니다. 기기 설정에서 허용해주세요.'+permission);
            //alert('requestPermission=' + permission);
            //alert('Notification.permission=' + Notification.permission);
            //alert('standalone=' + window.navigator.standalone);
            //alert('display-mode=' + window.matchMedia('(display-mode: standalone)').matches);
            return;
        }

        const token = await getToken(messaging, { vapidKey: VAPID_KEY });
        if (!token) return;

        // 💡 [여기가 핵심!] 권한을 얻자마자 즉시 화면의 버튼을 '알림 켜짐'으로 변경
        const btnPc = document.getElementById('fcm-allow-btn');
        const btnMobile = document.getElementById('fcm-allow-btn-mobile');
        
        if (btnPc) { 
            btnPc.innerHTML = '<i class="bi bi-bell-check-fill"></i> 알림 켜짐'; 
            btnPc.disabled = true; 
        }
        if (btnMobile) { 
            btnMobile.innerHTML = '<i class="bi bi-bell-check-fill"></i> 알림 켜짐'; 
            btnMobile.disabled = true; 
        }

        // 이미 저장된 토큰과 같으면 서버 전송 생략 (최적화)
        const saved = localStorage.getItem('fcm_token');
        if (saved === token) return;

        const res = await fetch('/fcm_save_token.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ token })
        });
        const data = await res.json();
        
        if (data.success) {
            localStorage.setItem('fcm_token', token);
            console.log('[FCM] 토큰 저장 완료');
            // 유저에게 성공했다고 알려주는 팝업 추가
            alert('푸시 알림이 성공적으로 설정되었습니다! 🎉'); 
        }
    } catch (e) {
    console.error('[FCM]', e);
    alert(e.message);
    }
}

// ── 포그라운드 메시지 수신 (사이트 열려있을 때) ──────────────
onMessage(messaging, function(payload) {
    console.log('[FCM] 포그라운드 메시지:', payload);

    const title = payload.notification?.title || '한글은 늘 도망가';
    const body  = payload.notification?.body  || '새 알림이 있습니다.';
    const url   = payload.data?.url           || '/notifications.php';

    // 브라우저 알림 표시 수정 - 브라우저 알림 제거, 뱃지 갱신만
    // if (Notification.permission === 'granted') {
    //     const noti = new Notification(title, {
    //         body: body,
    //         icon: '/favicon.ico',
    //     });
    //     noti.onclick = () => { window.location.href = url; };
    // }

    // 헤더 알림 뱃지 갱신 (있는 경우)
    const badge = document.getElementById('noti-badge');
    if (badge) {
        const cur = parseInt(badge.textContent) || 0;
        badge.textContent = cur + 1;
        badge.style.display = 'inline';
    }
});
 
// ── 알림 허용 버튼 클릭 이벤트 ──────────────────────────────
const btnPc = document.getElementById('fcm-allow-btn');
const btnMobile = document.getElementById('fcm-allow-btn-mobile');

if (btnPc) {
    btnPc.addEventListener('click', requestAndSaveToken);
}
// 💡 [핵심] 모바일 버튼에도 클릭 이벤트를 달아줍니다!
if (btnMobile) {
    btnMobile.addEventListener('click', requestAndSaveToken);
}

// ── 이미 허용된 경우 자동으로 토큰 갱신 및 버튼 UI 변경 ─────
if (Notification.permission === 'granted') {
    requestAndSaveToken();
    
    // PC 버튼 글자 바꾸기
    if (btnPc) { 
        btnPc.innerHTML = '<i class="bi bi-bell-check-fill"></i> 알림 켜짐'; 
        btnPc.disabled = true; 
    }
    
    // 모바일 버튼 글자 바꾸기
    if (btnMobile) { 
        btnMobile.innerHTML = '<i class="bi bi-bell-check-fill"></i> 알림 켜짐'; 
        btnMobile.disabled = true; 
    }
}
</script>

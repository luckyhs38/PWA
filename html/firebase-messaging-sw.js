// /firebase-messaging-sw.js
// 반드시 웹 루트(public_html/)에 위치해야 함

importScripts('https://www.gstatic.com/firebasejs/10.12.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/10.12.0/firebase-messaging-compat.js');

firebase.initializeApp({
    apiKey:            "AIzaSyDinmZLLyPIT1P8o7lpSMuivSpwD22Il0k",
    authDomain:        "hangul-dothome-co-kr-cb0fb.firebaseapp.com",
    projectId:         "hangul-dothome-co-kr-cb0fb",
    storageBucket:     "hangul-dothome-co-kr-cb0fb.firebasestorage.app",
    messagingSenderId: "325608235835",
    appId:             "1:325608235835:web:044034d0339ac22aae4d1e"
});

const messaging = firebase.messaging();

// 백그라운드 푸시 수신
messaging.onBackgroundMessage(function(payload) {
    console.log('[SW] 백그라운드 메시지 수신:', payload);
/* 알람이 두 번 와서 삭제
    const title = payload.notification?.title || '한글은 늘 도망가';
    const body  = payload.notification?.body  || '새 알림이 있습니다.';
    const icon  = payload.notification?.icon  || '/favicon.ico';
    const url   = payload.data?.url           || '/notifications.php';

    self.registration.showNotification(title, {
        body:  body,
        icon:  icon,
        badge: '/favicon.ico',
        data:  { url: url },
        vibrate: [200, 100, 200],
    });
    */
});
// 알림 클릭 시 해당 URL로 이동
self.addEventListener('notificationclick', function(event) {
    event.notification.close();
    const url = event.notification.data?.url || '/notifications.php';
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true })
            .then(function(clientList) {
                for (var i = 0; i < clientList.length; i++) {
                    if (clientList[i].url === url && 'focus' in clientList[i]) {
                        return clientList[i].focus();
                    }
                }
                if (clients.openWindow) {
                    return clients.openWindow(url);
                }
            })
    );
});

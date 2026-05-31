<?php
// /includes/fcm_init.php
// FCM V1 API - 서비스 계정 JSON으로 액세스 토큰 발급 후 푸시 발송

define('FCM_SERVICE_ACCOUNT_PATH', __DIR__ . '/hangul-dothome-co-kr-cb0fb-cbf953e0ad35.json');
define('FCM_PROJECT_ID', 'hangul-dothome-co-kr-cb0fb');
define('FCM_VAPID_KEY',  'BMdICxG5citb3mTKzL--m5W1nFnnkvDjllaryQ1qmWWT_mQy6-L9Y3mAb__9JmgGqCM4x_4w1eeB2chI6Pnml0o');

// ── JWT 생성 (서비스 계정 인증용) ──────────────────────────────
function fcm_get_access_token(): ?string {
    static $cached_token = null;
    static $token_expiry = 0;

    // 캐시된 토큰이 아직 유효하면 재사용
    if ($cached_token && time() < $token_expiry - 60) {
        return $cached_token;
    }

    $sa = json_decode(file_get_contents(FCM_SERVICE_ACCOUNT_PATH), true);
    if (!$sa) return null;

    $now = time();
    $header  = base64url_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $payload = base64url_encode(json_encode([
        'iss'   => $sa['client_email'],
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'aud'   => 'https://oauth2.googleapis.com/token',
        'iat'   => $now,
        'exp'   => $now + 3600,
    ]));

    $sign_input = $header . '.' . $payload;

    openssl_sign($sign_input, $signature, $sa['private_key'], 'SHA256');
    $jwt = $sign_input . '.' . base64url_encode($signature);

    // 액세스 토큰 요청
// 💡 [수정 전]
    // $response = file_get_contents('https://oauth2.googleapis.com/token', false, stream_context_create([ ... ]));
    
    // 💡 [수정 후: cURL로 변경]
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion'  => $jwt,
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded'
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);

    if (!$response) return null;

    $data = json_decode($response, true);
    if (empty($data['access_token'])) return null;

    $cached_token = $data['access_token'];
    $token_expiry = $now + ($data['expires_in'] ?? 3600);

    return $cached_token;
}

// Base64 URL 인코딩 헬퍼
function base64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

// ── 단일 토큰에 푸시 발송 ──────────────────────────────────────
function fcm_send_to_token(
    string $fcm_token,
    string $title,
    string $body,
    string $url = '/notifications.php',
    array  $extra_data = []
): bool {
    $access_token = fcm_get_access_token();
    if (!$access_token) return false;

    $payload = [
        'message' => [
            'token'        => $fcm_token,
            'notification' => [
                'title' => $title,
                'body'  => $body,
            ],
            'data' => array_merge(['url' => $url], $extra_data),
            'webpush' => [
                'fcm_options' => ['link' => $url],
                'notification' => [
                    'icon'    => '/favicon.ico',
                    'badge'   => '/favicon.ico',
                    'vibrate' => [200, 100, 200],
                ],
            ],
        ],
    ];

    $api_url  = 'https://fcm.googleapis.com/v1/projects/' . FCM_PROJECT_ID . '/messages:send';
$api_url  = 'https://fcm.googleapis.com/v1/projects/' . FCM_PROJECT_ID . '/messages:send';
    
    // 💡 [수정 전]
    // $response = file_get_contents($api_url, false, stream_context_create([ ... ]));
    
    // 💡 [수정 후: cURL로 변경]
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $access_token,
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($response, true);
    return !empty($result['name']); // 성공 시 name 필드 반환
}

// ── 여러 user_id에게 푸시 발송 ────────────────────────────────
function fcm_send_to_users(
    PDO    $pdo,
    array  $user_ids,
    string $title,
    string $body,
    string $url = '/notifications.php'
): void {
    if (empty($user_ids)) return;

    $placeholders = implode(',', array_fill(0, count($user_ids), '?'));
    $stmt = $pdo->prepare("
        SELECT user_id, token FROM fcm_tokens
        WHERE user_id IN ({$placeholders})
    ");
    $stmt->execute($user_ids);
    $tokens = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($tokens as $row) {
        fcm_send_to_token($row['token'], $title, $body, $url);
    }
}

<?php
// /board/image_upload.php
// Summernote 에디터에서 이미지 업로드 시 호출되는 파일

require_once '../includes/db.php';
require_once '../includes/auth_check.php'; // 로그인한 사용자만 업로드 가능

header('Content-Type: application/json');

// POST + 파일 있는지 확인
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['image'])) {
    echo json_encode(['error' => '잘못된 요청입니다.']);
    exit;
}

$file     = $_FILES['image'];
$max_size = 5 * 1024 * 1024; // 5MB

// 크기 검사
if ($file['size'] > $max_size) {
    echo json_encode(['error' => '이미지는 5MB 이하만 업로드 가능합니다.']);
    exit;
}

// 확장자 검사
$allowed_ext  = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$ext          = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $allowed_ext)) {
    echo json_encode(['error' => '허용되지 않는 파일 형식입니다.']);
    exit;
}

// MIME 타입 검사 (확장자 위조 방지)
$finfo        = finfo_open(FILEINFO_MIME_TYPE);
$mime         = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);
$allowed_mime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
if (!in_array($mime, $allowed_mime)) {
    echo json_encode(['error' => '유효하지 않은 이미지 파일입니다.']);
    exit;
}

// 업로드 디렉토리 생성
$upload_dir = '../uploads/board/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// 파일 저장
$new_name = uniqid('img_', true) . '.' . $ext;
$dest     = $upload_dir . $new_name;

if (move_uploaded_file($file['tmp_name'], $dest)) {
    // 성공 시 접근 가능한 URL 반환
    echo json_encode(['url' => '/uploads/board/' . $new_name]);
} else {
    echo json_encode(['error' => '파일 저장에 실패했습니다.']);
}
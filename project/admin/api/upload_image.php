<?php
session_start();

header('Content-Type: application/json');

// AJAX-friendly authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

try {
    if (!isset($_FILES['image'])) {
        throw new Exception('Không có file được upload');
    }
    
    $file = $_FILES['image'];
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Lỗi khi upload file');
    }
    
    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $fileType = mime_content_type($file['tmp_name']);
    
    if (!in_array($fileType, $allowedTypes)) {
        throw new Exception('Chỉ chấp nhận file ảnh (JPG, PNG, GIF, WEBP)');
    }
    
    // Validate file size (max 5MB)
    $maxSize = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $maxSize) {
        throw new Exception('Kích thước file không được vượt quá 5MB');
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('product_') . '_' . time() . '.' . $extension;
    
    // Upload directory
    $uploadDir = '../../public/assets/images/products/';
    
    // Create directory if not exists
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $uploadPath = $uploadDir . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        throw new Exception('Không thể lưu file');
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Upload ảnh thành công',
        'filename' => $filename,
        'path' => 'products/' . $filename
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

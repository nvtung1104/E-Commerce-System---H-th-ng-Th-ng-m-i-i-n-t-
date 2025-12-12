<?php
session_start();
require_once '../../public/config/db.php';

header('Content-Type: application/json');

// AJAX-friendly authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$pdo = getDBConnection();

try {
    // Get all categories sorted A-Z
    $stmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Organize categories by parent
    $parentCategories = [];
    $childCategories = [];
    
    foreach ($categories as $cat) {
        if ($cat['parent_id'] === null) {
            $parentCategories[] = $cat;
        } else {
            if (!isset($childCategories[$cat['parent_id']])) {
                $childCategories[$cat['parent_id']] = [];
            }
            $childCategories[$cat['parent_id']][] = $cat;
        }
    }
    
    echo json_encode([
        'success' => true,
        'categories' => $categories,
        'parentCategories' => $parentCategories,
        'childCategories' => $childCategories
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

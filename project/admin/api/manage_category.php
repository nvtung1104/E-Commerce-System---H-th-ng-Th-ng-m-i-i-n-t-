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

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'POST') {
        // Add or Update Category
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['name']) || empty(trim($data['name']))) {
            throw new Exception('Tên danh mục không được để trống');
        }
        
        $name = trim($data['name']);
        $description = isset($data['description']) ? trim($data['description']) : null;
        $parent_id = isset($data['parent_id']) && $data['parent_id'] !== '' ? (int)$data['parent_id'] : null;
        
        if (isset($data['id']) && !empty($data['id'])) {
            // Update existing category
            $id = (int)$data['id'];
            
            // Check if category exists
            $stmt = $pdo->prepare("SELECT id FROM categories WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                throw new Exception('Danh mục không tồn tại');
            }
            
            // Update category
            $stmt = $pdo->prepare("UPDATE categories SET name = ?, description = ?, parent_id = ? WHERE id = ?");
            $stmt->execute([$name, $description, $parent_id, $id]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Cập nhật danh mục thành công',
                'id' => $id
            ]);
        } else {
            // Add new category
            $stmt = $pdo->prepare("INSERT INTO categories (name, description, parent_id) VALUES (?, ?, ?)");
            $stmt->execute([$name, $description, $parent_id]);
            
            $newId = $pdo->lastInsertId();
            
            echo json_encode([
                'success' => true,
                'message' => 'Thêm danh mục thành công',
                'id' => $newId
            ]);
        }
        
    } elseif ($method === 'DELETE') {
        // Delete Category
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['id'])) {
            throw new Exception('ID danh mục không hợp lệ');
        }
        
        $id = (int)$data['id'];
        
        // Check if category has products
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM products WHERE category_id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] > 0) {
            throw new Exception('Không thể xóa danh mục đang có sản phẩm');
        }
        
        // Check if category has child categories
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM categories WHERE parent_id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] > 0) {
            throw new Exception('Không thể xóa danh mục đang có danh mục con');
        }
        
        // Delete category
        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Xóa danh mục thành công'
        ]);
        
    } else {
        throw new Exception('Phương thức không được hỗ trợ');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

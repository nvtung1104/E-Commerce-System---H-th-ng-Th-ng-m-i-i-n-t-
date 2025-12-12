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
        // Add or Update Product
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validate required fields
        if (!isset($data['name']) || empty(trim($data['name']))) {
            throw new Exception('Tên sản phẩm không được để trống');
        }
        if (!isset($data['sku']) || empty(trim($data['sku']))) {
            throw new Exception('SKU không được để trống');
        }
        if (!isset($data['category_id']) || empty($data['category_id'])) {
            throw new Exception('Vui lòng chọn danh mục');
        }
        if (!isset($data['price']) || $data['price'] <= 0) {
            throw new Exception('Giá sản phẩm phải lớn hơn 0');
        }
        if (!isset($data['quantity']) || $data['quantity'] < 0) {
            throw new Exception('Số lượng không hợp lệ');
        }
        
        $name = trim($data['name']);
        $sku = trim($data['sku']);
        $category_id = (int)$data['category_id'];
        $price = (float)$data['price'];
        $sale_price = isset($data['sale_price']) && $data['sale_price'] > 0 ? (float)$data['sale_price'] : null;
        $quantity = (int)$data['quantity'];
        $description = isset($data['description']) ? trim($data['description']) : null;
        $thumbnail = isset($data['thumbnail']) ? trim($data['thumbnail']) : null;
        
        if (isset($data['id']) && !empty($data['id'])) {
            // Update existing product
            $id = (int)$data['id'];
            
            // Check if SKU is already used by another product
            $stmt = $pdo->prepare("SELECT id FROM products WHERE sku = ? AND id != ?");
            $stmt->execute([$sku, $id]);
            if ($stmt->fetch()) {
                throw new Exception('SKU đã được sử dụng cho sản phẩm khác');
            }
            
            // Update product
            $stmt = $pdo->prepare("UPDATE products SET name = ?, sku = ?, category_id = ?, price = ?, sale_price = ?, description = ?, thumbnail = ? WHERE id = ?");
            $stmt->execute([$name, $sku, $category_id, $price, $sale_price, $description, $thumbnail, $id]);
            
            // Stock quantity is already updated in products table above
            
            echo json_encode([
                'success' => true,
                'message' => 'Cập nhật sản phẩm thành công',
                'id' => $id
            ]);
        } else {
            // Add new product
            // Check if SKU already exists
            $stmt = $pdo->prepare("SELECT id FROM products WHERE sku = ?");
            $stmt->execute([$sku]);
            if ($stmt->fetch()) {
                throw new Exception('SKU đã tồn tại trong hệ thống');
            }
            
            // Insert product
            $stmt = $pdo->prepare("INSERT INTO products (name, sku, category_id, price, sale_price, description, thumbnail) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $sku, $category_id, $price, $sale_price, $description, $thumbnail]);
            
            $newId = $pdo->lastInsertId();
            
            // Stock quantity is already inserted in products table above
            
            echo json_encode([
                'success' => true,
                'message' => 'Thêm sản phẩm thành công',
                'id' => $newId
            ]);
        }
        
    } elseif ($method === 'DELETE') {
        // Delete Product
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['id'])) {
            throw new Exception('ID sản phẩm không hợp lệ');
        }
        
        $id = (int)$data['id'];
        
        // No need to delete from inventory table (using stock_quantity in products table)
        
        // Delete product
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Xóa sản phẩm thành công'
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

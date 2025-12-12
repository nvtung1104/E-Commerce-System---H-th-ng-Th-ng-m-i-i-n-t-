<?php
header('Content-Type: application/json');
require_once '../config/db.php';

$query = $_GET['q'] ?? '';

if (strlen($query) < 2) {
    echo json_encode([]);
    exit;
}

try {
    $pdo = getDBConnection();
    
    // Search products with relevance ordering
    $sql = "SELECT DISTINCT p.id, p.name, p.thumbnail, p.price, p.sale_price, 
            c.name as category_name,
            COALESCE(p.sale_price, p.price) as final_price,
            CASE 
                WHEN p.name LIKE :exact THEN 1
                WHEN p.name LIKE :start THEN 2
                WHEN p.description LIKE :start THEN 3
                ELSE 4
            END as relevance
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.status = 1 
            AND (p.name LIKE :search OR p.description LIKE :search OR p.sku LIKE :search OR c.name LIKE :search)
            ORDER BY relevance, p.name
            LIMIT 8";
    
    $stmt = $pdo->prepare($sql);
    $searchParam = '%' . $query . '%';
    $exactParam = $query;
    $startParam = $query . '%';
    
    $stmt->bindParam(':search', $searchParam);
    $stmt->bindParam(':exact', $exactParam);
    $stmt->bindParam(':start', $startParam);
    $stmt->execute();
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format results for autocomplete
    $suggestions = array_map(function($product) use ($query) {
        return [
            'id' => $product['id'],
            'name' => $product['name'],
            'category' => $product['category_name'],
            'price' => number_format($product['final_price']),
            'thumbnail' => $product['thumbnail'],
            'highlight' => highlightMatch($product['name'], $query)
        ];
    }, $results);
    
    echo json_encode($suggestions);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

function highlightMatch($text, $query) {
    return preg_replace('/(' . preg_quote($query, '/') . ')/i', '<strong>$1</strong>', $text);
}

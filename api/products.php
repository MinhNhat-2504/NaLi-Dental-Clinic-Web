<?php
/**
 * API Quản lý Dịch vụ (Products)
 * CRUD: Create, Read, Update, Delete
 */
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once '../config.php';

$method = $_SERVER['REQUEST_METHOD'];

// GET - Lấy danh sách hoặc chi tiết dịch vụ
if ($method === 'GET') {
    if (isset($_GET['id'])) {
        // Lấy chi tiết 1 dịch vụ
        $id = intval($_GET['id']);
        $stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            echo json_encode(['success' => true, 'product' => $row]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy dịch vụ']);
        }
    } else {
        // Lấy tất cả dịch vụ
        $where = "1=1";
        if (isset($_GET['target_group']) && $_GET['target_group']) {
            $group = $conn->real_escape_string($_GET['target_group']);
            $where .= " AND target_group = '$group'";
        }
        if (isset($_GET['search']) && $_GET['search']) {
            $search = $conn->real_escape_string($_GET['search']);
            $where .= " AND (name LIKE '%$search%' OR description LIKE '%$search%')";
        }
        
        $sql = "SELECT * FROM products WHERE $where ORDER BY id DESC";
        $result = $conn->query($sql);
        
        $products = [];
        while ($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
        
        echo json_encode(['success' => true, 'products' => $products, 'count' => count($products)]);
    }
}

// POST - Thêm dịch vụ mới
elseif ($method === 'POST') {
    // Kiểm tra quyền admin
    if (!isset($_SESSION['auth_user']['role']) || $_SESSION['auth_user']['role'] !== 'admin') {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Không có quyền thực hiện']);
        exit;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $name = $conn->real_escape_string($data['name'] ?? '');
    $description = $conn->real_escape_string($data['description'] ?? '');
    $price = floatval($data['price'] ?? 0);
    $image = $conn->real_escape_string($data['image'] ?? '');
    $target_group = $conn->real_escape_string($data['target_group'] ?? 'adults');
    $duration = intval($data['duration'] ?? 30);
    
    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Tên dịch vụ không được để trống']);
        exit;
    }
    
    $stmt = $conn->prepare("INSERT INTO products (name, description, price, image, target_group, duration) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssdssi", $name, $description, $price, $image, $target_group, $duration);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Thêm dịch vụ thành công', 'id' => $conn->insert_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $stmt->error]);
    }
}

// PUT - Cập nhật dịch vụ
elseif ($method === 'PUT') {
    if (!isset($_SESSION['auth_user']['role']) || $_SESSION['auth_user']['role'] !== 'admin') {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Không có quyền thực hiện']);
        exit;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $id = intval($data['id'] ?? 0);
    $name = $conn->real_escape_string($data['name'] ?? '');
    $description = $conn->real_escape_string($data['description'] ?? '');
    $price = floatval($data['price'] ?? 0);
    $image = $conn->real_escape_string($data['image'] ?? '');
    $target_group = $conn->real_escape_string($data['target_group'] ?? 'adults');
    $duration = intval($data['duration'] ?? 30);
    $is_active = intval($data['is_active'] ?? 1);
    
    if ($id <= 0 || empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']);
        exit;
    }
    
    $stmt = $conn->prepare("UPDATE products SET name=?, description=?, price=?, image=?, target_group=?, duration=?, is_active=? WHERE id=?");
    $stmt->bind_param("ssdssiii", $name, $description, $price, $image, $target_group, $duration, $is_active, $id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Cập nhật thành công']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $stmt->error]);
    }
}

// DELETE - Xóa dịch vụ
elseif ($method === 'DELETE') {
    if (!isset($_SESSION['auth_user']['role']) || $_SESSION['auth_user']['role'] !== 'admin') {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Không có quyền thực hiện']);
        exit;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $id = intval($data['id'] ?? 0);
    
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']);
        exit;
    }
    
    $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Xóa thành công']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $stmt->error]);
    }
}

$conn->close();
?>

<?php
/**
 * API Quản lý Lịch hẹn (Appointments)
 * CRUD + Thay đổi trạng thái
 */
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once '../config.php';

$method = $_SERVER['REQUEST_METHOD'];

// GET - Lấy danh sách lịch hẹn
if ($method === 'GET') {
    // Chỉ admin mới xem được tất cả
    if (!isset($_SESSION['auth_user']['role']) || $_SESSION['auth_user']['role'] !== 'admin') {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Không có quyền truy cập']);
        exit;
    }
    
    // Lấy 1 appointment theo ID
    if (isset($_GET['id']) && $_GET['id']) {
        $id = intval($_GET['id']);
        $sql = "SELECT * FROM appointments WHERE id = $id";
        $result = $conn->query($sql);
        
        if ($result && $result->num_rows > 0) {
            $appointment = $result->fetch_assoc();
            echo json_encode(['success' => true, 'appointment' => $appointment]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy lịch hẹn']);
        }
        exit;
    }
    
    $where = "1=1";
    
    // Lọc theo trạng thái
    if (isset($_GET['status']) && $_GET['status']) {
        if ($_GET['status'] === 'pending_confirmed') {
            $where .= " AND status IN ('pending', 'confirmed')";
        } else {
            $status = $conn->real_escape_string($_GET['status']);
            $where .= " AND status = '$status'";
        }
    }
    
    // Lọc theo ngày
    if (isset($_GET['date']) && $_GET['date']) {
        $date = $conn->real_escape_string($_GET['date']);
        $where .= " AND appointment_date = '$date'";
    }
    
    // Tìm kiếm
    if (isset($_GET['search']) && $_GET['search']) {
        $search = $conn->real_escape_string($_GET['search']);
        $where .= " AND (customer_name LIKE '%$search%' OR customer_phone LIKE '%$search%' OR customer_email LIKE '%$search%')";
    }
    
    $sql = "SELECT * FROM appointments WHERE $where ORDER BY appointment_date DESC, appointment_time DESC";
    $result = $conn->query($sql);
    
    $appointments = [];
    while ($row = $result->fetch_assoc()) {
        $appointments[] = $row;
    }
    
    echo json_encode(['success' => true, 'appointments' => $appointments, 'count' => count($appointments)]);
}

// POST - Tạo lịch hẹn mới (cho khách hàng)
elseif ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $user_id = isset($_SESSION['auth_user']['id']) ? intval($_SESSION['auth_user']['id']) : null;
    $product_ids = $conn->real_escape_string($data['product_ids'] ?? '');
    $customer_name = $conn->real_escape_string($data['customer_name'] ?? '');
    $customer_phone = $conn->real_escape_string($data['customer_phone'] ?? '');
    $customer_email = $conn->real_escape_string($data['customer_email'] ?? '');
    $appointment_date = $conn->real_escape_string($data['appointment_date'] ?? '');
    $appointment_time = $conn->real_escape_string($data['appointment_time'] ?? '');
    $notes = $conn->real_escape_string($data['notes'] ?? '');
    $payment_method = $conn->real_escape_string($data['payment_method'] ?? 'cash');
    $discount_code = $conn->real_escape_string($data['discount_code'] ?? '');
    $discount_amount = floatval($data['discount_amount'] ?? 0);
    $total_price = floatval($data['total_price'] ?? 0);
    
    if (empty($customer_name) || empty($customer_phone) || empty($appointment_date) || empty($appointment_time)) {
        echo json_encode(['success' => false, 'message' => 'Vui lòng điền đầy đủ thông tin bắt buộc']);
        exit;
    }
    
    $stmt = $conn->prepare("INSERT INTO appointments (user_id, product_ids, customer_name, customer_phone, customer_email, appointment_date, appointment_time, notes, payment_method, discount_code, discount_amount, total_price) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssssssssdd", $user_id, $product_ids, $customer_name, $customer_phone, $customer_email, $appointment_date, $appointment_time, $notes, $payment_method, $discount_code, $discount_amount, $total_price);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Đặt lịch thành công', 'id' => $conn->insert_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $stmt->error]);
    }
}

// PUT - Cập nhật lịch hẹn (Admin)
elseif ($method === 'PUT') {
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
    
    // Nếu chỉ cập nhật trạng thái
    if (isset($data['status']) && count($data) <= 2) {
        $status = $conn->real_escape_string($data['status']);
        $stmt = $conn->prepare("UPDATE appointments SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $id);
    } else {
        // Cập nhật đầy đủ
        $customer_name = $conn->real_escape_string($data['customer_name'] ?? '');
        $customer_phone = $conn->real_escape_string($data['customer_phone'] ?? '');
        $customer_email = $conn->real_escape_string($data['customer_email'] ?? '');
        $appointment_date = $conn->real_escape_string($data['appointment_date'] ?? '');
        $appointment_time = $conn->real_escape_string($data['appointment_time'] ?? '');
        $notes = $conn->real_escape_string($data['notes'] ?? '');
        $status = $conn->real_escape_string($data['status'] ?? 'pending');
        
        $stmt = $conn->prepare("UPDATE appointments SET customer_name=?, customer_phone=?, customer_email=?, appointment_date=?, appointment_time=?, notes=?, status=? WHERE id=?");
        $stmt->bind_param("sssssssi", $customer_name, $customer_phone, $customer_email, $appointment_date, $appointment_time, $notes, $status, $id);
    }
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Cập nhật thành công']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $stmt->error]);
    }
}

// DELETE - Xóa lịch hẹn
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
    
    $stmt = $conn->prepare("DELETE FROM appointments WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Xóa thành công']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $stmt->error]);
    }

    }

$conn->close();
?>

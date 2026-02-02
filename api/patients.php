<?php
/**
 * API Quản lý Khách hàng (Patients)
 */
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once '../config.php';

$method = $_SERVER['REQUEST_METHOD'];

// Kiểm tra quyền admin cho tất cả operations
if (!isset($_SESSION['auth_user']['role']) || $_SESSION['auth_user']['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Không có quyền truy cập']);
    exit;
}

// GET - Lấy danh sách khách hàng
if ($method === 'GET') {
    if (isset($_GET['id'])) {
        // Lấy chi tiết 1 khách hàng + lịch sử đặt lịch
        $id = intval($_GET['id']);
        $stmt = $conn->prepare("SELECT * FROM patients WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($patient = $result->fetch_assoc()) {
            // Lấy lịch sử đặt lịch
            $stmt2 = $conn->prepare("SELECT * FROM appointments WHERE user_id = ? ORDER BY appointment_date DESC LIMIT 10");
            $stmt2->bind_param("i", $id);
            $stmt2->execute();
            $appointments = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
            
            $patient['appointments'] = $appointments;
            echo json_encode(['success' => true, 'patient' => $patient]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy khách hàng']);
        }
    } else {
        // Lấy tất cả khách hàng
        $where = "1=1";
        
        if (isset($_GET['search']) && $_GET['search']) {
            $search = $conn->real_escape_string($_GET['search']);
            $where .= " AND (full_name LIKE '%$search%' OR email LIKE '%$search%' OR phone LIKE '%$search%')";
        }
        
        $sql = "SELECT * FROM patients WHERE $where ORDER BY created_at DESC";
        $result = $conn->query($sql);
        
        $patients = [];
        while ($row = $result->fetch_assoc()) {
            // Đếm số lịch hẹn
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM appointments WHERE user_id = ?");
            $stmt->bind_param("i", $row['id']);
            $stmt->execute();
            $count = $stmt->get_result()->fetch_assoc()['count'];
            $row['appointment_count'] = $count;
            
            $patients[] = $row;
        }
        
        echo json_encode(['success' => true, 'patients' => $patients, 'count' => count($patients)]);
    }
}

// PUT - Cập nhật thông tin khách hàng
elseif ($method === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $id = intval($data['id'] ?? 0);
    $full_name = $conn->real_escape_string($data['full_name'] ?? '');
    $phone = $conn->real_escape_string($data['phone'] ?? '');
    $gender = $conn->real_escape_string($data['gender'] ?? 'Khác');
    $birthday = $conn->real_escape_string($data['birthday'] ?? null);
    $address = $conn->real_escape_string($data['address'] ?? '');
    
    if ($id <= 0 || empty($full_name)) {
        echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']);
        exit;
    }
    
    $stmt = $conn->prepare("UPDATE patients SET full_name=?, phone=?, gender=?, birthday=?, address=? WHERE id=?");
    $stmt->bind_param("sssssi", $full_name, $phone, $gender, $birthday, $address, $id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Cập nhật thành công']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $stmt->error]);
    }
}

// DELETE - Xóa khách hàng
elseif ($method === 'DELETE') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = intval($data['id'] ?? 0);
    
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']);
        exit;
    }
    
    $stmt = $conn->prepare("DELETE FROM patients WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Xóa thành công']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $stmt->error]);
    }
}

$conn->close();
?>

<?php
/**
 * API Quản lý Bác sĩ (Doctors)
 */
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once '../config.php';

$method = $_SERVER['REQUEST_METHOD'];

// GET - Lấy danh sách bác sĩ (public)
if ($method === 'GET') {
    if (isset($_GET['id'])) {
        $id = intval($_GET['id']);
        $stmt = $conn->prepare("SELECT id, username, full_name, role, avatar, phone, specialty, created_at FROM users WHERE id = ? AND role = 'doctor'");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($doctor = $result->fetch_assoc()) {
            echo json_encode(['success' => true, 'doctor' => $doctor]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy bác sĩ']);
        }
    } else {
        $sql = "SELECT id, username, full_name, role, avatar, phone, specialty, created_at FROM users WHERE role = 'doctor' ORDER BY full_name";
        $result = $conn->query($sql);
        
        $doctors = [];
        while ($row = $result->fetch_assoc()) {
            $doctors[] = $row;
        }
        
        echo json_encode(['success' => true, 'doctors' => $doctors, 'count' => count($doctors)]);
    }
}

// POST - Thêm bác sĩ mới
elseif ($method === 'POST') {
    if (!isset($_SESSION['auth_user']['role']) || $_SESSION['auth_user']['role'] !== 'admin') {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Không có quyền thực hiện']);
        exit;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $username = $conn->real_escape_string($data['username'] ?? '');
    $password = password_hash($data['password'] ?? '123456', PASSWORD_DEFAULT);
    $full_name = $conn->real_escape_string($data['full_name'] ?? '');
    $phone = $conn->real_escape_string($data['phone'] ?? '');
    $specialty = $conn->real_escape_string($data['specialty'] ?? '');
    $avatar = $conn->real_escape_string($data['avatar'] ?? '');
    
    if (empty($username) || empty($full_name)) {
        echo json_encode(['success' => false, 'message' => 'Vui lòng nhập đầy đủ thông tin']);
        exit;
    }
    
    // Kiểm tra username đã tồn tại chưa
    $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $check->bind_param("s", $username);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Tên đăng nhập đã tồn tại']);
        exit;
    }
    
    $stmt = $conn->prepare("INSERT INTO users (username, password, full_name, role, phone, specialty, avatar) VALUES (?, ?, ?, 'doctor', ?, ?, ?)");
    $stmt->bind_param("ssssss", $username, $password, $full_name, $phone, $specialty, $avatar);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Thêm bác sĩ thành công', 'id' => $conn->insert_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $stmt->error]);
    }
}

// PUT - Cập nhật thông tin bác sĩ
elseif ($method === 'PUT') {
    if (!isset($_SESSION['auth_user']['role']) || $_SESSION['auth_user']['role'] !== 'admin') {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Không có quyền thực hiện']);
        exit;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $id = intval($data['id'] ?? 0);
    $full_name = $conn->real_escape_string($data['full_name'] ?? '');
    $phone = $conn->real_escape_string($data['phone'] ?? '');
    $specialty = $conn->real_escape_string($data['specialty'] ?? '');
    $avatar = $conn->real_escape_string($data['avatar'] ?? '');
    
    if ($id <= 0 || empty($full_name)) {
        echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']);
        exit;
    }
    
    // Nếu có đổi mật khẩu
    if (!empty($data['password'])) {
        $password = password_hash($data['password'], PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET full_name=?, phone=?, specialty=?, avatar=?, password=? WHERE id=? AND role='doctor'");
        $stmt->bind_param("sssssi", $full_name, $phone, $specialty, $avatar, $password, $id);
    } else {
        $stmt = $conn->prepare("UPDATE users SET full_name=?, phone=?, specialty=?, avatar=? WHERE id=? AND role='doctor'");
        $stmt->bind_param("ssssi", $full_name, $phone, $specialty, $avatar, $id);
    }
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Cập nhật thành công']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $stmt->error]);
    }
}

// DELETE - Xóa bác sĩ
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
    
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'doctor'");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Xóa thành công']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $stmt->error]);
    }
}

$conn->close();
?>

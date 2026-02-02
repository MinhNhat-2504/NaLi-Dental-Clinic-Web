<?php
/**
 * admin_login.php - Đăng nhập cho Admin, Bác sĩ, Lễ tân
 */

session_start();
header('Content-Type: application/json');
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $username = $data['username'] ?? '';
    $password = $data['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Vui lòng điền đầy đủ thông tin']);
        exit;
    }
    
    // Đăng nhập cho NHÂN SỰ (bảng users)
    $stmt = $conn->prepare("SELECT id, username, full_name, password, role, specialty, phone FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Tài khoản hoặc mật khẩu không chính xác']);
        exit;
    }
    
    $user = $result->fetch_assoc();
    
    // Verify password
    if (!password_verify($password, $user['password'])) {
        echo json_encode(['success' => false, 'message' => 'Tài khoản hoặc mật khẩu không chính xác']);
        exit;
    }
    
    // Đăng nhập thành công
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['specialty'] = $user['specialty'];
    $_SESSION['phone'] = $user['phone'];

    // Đồng bộ session cho API
    $_SESSION['auth'] = true;
    $_SESSION['auth_user'] = [
        'id' => $user['id'],
        'name' => $user['full_name'],
        'username' => $user['username'],
        'role' => $user['role']
    ];

    echo json_encode([
        'success' => true,
        'message' => 'Đăng nhập thành công',
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'name' => $user['full_name'],
            'role' => $user['role'],
            'specialty' => $user['specialty']
        ]
    ]);
}

$conn->close();
?>
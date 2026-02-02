<?php
/**
 * config.php - Database Configuration
 * NALI Dental Clinic
 */

// Cấu hình kết nối Database
define('DB_HOST', 'localhost');
define('DB_PORT', '3306'); // Cổng MySQL - thay đổi nếu cần (3306 hoặc 3307)
define('DB_USER', 'root');
define('DB_PASS', ''); // Mật khẩu MySQL (thường để trống với XAMPP)
define('DB_NAME', 'nali_dental');

// Kết nối Database với port
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

// Kiểm tra kết nối
if ($conn->connect_error) {
    if (strpos($_SERVER['REQUEST_URI'], 'book_appointment.php') !== false) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Kết nối database thất bại: ' . $conn->connect_error
        ]);
        exit;
    } else {
        die("Kết nối thất bại: " . $conn->connect_error);
    }
}

// Set charset UTF-8
$conn->set_charset("utf8mb4");

// Helper functions
function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.html');
        exit;
    }
}

function requireAdmin() {
    if (!isset($_SESSION['auth_user']['role']) || $_SESSION['auth_user']['role'] !== 'admin') {
        // Always return JSON for API endpoints
        $apiEndpoints = ['products.php', 'appointments.php', 'patients.php', 'services.php'];
        $isApiCall = false;
        foreach ($apiEndpoints as $endpoint) {
            if (strpos($_SERVER['REQUEST_URI'], $endpoint) !== false) {
                $isApiCall = true;
                break;
            }
        }
        
        if ($isApiCall) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Bạn chưa đăng nhập hoặc không có quyền truy cập.'
            ]);
            exit;
        } else {
            header('Location: login.html');
            exit;
        }
    }
}
?>

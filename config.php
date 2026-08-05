<?php
/**
 * config.php - Database Configuration
 * NALI Dental Clinic
 */

// Cấu hình kết nối Database — ưu tiên biến môi trường (để chạy Docker),
// nếu không có thì dùng giá trị mặc định cho XAMPP/MySQL local.
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '3306'); // Cổng MySQL (3306/3307)
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '123456'); // Mật khẩu MySQL 8
define('DB_NAME', getenv('DB_NAME') ?: 'nali_dental');

// PHP 8.2: tắt chế độ ném exception của mysqli để đoạn kiểm tra connect_error
// bên dưới hoạt động đúng (nếu không sẽ ném Fatal error trước khi kịp xử lý).
mysqli_report(MYSQLI_REPORT_OFF);

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
        header('Location: auth.php');
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
            header('Location: auth.php');
            exit;
        }
    }
}
?>

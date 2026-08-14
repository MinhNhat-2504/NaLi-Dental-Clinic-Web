<?php
/**
 * save_feedback.php — Lưu phản hồi/đánh giá của khách từ trang contact.php.
 *
 * Trước đây file này bị THIẾU khiến form "Gửi phản hồi" báo lỗi.
 * Nhận JSON: {name, phone, email, rating, type, message}
 * Tự tạo bảng `feedback` nếu chưa có, rồi lưu và trả {success}.
 */
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once 'config.php';
require_once 'content_repository.php';
require_once 'booking_repository.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Phương thức không hợp lệ']);
    exit;
}

// Đảm bảo bảng tồn tại (an toàn nếu chưa tạo)
$conn->query("CREATE TABLE IF NOT EXISTS feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(15) NULL,
    email VARCHAR(100) NULL,
    rating TINYINT NULL,
    type VARCHAR(50) NULL,
    message TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

ensureContentSchema($conn);
ensureBookingSchema($conn);
if (!bookingAllowRequest($conn, 'feedback', 5, 3600)) { http_response_code(429); echo json_encode(['success'=>false,'message'=>'Bạn đã gửi quá nhiều phản hồi.']); exit; }
$data = json_decode(file_get_contents('php://input'), true) ?: [];
$name    = trim($data['name'] ?? '');
$phone   = trim($data['phone'] ?? '');
$email   = trim($data['email'] ?? '');
$rating  = isset($data['rating']) ? (int)$data['rating'] : null;
$type    = trim($data['type'] ?? '');
$message = trim($data['message'] ?? '');

if ($name === '' || $message === '') {
    echo json_encode(['success' => false, 'message' => 'Vui lòng nhập tên và nội dung phản hồi.']);
    exit;
}

$status = 'pending';
$stmt = $conn->prepare("INSERT INTO feedback (name, phone, email, rating, type, message, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param('sssisss', $name, $phone, $email, $rating, $type, $message, $status);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Đã ghi nhận phản hồi. Cảm ơn bạn!']);
} else {
    echo json_encode(['success' => false, 'message' => 'Lỗi lưu phản hồi: ' . $stmt->error]);
}
$stmt->close();
$conn->close();

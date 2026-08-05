<?php
/**
 * book_appointment.php — Xử lý đặt lịch hẹn từ trang contact.php.
 *
 * File này trước đây bị THIẾU khiến nút "Đặt lịch" trên web hỏng.
 * Nhận JSON: {name, phone, email, date, time, service, category, doctor, notes}
 * Ghi vào bảng `appointments` (schema phẳng) và trả {success, appointment_id}.
 *
 * Cho phép khách đặt lịch không cần đăng nhập (giống api/appointments.php).
 */
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once 'config.php';

// Chỉ nhận POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Phương thức không hợp lệ']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?: [];

// --- Lấy & làm sạch dữ liệu ---
$name     = trim($data['name'] ?? '');
$phone    = trim($data['phone'] ?? '');
$email    = trim($data['email'] ?? '');
$date     = trim($data['date'] ?? '');   // YYYY-MM-DD (input type=date)
$time     = trim($data['time'] ?? '');   // HH:MM
$service  = trim($data['service'] ?? '');
$category = trim($data['category'] ?? '');
$doctor   = trim($data['doctor'] ?? '');
$notes    = trim($data['notes'] ?? '');

// --- Kiểm tra hợp lệ ---
if ($name === '' || $phone === '' || $date === '' || $time === '') {
    echo json_encode(['success' => false, 'message' => 'Vui lòng điền đầy đủ họ tên, SĐT, ngày và giờ hẹn.']);
    exit;
}
if (!preg_match('/^0[0-9]{9}$/', $phone)) {
    echo json_encode(['success' => false, 'message' => 'Số điện thoại không hợp lệ (cần dạng 0xxxxxxxxx).']);
    exit;
}

// --- Tìm dịch vụ theo tên để lưu product_ids + giá (best-effort) ---
$product_ids = '';
$total_price = 0.0;
if ($service !== '') {
    $stmt = $conn->prepare("SELECT id, price FROM products WHERE name = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('s', $service);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $product_ids = (string) $row['id'];
            $total_price = (float) $row['price'];
        }
        $stmt->close();
    }
}

// --- Gộp thông tin phụ vào ghi chú để admin thấy đủ ---
$fullNotes = $notes;
$extra = [];
if ($category !== '') $extra[] = "Nhóm: $category";
if ($service !== '')  $extra[] = "Dịch vụ: $service";
if ($doctor !== '')   $extra[] = "Bác sĩ: $doctor";
if ($extra) {
    $fullNotes = ($notes !== '' ? $notes . ' | ' : '') . implode(' | ', $extra);
}

// --- Gắn user_id nếu khách đã đăng nhập ---
$user_id = isset($_SESSION['auth_user']['id']) ? intval($_SESSION['auth_user']['id']) : null;

// --- Ghi lịch hẹn ---
$stmt = $conn->prepare(
    "INSERT INTO appointments
        (user_id, product_ids, customer_name, customer_phone, customer_email,
         appointment_date, appointment_time, notes, payment_method, total_price, status)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'cash', ?, 'pending')"
);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống: ' . $conn->error]);
    exit;
}
$stmt->bind_param(
    'isssssssd',
    $user_id, $product_ids, $name, $phone, $email,
    $date, $time, $fullNotes, $total_price
);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'appointment_id' => $conn->insert_id]);
} else {
    echo json_encode(['success' => false, 'message' => 'Không lưu được lịch hẹn: ' . $stmt->error]);
}
$stmt->close();
$conn->close();

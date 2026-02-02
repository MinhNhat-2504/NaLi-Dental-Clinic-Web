<?php
/**
 * API Thống kê Dashboard
 */
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once '../config.php';

// Kiểm tra quyền admin
if (!isset($_SESSION['auth_user']['role']) || $_SESSION['auth_user']['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Không có quyền truy cập']);
    exit;
}

$stats = [];

// 1. Tổng số khách hàng
$result = $conn->query("SELECT COUNT(*) as count FROM patients");
$stats['total_patients'] = $result->fetch_assoc()['count'];

// 2. Khách hàng mới trong tháng
$result = $conn->query("SELECT COUNT(*) as count FROM patients WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())");
$stats['new_patients_month'] = $result->fetch_assoc()['count'];

// 3. Tổng số lịch hẹn
$result = $conn->query("SELECT COUNT(*) as count FROM appointments");
$stats['total_appointments'] = $result->fetch_assoc()['count'];

// 4. Lịch hẹn chờ xác nhận
$result = $conn->query("SELECT COUNT(*) as count FROM appointments WHERE status = 'pending'");
$stats['pending_appointments'] = $result->fetch_assoc()['count'];

// 5. Lịch hẹn hôm nay
$result = $conn->query("SELECT COUNT(*) as count FROM appointments WHERE appointment_date = CURDATE()");
$stats['today_appointments'] = $result->fetch_assoc()['count'];

// 6. Lịch hẹn đã xác nhận (sắp tới)
$result = $conn->query("SELECT COUNT(*) as count FROM appointments WHERE status = 'confirmed' AND appointment_date >= CURDATE()");
$stats['confirmed_appointments'] = $result->fetch_assoc()['count'];

// 7. Doanh thu tháng này
$result = $conn->query("SELECT COALESCE(SUM(total_price), 0) as total FROM appointments WHERE status = 'completed' AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())");
$stats['revenue_month'] = floatval($result->fetch_assoc()['total']);

// 8. Doanh thu tổng
$result = $conn->query("SELECT COALESCE(SUM(total_price), 0) as total FROM appointments WHERE status = 'completed'");
$stats['revenue_total'] = floatval($result->fetch_assoc()['total']);

// 9. Tổng số dịch vụ
$result = $conn->query("SELECT COUNT(*) as count FROM products WHERE is_active = 1");
$stats['total_products'] = $result->fetch_assoc()['count'];

// 10. Tổng số bác sĩ
$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'doctor'");
$stats['total_doctors'] = $result->fetch_assoc()['count'];

// 11. Lịch hẹn theo trạng thái
$result = $conn->query("SELECT status, COUNT(*) as count FROM appointments GROUP BY status");
$stats['appointments_by_status'] = [];
while ($row = $result->fetch_assoc()) {
    $stats['appointments_by_status'][$row['status']] = intval($row['count']);
}

// 12. Lịch hẹn 7 ngày tới
$result = $conn->query("SELECT DATE(appointment_date) as date, COUNT(*) as count FROM appointments WHERE appointment_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) GROUP BY DATE(appointment_date) ORDER BY date");
$stats['appointments_next_7_days'] = [];
while ($row = $result->fetch_assoc()) {
    $stats['appointments_next_7_days'][] = $row;
}

// 13. Doanh thu 6 tháng gần nhất
$result = $conn->query("SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COALESCE(SUM(total_price), 0) as total FROM appointments WHERE status = 'completed' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY DATE_FORMAT(created_at, '%Y-%m') ORDER BY month");
$stats['revenue_last_6_months'] = [];
while ($row = $result->fetch_assoc()) {
    $stats['revenue_last_6_months'][] = $row;
}

// 14. Top dịch vụ phổ biến (dựa trên appointments)
$result = $conn->query("SELECT product_ids, COUNT(*) as count FROM appointments WHERE product_ids IS NOT NULL AND product_ids != '' GROUP BY product_ids ORDER BY count DESC LIMIT 5");
$stats['top_services'] = [];
while ($row = $result->fetch_assoc()) {
    $stats['top_services'][] = $row;
}

echo json_encode(['success' => true, 'stats' => $stats]);

$conn->close();
?>

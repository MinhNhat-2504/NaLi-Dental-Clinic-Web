<?php
define('NALI_SECURE', true);

session_start();
// Ngăn cache để trình duyệt luôn lấy session mới nhất
header('Expires: 0');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Content-Type: application/json; charset=utf-8');

session_unset();
session_destroy();
// Xóa cookie phiên nếu có
if (ini_get('session.use_cookies')) {
	$params = session_get_cookie_params();
	setcookie(session_name(), '', time() - 42000,
		$params['path'], $params['domain'],
		$params['secure'], $params['httponly']
	);
}

// Nếu là fetch/ajax thì trả về JSON, nếu không thì redirect về index.php
if (
	(isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
	(isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
) {
	echo json_encode(['success' => true, 'message' => 'Đăng xuất thành công']);
	exit;
} else {
	header('Location: index.php');
	exit;
}
?>

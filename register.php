<?php
session_start();
include 'config.php'; // Kết nối database

$message = ""; // Biến lưu thông báo lỗi/thành công

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if ($input) {
        $fullname = mysqli_real_escape_string($conn, $input['name']);
        $email = mysqli_real_escape_string($conn, $input['email']);
        $phone = mysqli_real_escape_string($conn, $input['phone']);
        $password = $input['password'];

        header('Content-Type: application/json');
        // Kiểm tra email đã tồn tại chưa
        $check_email = "SELECT email FROM patients WHERE email='$email'";
        $check_run = mysqli_query($conn, $check_email);

        if (mysqli_num_rows($check_run) > 0) {
            echo json_encode(['success' => false, 'message' => '⚠️ Email này đã được đăng ký rồi!']);
        } else {
            // 3. Mã hóa mật khẩu và Lưu vào DB
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $query = "INSERT INTO patients (full_name, email, phone, password) VALUES ('$fullname', '$email', '$phone', '$hashed_password')";
            $query_run = mysqli_query($conn, $query);

            if ($query_run) {
                echo json_encode(['success' => true, 'message' => '✅ Đăng ký thành công! Hãy đăng nhập ngay.']);
            } else {
                echo json_encode(['success' => false, 'message' => '❌ Lỗi hệ thống: ' . mysqli_error($conn)]);
            }
        }
        exit;
    }
}

// Kiểm tra xem người dùng có bấm nút Đăng ký không
if (isset($_POST['register_btn'])) {
    // 1. Lấy dữ liệu từ form
    $fullname = mysqli_real_escape_string($conn, $_POST['fullname']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $password = $_POST['password'];
    $confirm_pass = $_POST['confirm_password'];

    // 2. Kiểm tra logic
    if ($password != $confirm_pass) {
        $message = "❌ Mật khẩu xác nhận không khớp!";
    } else {
        // Kiểm tra email đã tồn tại chưa
        $check_email = "SELECT email FROM patients WHERE email='$email'";
        $check_run = mysqli_query($conn, $check_email);

        if (mysqli_num_rows($check_run) > 0) {
            $message = "⚠️ Email này đã được đăng ký rồi!";
        } else {
            // 3. Mã hóa mật khẩu và Lưu vào DB
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $query = "INSERT INTO patients (full_name, email, phone, password) VALUES ('$fullname', '$email', '$phone', '$hashed_password')";
            $query_run = mysqli_query($conn, $query);

            if ($query_run) {
                echo "<script>alert('✅ Đăng ký thành công! Hãy đăng nhập ngay.'); window.location.href='login.php';</script>";
            } else {
                $message = "❌ Lỗi hệ thống: " . mysqli_error($conn);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đăng Ký - NALI Dental</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white text-center">
                        <h4>ĐĂNG KÝ TÀI KHOẢN</h4>
                    </div>
                    <div class="card-body">
                        <?php if($message != ""): ?>
                            <div class="alert alert-warning"><?= $message; ?></div>
                        <?php endif; ?>

                        <form action="register.php" method="POST">
                            <div class="mb-3">
                                <label>Họ và Tên</label>
                                <input type="text" name="fullname" class="form-control" required placeholder="Ví dụ: Nguyễn Văn A">
                            </div>
                            <div class="mb-3">
                                <label>Email</label>
                                <input type="email" name="email" class="form-control" required placeholder="name@example.com">
                            </div>
                            <div class="mb-3">
                                <label>Số điện thoại</label>
                                <input type="text" name="phone" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label>Mật khẩu</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label>Nhập lại Mật khẩu</label>
                                <input type="password" name="confirm_password" class="form-control" required>
                            </div>
                            
                            <button type="submit" name="register_btn" class="btn btn-primary w-100">Đăng Ký Ngay</button>
                        </form>
                        <div class="mt-3 text-center">
                            <a href="login.php">Đã có tài khoản? Đăng nhập</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
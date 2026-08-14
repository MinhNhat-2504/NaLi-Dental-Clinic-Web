<?php
session_start();
include 'config.php';

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if ($input) {
        $emailOrUser = mysqli_real_escape_string($conn, $input['email']);
        $password = $input['password'];

        // 1. Thử đăng nhập bằng email ở bảng patients
        $query = "SELECT * FROM patients WHERE email='$emailOrUser' LIMIT 1";
        $query_run = mysqli_query($conn, $query);

        header('Content-Type: application/json');
        if (mysqli_num_rows($query_run) > 0) {
            $row = mysqli_fetch_array($query_run);
            if (password_verify($password, $row['password'])) {
                $_SESSION['auth'] = true;
                $_SESSION['auth_user'] = [
                    'id' => $row['id'],
                    'name' => $row['full_name'],
                    'email' => $row['email']
                ];
                echo json_encode(['success' => true, 'patient' => ['name' => $row['full_name'], 'email' => $row['email']]]);
            } else {
                echo json_encode(['success' => false, 'message' => '❌ Sai mật khẩu rồi!']);
            }
            exit;
        }

        // 2. Nếu không có, thử đăng nhập bằng username ở bảng users (admin, bác sĩ, lễ tân)
        $query2 = "SELECT * FROM users WHERE username='$emailOrUser' LIMIT 1";
        $query_run2 = mysqli_query($conn, $query2);
        if (mysqli_num_rows($query_run2) > 0) {
            $row = mysqli_fetch_array($query_run2);
            // Debug trực tiếp ra màn hình khi sai mật khẩu
            $verify = password_verify($password, $row['password']);
            if ($verify) {
                $_SESSION['auth'] = true;
                $_SESSION['auth_user'] = [
                    'id' => $row['id'],
                    'name' => $row['full_name'],
                    'username' => $row['username'],
                    'role' => $row['role']
                ];
                echo json_encode(['success' => true, 'user' => ['name' => $row['full_name'], 'username' => $row['username'], 'role' => $row['role']]]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => '❌ Sai mật khẩu rồi!',
                    'debug' => [
                        'username_input' => $emailOrUser,
                        'password_input' => $password,
                        'hash_from_db' => $row['password'],
                        'password_verify' => $verify ? 'true' : 'false'
                    ]
                ]);
            }
            exit;
        }

        // Không tìm thấy ở cả hai bảng
        echo json_encode(['success' => false, 'message' => '❌ Email hoặc tài khoản này chưa được đăng ký!']);
        exit;
    }
}

if (isset($_POST['login_btn'])) {
    $emailOrUser = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];

    // 1. Thử đăng nhập bằng email ở bảng patients
    $query = "SELECT * FROM patients WHERE email='$emailOrUser' LIMIT 1";
    $query_run = mysqli_query($conn, $query);

    if (mysqli_num_rows($query_run) > 0) {
        $row = mysqli_fetch_array($query_run);
        if (password_verify($password, $row['password'])) {
            $_SESSION['auth'] = true;
            $_SESSION['auth_user'] = [
                'id' => $row['id'],
                'name' => $row['full_name'],
                'email' => $row['email']
            ];
            echo "<script>alert('✅ Đăng nhập thành công!'); window.location.href='index.php';</script>";
        } else {
            $message = "❌ Sai mật khẩu rồi!";
        }
    } else {
        // 2. Nếu không có, thử đăng nhập bằng username ở bảng users (admin, bác sĩ, lễ tân)
        $query2 = "SELECT * FROM users WHERE username='$emailOrUser' LIMIT 1";
        $query_run2 = mysqli_query($conn, $query2);
        if (mysqli_num_rows($query_run2) > 0) {
            $row = mysqli_fetch_array($query_run2);
            if (password_verify($password, $row['password'])) {
                $_SESSION['auth'] = true;
                $_SESSION['auth_user'] = [
                    'id' => $row['id'],
                    'name' => $row['full_name'],
                    'username' => $row['username'],
                    'role' => $row['role']
                ];
                echo "<script>alert('✅ Đăng nhập thành công!'); window.location.href='index.php';</script>";
            } else {
                $message = "❌ Sai mật khẩu rồi!";
            }
        } else {
            $message = "❌ Email hoặc tài khoản này chưa được đăng ký!";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đăng Nhập - NALI Dental</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="clinic-ui.css">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="card shadow">
                    <div class="card-header bg-success text-white text-center">
                        <h4>ĐĂNG NHẬP</h4>
                    </div>
                    <div class="card-body">
                        <?php if($message != ""): ?>
                            <div class="alert alert-danger"><?= $message; ?></div>
                        <?php endif; ?>

                        <form action="login.php" method="POST">
                            <div class="mb-3">
                                <label>Email</label>
                                <input type="email" name="email" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label>Mật khẩu</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                            
                            <button type="submit" name="login_btn" class="btn btn-success w-100">Đăng Nhập</button>
                        </form>
                        <div class="mt-3 text-center">
                            <a href="register.php">Chưa có tài khoản? Đăng ký</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

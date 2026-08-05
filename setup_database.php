<?php
/**
 * setup_database.php — Tạo & nạp dữ liệu mẫu cho database nali_dental.
 *
 * Dùng khi cần dựng lại DB từ đầu (vd trên máy chấm đồ án). Chạy:
 *   php setup_database.php        (CLI)
 *   hoặc mở http://localhost/nali/setup_database.php trên trình duyệt.
 *
 * An toàn: dùng IF NOT EXISTS, chỉ chèn dữ liệu mẫu khi bảng đang trống.
 * Schema khớp CODE THẬT (bảng products + appointments phẳng).
 */
mysqli_report(MYSQLI_REPORT_OFF);
$isCli = (php_sapi_name() === 'cli');
$nl = $isCli ? "\n" : "<br>";
function out($m){ global $nl; echo $m . $nl; }

// --- Kết nối (KHÔNG chọn DB để có thể tạo mới) — ưu tiên biến môi trường (Docker) ---
$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = (int)(getenv('DB_PORT') ?: 3306);
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '123456';
$conn = @mysqli_connect($host, $user, $pass, null, $port);
if (!$conn) { out("❌ Không kết nối được MySQL: " . mysqli_connect_error()); exit(1); }
mysqli_set_charset($conn, 'utf8mb4');
out("✅ Kết nối MySQL OK");

// --- Tạo database ---
mysqli_query($conn, "CREATE DATABASE IF NOT EXISTS nali_dental CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
mysqli_select_db($conn, 'nali_dental');
out("✅ Database nali_dental sẵn sàng");

// --- Tạo bảng (schema phẳng khớp app) ---
$ddl = [
"CREATE TABLE IF NOT EXISTS patients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(15) NOT NULL,
    gender ENUM('Nam','Nữ','Khác') DEFAULT 'Khác',
    birthday DATE NULL, address TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin','doctor','receptionist') NOT NULL DEFAULT 'doctor',
    avatar VARCHAR(255) NULL, phone VARCHAR(15) NULL, specialty VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL, description TEXT NULL,
    price DECIMAL(12,2) DEFAULT 0, image VARCHAR(255) NULL,
    target_group VARCHAR(50) DEFAULT 'adults', duration INT DEFAULT 30,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL, product_ids VARCHAR(255) NULL,
    customer_name VARCHAR(100) NOT NULL, customer_phone VARCHAR(15) NOT NULL,
    customer_email VARCHAR(100) NULL,
    appointment_date DATE NOT NULL, appointment_time TIME NOT NULL,
    notes TEXT NULL, admin_notes TEXT NULL,
    status ENUM('pending','confirmed','completed','cancelled') DEFAULT 'pending',
    payment_method ENUM('cash','transfer','card') DEFAULT 'cash',
    discount_code VARCHAR(50) NULL, discount_amount DECIMAL(12,2) DEFAULT 0,
    total_price DECIMAL(12,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];
foreach ($ddl as $sql) {
    if (!mysqli_query($conn, $sql)) { out("❌ Lỗi tạo bảng: " . mysqli_error($conn)); }
}
out("✅ Đã tạo 4 bảng: patients, users, products, appointments");

function tableEmpty($conn, $t){ $r=mysqli_query($conn,"SELECT COUNT(*) FROM `$t`"); return $r && mysqli_fetch_row($r)[0]==0; }

// --- Seed dịch vụ (products) ---
if (tableEmpty($conn, 'products')) {
    $services = [
        // name, description, price, image, target_group, duration
        ['Tẩy trắng răng Laser','Làm trắng răng bằng tia Laser an toàn, hiệu quả tức thì',2500000,'tay-trang.jpg','adults',60],
        ['Bọc răng sứ Titan','Bọc răng sứ cao cấp, bền đẹp tự nhiên lâu dài',4500000,'boc-rang-su.jpg','adults',90],
        ['Niềng răng Invisalign','Niềng răng trong suốt, thẩm mỹ, không đau',65000000,'invisalign.jpg','adults',120],
        ['Cấy ghép Implant','Trồng răng Implant công nghệ Hàn Quốc, bảo hành dài hạn',18000000,'implant.jpg','adults',120],
        ['Nhổ răng khôn','Nhổ răng khôn an toàn, không đau, tiểu phẫu nhẹ nhàng',1500000,'nho-rang-khon.jpg','adults',45],
        ['Điều trị tủy răng','Lấy tủy, điều trị viêm tủy chuyên sâu',2000000,'dieu-tri-tuy.jpg','adults',60],
        ['Trám răng thẩm mỹ','Trám răng bằng composite cao cấp, màu tự nhiên',300000,'tram-rang.jpeg','adults',30],
        ['Lấy cao răng','Làm sạch cao răng, đánh bóng, vệ sinh răng miệng',200000,'cao-voi-rang.jpg','adults',30],
        // Trẻ em
        ['Nhổ răng sữa','Nhổ răng sữa cho bé an toàn, nhẹ nhàng',150000,'nho-rang-sua.jpg','children',30],
        ['Trám răng sữa','Trám răng sâu cho bé bằng vật liệu an toàn',250000,'tram-rang-sua.jpg','children',30],
        ['Niềng răng trẻ em','Chỉnh nha sớm, định hướng răng mọc đều đẹp',35000000,'nieng-rang-tre.jpg','children',90],
        ['Bôi Fluoride ngừa sâu','Bôi Fluoride bảo vệ men răng, phòng sâu răng',200000,'fluoride.jpg','children',20],
        // Người cao tuổi
        ['Hàm giả tháo lắp','Phục hình hàm giả tháo lắp êm ái, ăn nhai tốt',6000000,'ham-gia.jpg','elderly',90],
        ['Điều trị nha chu','Điều trị viêm nha chu, chảy máu chân răng',1200000,'nha-chu.jpg','elderly',60],
        ['Tư vấn nha khoa cao tuổi','Khám & tư vấn chăm sóc răng miệng người cao tuổi',0,'tu-van-cao-tuoi.jpg','elderly',30],
    ];
    $stmt = mysqli_prepare($conn, "INSERT INTO products (name,description,price,image,target_group,duration) VALUES (?,?,?,?,?,?)");
    foreach ($services as $s) {
        mysqli_stmt_bind_param($stmt, 'ssdssi', $s[0],$s[1],$s[2],$s[3],$s[4],$s[5]);
        mysqli_stmt_execute($stmt);
    }
    out("✅ Đã thêm " . count($services) . " dịch vụ mẫu (products)");
} else { out("ℹ️ Bảng products đã có dữ liệu, bỏ qua seed"); }

// --- Seed nhân sự (admin + bác sĩ) ---
if (tableEmpty($conn, 'users')) {
    $adminPass = password_hash('admin123', PASSWORD_DEFAULT);
    $docPass   = password_hash('123456', PASSWORD_DEFAULT);
    $staff = [
        ['admin', $adminPass, 'Quản Trị Viên', 'admin', '', '0901234567'],
        ['bs_nhat', $docPass, 'BS. Trần Minh Nhật', 'doctor', 'Chỉnh nha', '0902222222'],
        ['bs_mai', $docPass, 'BS. Nguyễn Thị Mai', 'doctor', 'Cấy ghép Implant', '0903333333'],
        ['letan_lan', $docPass, 'Võ Thị Lan', 'receptionist', '', '0904444444'],
    ];
    $stmt = mysqli_prepare($conn, "INSERT INTO users (username,password,full_name,role,specialty,phone) VALUES (?,?,?,?,?,?)");
    foreach ($staff as $u) {
        mysqli_stmt_bind_param($stmt, 'ssssss', $u[0],$u[1],$u[2],$u[3],$u[4],$u[5]);
        mysqli_stmt_execute($stmt);
    }
    out("✅ Đã thêm nhân sự: admin (mật khẩu: admin123), bác sĩ/lễ tân (mật khẩu: 123456)");
} else { out("ℹ️ Bảng users đã có dữ liệu, bỏ qua seed"); }

// --- Seed khách hàng mẫu ---
if (tableEmpty($conn, 'patients')) {
    $cusPass = password_hash('password123', PASSWORD_DEFAULT);
    $patients = [
        ['Trần Thị Lan Anh','lananh@gmail.com','0912345678','Nữ'],
        ['Lê Hoàng Nam','namle@gmail.com','0923456789','Nam'],
        ['Phạm Minh Anh','anhpm@gmail.com','0934567890','Nữ'],
    ];
    $stmt = mysqli_prepare($conn, "INSERT INTO patients (full_name,email,password,phone,gender) VALUES (?,?,?,?,?)");
    foreach ($patients as $p) {
        mysqli_stmt_bind_param($stmt, 'sssss', $p[0],$p[1],$cusPass,$p[2],$p[3]);
        mysqli_stmt_execute($stmt);
    }
    out("✅ Đã thêm 3 khách hàng mẫu (mật khẩu: password123)");
} else { out("ℹ️ Bảng patients đã có dữ liệu, bỏ qua seed"); }

// --- Seed lịch hẹn mẫu ---
if (tableEmpty($conn, 'appointments')) {
    $appts = [
        ['Trần Thị Lan Anh','0912345678','lananh@gmail.com','2026-07-10','09:00:00','1',2500000,'confirmed','Muốn tư vấn thêm về quy trình'],
        ['Lê Hoàng Nam','0923456789','namle@gmail.com','2026-07-11','14:00:00','4',18000000,'pending','Quan tâm cấy ghép Implant'],
        ['Phạm Minh Anh','0934567890','anhpm@gmail.com','2026-07-12','10:30:00','5',1500000,'confirmed','Răng khôn mọc lệch, đau nhiều'],
    ];
    $stmt = mysqli_prepare($conn, "INSERT INTO appointments (customer_name,customer_phone,customer_email,appointment_date,appointment_time,product_ids,total_price,status,notes) VALUES (?,?,?,?,?,?,?,?,?)");
    foreach ($appts as $a) {
        mysqli_stmt_bind_param($stmt, 'ssssssdss', $a[0],$a[1],$a[2],$a[3],$a[4],$a[5],$a[6],$a[7],$a[8]);
        mysqli_stmt_execute($stmt);
    }
    out("✅ Đã thêm 3 lịch hẹn mẫu");
} else { out("ℹ️ Bảng appointments đã có dữ liệu, bỏ qua seed"); }

mysqli_close($conn);
out("");
out("🎉 HOÀN TẤT! Database nali_dental đã sẵn sàng.");

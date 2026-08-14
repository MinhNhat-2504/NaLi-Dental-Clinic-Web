<?php
/**
 * Kho nội dung công khai của NALI.
 *
 * Các bảng dưới đây tách nội dung có thể thay đổi khỏi mã nguồn. Hàm migration
 * chỉ tạo/cập nhật cấu trúc, không tự tạo đánh giá, chứng nhận hay tuyên bố y tế.
 */
function ensureContentSchema(mysqli $conn): void {
    static $done = false;
    if ($done) return;
    $done = true;

    $queries = [
        "CREATE TABLE IF NOT EXISTS feedback (
            id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL,
            phone VARCHAR(15) NULL, email VARCHAR(100) NULL, rating TINYINT NULL,
            type VARCHAR(50) NULL, message TEXT NULL,
            status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS faqs (
            id INT AUTO_INCREMENT PRIMARY KEY, question VARCHAR(500) NOT NULL,
            answer TEXT NOT NULL, sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS blog_posts (
            id INT AUTO_INCREMENT PRIMARY KEY, slug VARCHAR(180) NOT NULL UNIQUE,
            title VARCHAR(255) NOT NULL, excerpt TEXT NULL, content MEDIUMTEXT NULL,
            cover_image VARCHAR(255) NULL, category VARCHAR(100) NULL,
            status ENUM('draft','published') NOT NULL DEFAULT 'draft',
            published_at DATETIME NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS doctor_profiles (
            id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL UNIQUE,
            slug VARCHAR(180) NOT NULL UNIQUE, introduction TEXT NULL,
            education TEXT NULL, experience_text TEXT NULL, case_count INT NULL,
            consultation_note TEXT NULL, photo VARCHAR(255) NULL,
            is_published TINYINT(1) NOT NULL DEFAULT 0,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_doctor_profile_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS service_terms (
            id INT AUTO_INCREMENT PRIMARY KEY, product_id INT NULL UNIQUE,
            service_name VARCHAR(180) NOT NULL, price_note VARCHAR(500) NULL,
            warranty_text TEXT NULL, payment_note TEXT NULL,
            sort_order INT NOT NULL DEFAULT 0, is_active TINYINT(1) NOT NULL DEFAULT 1,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS trust_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            item_type ENUM('license','certificate','partner') NOT NULL,
            title VARCHAR(255) NOT NULL, issuer VARCHAR(255) NULL,
            credential_code VARCHAR(150) NULL, evidence_url VARCHAR(500) NULL,
            image VARCHAR(255) NULL, description TEXT NULL,
            is_verified TINYINT(1) NOT NULL DEFAULT 0,
            is_public TINYINT(1) NOT NULL DEFAULT 0,
            sort_order INT NOT NULL DEFAULT 0,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS site_settings (
            setting_key VARCHAR(100) PRIMARY KEY, setting_value TEXT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];
    foreach ($queries as $sql) { $conn->query($sql); }

    // Dự án cũ đã có feedback; chỉ thêm cột duyệt nếu chưa tồn tại.
    $feedback = $conn->query("SHOW COLUMNS FROM feedback LIKE 'status'");
    if (!$feedback || $feedback->num_rows === 0) {
        $conn->query("ALTER TABLE feedback ADD COLUMN status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending' AFTER message");
    }
}

function contentRows(mysqli $conn, string $sql): array {
    $res = $conn->query($sql);
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

function contentSetting(mysqli $conn, string $key): string {
    $stmt = $conn->prepare('SELECT setting_value FROM site_settings WHERE setting_key = ? LIMIT 1');
    $stmt->bind_param('s', $key); $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc(); $stmt->close();
    return trim((string)($row['setting_value'] ?? ''));
}

function contentCsrfToken(): string {
    if (empty($_SESSION['content_csrf'])) $_SESSION['content_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['content_csrf'];
}

function requireContentCsrf(): void {
    if (!hash_equals($_SESSION['content_csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(403); exit('Yêu cầu không hợp lệ. Vui lòng tải lại trang và thử lại.');
    }
}

function contentSlug(string $value): string {
    $value = trim(mb_strtolower($value, 'UTF-8'));
    $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
    $value = preg_replace('/[^a-z0-9]+/', '-', $value);
    return trim($value, '-') ?: ('noi-dung-' . time());
}
?>

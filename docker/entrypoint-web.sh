#!/bin/bash
# entrypoint-web.sh — Chờ MySQL sẵn sàng, seed dữ liệu, rồi chạy Apache.
set -e

echo "[web] Chờ MySQL (${DB_HOST}:${DB_PORT})..."
until php -r '
    mysqli_report(MYSQLI_REPORT_OFF);
    $c = @mysqli_connect(getenv("DB_HOST"), getenv("DB_USER"), getenv("DB_PASS"), null, (int)getenv("DB_PORT"));
    exit($c ? 0 : 1);
' 2>/dev/null; do
    sleep 2
done
echo "[web] MySQL đã sẵn sàng."

# Tạo DB + seed dữ liệu (idempotent: chỉ seed khi bảng trống)
php /var/www/html/setup_database.php || echo "[web] setup_database bỏ qua (có thể đã seed)."

echo "[web] Khởi động Apache."
exec apache2-foreground

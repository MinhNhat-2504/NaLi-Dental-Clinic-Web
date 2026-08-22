# 🚀 NALI Dental — Hướng dẫn deploy

Hai cách, chọn theo nhu cầu:

| Cách | Khi nào | Tài liệu |
|------|---------|----------|
| **Miễn phí** — Render + Aiven MySQL + GitHub Actions (bản demo đang chạy) | Đồ án, demo, phòng khám nhỏ | [`DEPLOY_FREE.md`](DEPLOY_FREE.md) (từng bước, có ảnh) |
| **VPS + tên miền riêng** — Docker Compose: MySQL + Flask/Gunicorn + Nginx + Let's Encrypt | Khi cần tên miền, HTTPS riêng, server không ngủ | Phần A bên dưới |

AI service có thể chạy chung VPS (Docker) hoặc deploy riêng miễn phí (Render theo `render.yaml`, hoặc HuggingFace Spaces — phần B).

---

## A. VPS: Docker Compose (Flask + MySQL + Nginx + HTTPS)

File liên quan: `flask_app/Dockerfile`, `docker-compose.prod.yml`, `docker-compose.https.yml`, `deploy/flask-entrypoint.sh`, `deploy/flask-nginx-*.conf.template`.
Entrypoint tự chạy `flask db upgrade` trước khi lên Gunicorn; container từ chối chạy nếu thiếu `SECRET_KEY` / `MYSQL_ROOT_PASSWORD`.

1. Trỏ bản ghi `A` của tên miền tới IP VPS, mở cổng `80` và `443`.
2. Trên VPS: `cp .env.example .env` rồi điền
   ```env
   DOMAIN=your-domain.example
   MYSQL_ROOT_PASSWORD=<mật khẩu MySQL dài và riêng>
   SECRET_KEY=<chuỗi bí mật dài, ngẫu nhiên>
   AI_SERVICE_URL=https://<địa chỉ AI service>
   MAIL_USERNAME=... MAIL_PASSWORD=... MAIL_DEFAULT_SENDER=...   # nếu muốn gửi email
   ```
3. Chạy HTTP trước để Let's Encrypt xác thực tên miền:
   ```bash
   docker compose -f docker-compose.prod.yml up -d --build
   ```
   MySQL được khởi tạo từ `nali_dental_schema_REAL.sql` (schema + dữ liệu mẫu) ở lần chạy đầu.
4. Cấp chứng chỉ (thay tên miền, email thật):
   ```bash
   docker compose -f docker-compose.prod.yml run --rm certbot certonly --webroot -w /var/www/certbot -d your-domain.example --email you@example.com --agree-tos --no-eff-email
   ```
5. Bật HTTPS:
   ```bash
   docker compose -f docker-compose.prod.yml -f docker-compose.https.yml up -d
   ```
6. Tạo admin (một lần): `docker compose -f docker-compose.prod.yml exec -e INITIAL_ADMIN_PASSWORD=<mật khẩu> web flask --app run.py seed-db`

Gia hạn chứng chỉ (cron mỗi tuần):
```bash
docker compose -f docker-compose.prod.yml run --rm certbot renew
docker compose -f docker-compose.prod.yml -f docker-compose.https.yml exec nginx nginx -s reload
```

Sao lưu DB: `scripts/backup_db.ps1` (Docker) hoặc `scripts/backup_db.bat` (XAMPP local).

---

## B. Deploy AI service riêng lên HuggingFace Spaces (miễn phí, có model finetune)

Chi tiết trong `deploy/hf-space/README.md`. Tóm tắt:
1. Upload GGUF đã finetune lên một model repo HF (`ai_service/finetune/out/nali-qwen-q4.gguf`).
2. Tạo Space kiểu *Docker*, copy `ai_service/` + 3 file trong `deploy/hf-space/`.
3. Secrets: `MODEL_REPO`, `MODEL_FILE` (+ `GEMINI_API_KEY` nếu muốn fallback, `DATABASE_URL` nếu muốn đặt lịch ghi DB).
4. Lấy URL `https://<user>-<space>.hf.space` → đặt vào `AI_SERVICE_URL` của web.

> Free tier chạy CPU nên model local chậm; bản demo hiện tại dùng Render + Gemini (xem `DEPLOY_FREE.md`).

---

## Biến môi trường

| Biến | Dùng cho | Ghi chú |
|------|----------|---------|
| `DATABASE_URL` hoặc `DB_HOST/DB_PORT/DB_USER/DB_PASS/DB_NAME` | web + ai_service | `mysql://user:pass@host:port/db?ssl-mode=REQUIRED` (Aiven) |
| `SECRET_KEY`, `APP_ENV=production` | web | bắt buộc khi production |
| `AI_SERVICE_URL` | web | địa chỉ AI service |
| `MAIL_USERNAME / MAIL_PASSWORD / MAIL_DEFAULT_SENDER` | web | Gmail App Password |
| `CRON_TOKEN` | web | bảo vệ `/api/cron/reminders` (GitHub Actions gọi) |
| `BANK_ID / BANK_ACCOUNT_NO / BANK_ACCOUNT_NAME / DEPOSIT_AMOUNT` | web | đặt cọc VietQR (trống = ẩn) |
| `GA_MEASUREMENT_ID / GOOGLE_SITE_VERIFICATION` | web | GA4 + Search Console |
| `LLM_BACKEND` | ai_service | `auto` / `local` / `gemini` / `offline` |
| `LOCAL_LLM_URL / LOCAL_LLM_MODEL` | ai_service | `http://ollama:11434/v1` / `nali-dental` |
| `GEMINI_API_KEY` | ai_service | chat (khi gemini) + AI xem ảnh răng |

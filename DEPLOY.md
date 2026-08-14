# 🚀 NALI Dental — Hướng dẫn Docker & Deploy

Ba cấp độ, chọn theo nhu cầu:

| Cấp | Mục tiêu | Lệnh chính |
|-----|----------|-----------|
| A | **Chạy full-stack bằng Docker** (máy bạn) | `docker compose up -d --build` |
| B | **Deploy AI công khai** (HF Spaces, free) | đẩy Space Docker |
| C | **Deploy web công khai** | Render/Railway/host PHP |

---

## A. Docker toàn hệ thống (local)
Cần **Docker Desktop** đang chạy.

Trước lần chạy đầu, tạo file `.env` ở thư mục gốc từ `.env.example` và đặt `MYSQL_ROOT_PASSWORD` thành mật khẩu riêng. Docker sẽ không khởi động khi biến này thiếu. Nếu đồng thời chạy Flask ngoài Docker, đặt `flask_app/.env` với `DB_PASS` đúng bằng mật khẩu MySQL mà Flask kết nối.

```bash
docker compose up -d --build
```
- Web:  http://localhost:8080
- AI (Swagger): http://localhost:8000/docs
- MySQL: cổng 3307 (tránh đụng MySQL sẵn có ở 3306)

DB tự tạo & seed khi container `web` khởi động (qua `setup_database.php`).

### Nạp LLM đã finetune vào Ollama (trong Docker)
Sau khi finetune (xem `ai_service/finetune/README.md`) và có `nali-qwen-q4.gguf`
trong `ai_service/finetune/out/`:
```bash
docker compose exec ollama ollama create nali-dental -f /models/Modelfile
```
→ `ai_service` (LLM_BACKEND=auto) sẽ tự chuyển sang dùng model NALI.
Kiểm tra: http://localhost:8000/health → `"ai_mode":"local"`.

> Chưa có model? Hệ thống vẫn chạy: tự fallback Gemini (nếu có key) hoặc offline.

---

## B. Deploy AI lên HuggingFace Spaces (MIỄN PHÍ, có URL công khai)
Chi tiết trong `deploy/hf-space/README.md`. Tóm tắt:

1. **Đưa GGUF lên HF**: tạo model repo (vd `yourname/nali-dental-gguf`), upload `nali-qwen-q4.gguf`.
   ```bash
   huggingface-cli upload yourname/nali-dental-gguf ai_service/finetune/out/nali-qwen-q4.gguf
   ```
2. **Tạo Space** kiểu *Docker*. Copy vào Space:
   - toàn bộ file trong `ai_service/` (main.py, retriever.py, tools.py, requirements.txt, ...)
   - 3 file trong `deploy/hf-space/` (`Dockerfile`, `start.sh`, `README.md`)
3. **Space → Settings → Secrets**: `MODEL_REPO`, `MODEL_FILE` (và `GEMINI_API_KEY` nếu muốn fallback).
4. Space build xong → URL: `https://<user>-<space>.hf.space`. Thử: `.../health`, `.../docs`.

> Free tier chạy CPU → chậm hơn GPU. Tư vấn (RAG) chạy tốt; đặt lịch ghi DB cần MySQL (bản A/C).

---

## C. Deploy web PHP công khai + nối tới AI
1. Deploy web (chọn 1):
   - **Render/Railway** (Docker): dùng `Dockerfile.web` + một MySQL managed (đặt biến `DB_HOST/DB_PORT/DB_USER/DB_PASS/DB_NAME`).
   - **Host PHP free** (InfinityFree, 000webhost): upload mã nguồn, tạo MySQL, sửa DB\_\* trong biến môi trường/`config.php`.
2. **Nối web ↔ AI**: đặt biến môi trường cho web
   ```
   AI_SERVICE_URL=https://<user>-<space>.hf.space
   ```
   (widget `ai_chat_widget.php` tự đọc biến này). Vậy là chatbot trên web thật gọi tới AI đã deploy.

---

## D. Deploy Flask bằng Gunicorn + Nginx + HTTPS

Các file `flask_app/Dockerfile`, `docker-compose.flask.prod.yml` và `deploy/flask-nginx-*.conf.template` là cấu hình production cho bản Flask. Cần một VPS có Docker, một tên miền và quyền quản lý DNS; không đưa mật khẩu hoặc khóa API vào Git.

1. Trỏ bản ghi `A` của tên miền tới IP công khai của VPS, mở cổng `80` và `443` trên firewall.
2. Trên VPS, sao chép `.env.example` thành `.env`, rồi điền ít nhất:

   ```env
   DOMAIN=your-domain.example
   MYSQL_ROOT_PASSWORD=<mat-khau-mysql-dai-va-rieng>
   SECRET_KEY=<chuoi-bi-mat-dai-va-ngau-nhien>
   AI_SERVICE_URL=https://<ai-service-cong-khai>
   ```

3. Khởi động HTTP để Let's Encrypt xác thực tên miền. Entrypoint Flask tự chạy `flask db upgrade` trước Gunicorn:

   ```bash
   docker compose -f docker-compose.flask.prod.yml up -d --build
   ```

4. Cấp chứng chỉ, thay `your-domain.example` và email bằng giá trị thật:

   ```bash
   docker compose -f docker-compose.flask.prod.yml run --rm certbot certonly --webroot -w /var/www/certbot -d your-domain.example --email you@example.com --agree-tos --no-eff-email
   ```

5. Bật cấu hình HTTPS và kiểm tra `https://your-domain.example`:

   ```bash
   docker compose -f docker-compose.flask.prod.yml -f docker-compose.flask.https.yml up -d
   ```

Gia hạn chứng chỉ định kỳ (cron mỗi tuần là đủ) rồi nạp lại Nginx:

```bash
docker compose -f docker-compose.flask.prod.yml run --rm certbot renew
docker compose -f docker-compose.flask.prod.yml -f docker-compose.flask.https.yml exec nginx nginx -s reload
```

---

## Biến môi trường quan trọng
| Biến | Dùng cho | Ví dụ |
|------|----------|-------|
| `LLM_BACKEND` | ai_service | `auto` / `local` / `gemini` / `offline` |
| `LOCAL_LLM_URL` | ai_service | `http://ollama:11434/v1` |
| `LOCAL_LLM_MODEL` | ai_service | `nali-dental` |
| `GEMINI_API_KEY` | ai_service | (khoá Gemini, fallback) |
| `DB_HOST/DB_PORT/DB_USER/DB_PASS/DB_NAME` | web PHP | `db` / `3306` / `root` / mật khẩu từ `.env` / `nali_dental` |
| `DB_PASSWORD` | ai_service (đọc DB) | cùng mật khẩu từ `.env` |
| `AI_SERVICE_URL` | web PHP (widget) | `https://...hf.space` |

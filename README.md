# 🦷 NALI Dental Clinic — Website phòng khám nha khoa tích hợp AI

Đây là đồ án môn **Lập trình Python nâng cao** của mình. Ý tưởng là làm một website cho phòng khám
nha khoa, nhưng thay vì dừng ở mấy chức năng cơ bản, mình muốn thử tích hợp thêm **một con chatbot AI
biết tư vấn và tự đặt lịch** — nên phần "nâng cao" của đồ án nằm ở chỗ đó.

Web chính viết bằng **Flask** (đúng yêu cầu môn học), còn phần AI mình tách ra một service Python riêng.

---

## Tính năng chính

**Phía khách hàng**
- Đăng ký / đăng nhập tài khoản
- Xem danh sách dịch vụ, có **tìm kiếm** và **phân trang**
- **Đặt lịch hẹn** online (có kiểm tra ngày/giờ hợp lệ), nhận **email xác nhận**
- Xem lịch hẹn của mình, gửi góp ý / phản hồi
- **Chatbot AI** tư vấn dịch vụ, báo giá và đặt lịch bằng hội thoại
- Giao diện có **dark mode**, slider so sánh Trước/Sau, hiệu ứng cuộn...

**Phía quản trị**
- Dashboard thống kê + biểu đồ (lịch hẹn, trạng thái)
- Quản lý dịch vụ (thêm/sửa/xóa), lịch hẹn, bệnh nhân, phản hồi
- Tìm kiếm ở từng trang quản lý

---

## Công nghệ sử dụng

- **Backend web:** Python, Flask (Blueprint + app factory), Flask-WTF, Flask-SQLAlchemy, Flask-Login, Flask-Mail, Flask-Migrate
- **Cơ sở dữ liệu:** MySQL
- **Frontend:** Jinja2, HTML/CSS/JS thuần (không dùng framework nặng)
- **Lập trình mạng:** urllib gọi API ngoài (thời tiết Open-Meteo) + tự viết REST API JSON
- **AI service:** FastAPI + RAG + LLM (Qwen2.5-3B mình tự finetune bằng QLoRA, chạy qua Ollama), có fallback offline
- **Khác:** Docker (đóng gói cả hệ thống), pytest (kiểm thử)

---

## Điểm mình tâm đắc nhất: con chatbot 🤖

Chatbot không chỉ trả lời câu hỏi cho có. Mình làm nó theo hướng **RAG** (lấy đúng dữ liệu dịch vụ/giá
của phòng khám rồi mới trả lời, tránh bịa), và cho nó **tự đặt lịch** vào database luôn — đặt xong là
thấy ngay trong trang admin.

Phần LLM mình **finetune lại model mở Qwen2.5-3B** bằng kỹ thuật QLoRA trên chính dữ liệu nha khoa,
để nó trả lời đúng giọng và hiểu nghiệp vụ của NALI hơn. Nếu không có model finetune / mất mạng thì
service tự chuyển sang chế độ offline nên demo không bao giờ "chết".

---

## Cách chạy

Cần: Python 3.11, MySQL (XAMPP hoặc MySQL 8).

**1. Chuẩn bị database**
```bash
# Tạo flask_app/.env từ flask_app/.env.example rồi điền DB_PASS.
# DB_PASS phải trùng mật khẩu MySQL đang chạy; không để trống và không dùng 123456 khi deploy.
# Local dùng AUTO_CREATE_SCHEMA=1 để tự tạo các bảng nội dung (faqs, blog_posts...) còn thiếu.
# Production: đặt AUTO_CREATE_SCHEMA=0 và chạy init-db/migration trước khi nhận traffic.
# Import DB, hoặc để Flask tự dựng:
cd flask_app
flask --app run.py init-db
flask --app run.py seed-db     # tạo dữ liệu mẫu + tài khoản admin
flask --app run.py seed-content # chèn FAQ và bài kiến thức tham khảo; không tạo đánh giá khách hàng giả
```

> Lưu ý: `seed-db` không còn dùng mật khẩu admin ghi sẵn trong code nữa — bạn phải đặt biến môi trường
> `INITIAL_ADMIN_PASSWORD` trước khi chạy, ví dụ: `INITIAL_ADMIN_PASSWORD=matkhaucuaban flask --app run.py seed-db`.

**Nâng cấp database cũ bằng migration**

Khi lấy mã mới trên một database NALI đã có sẵn, chạy migration trước khi mở ứng dụng để bổ sung cột/bảng mới (ví dụ `feedback.status`):

```bash
cd flask_app
flask --app run.py db upgrade
```

Với thay đổi model sau này, luôn tạo và áp dụng migration cùng lúc:

```bash
flask --app run.py db migrate -m "mo ta thay doi schema"
flask --app run.py db upgrade
```

Nếu khởi tạo database hoàn toàn mới bằng `init-db`, đánh dấu schema đó ở revision hiện tại để Alembic không áp lại migration cũ:

```bash
flask --app run.py db stamp head
```

**2. Chạy web Flask**
```bash
cd flask_app
pip install -r requirements.txt
python run.py                  # mở http://127.0.0.1:5000
```

**3. Chạy AI service (cho chatbot)**
```bash
cd ai_service
pip install -r requirements.txt
python main.py                 # http://127.0.0.1:8000
```
> Muốn dùng model đã finetune thì cài Ollama và nạp model `nali-dental` (xem `ai_service/finetune/README.md`).
> Muốn bật gửi email thật thì điền tài khoản Gmail vào `flask_app/.env` (xem hướng dẫn trong repo).

**Chạy test:**
```bash
cd flask_app && pytest -q
```

**Triển khai thật (VPS + tên miền)**

Mình đã chuẩn bị sẵn bộ Docker cho production: `docker-compose.flask.prod.yml` gồm MySQL,
Flask chạy qua gunicorn, nginx và certbot (HTTPS). Container tự chạy migration trước khi
khởi động, và sẽ từ chối chạy nếu thiếu các biến bắt buộc (`SECRET_KEY`, `MYSQL_ROOT_PASSWORD`...).
Các bước cụ thể xem trong `DEPLOY.md`.

---

## Cấu trúc thư mục (rút gọn)

```
flask_app/          # Web chính bằng Flask (bản được chấm)
  app/
    __init__.py     # app factory
    models.py       # model SQLAlchemy
    forms.py        # form Flask-WTF
    main / auth / booking / admin / api   # các blueprint
    templates/      # giao diện Jinja2
  tests/            # test pytest
ai_service/         # Service AI (FastAPI + RAG + LLM)
  finetune/         # script finetune Qwen2.5-3B (QLoRA)
docker-compose.yml  # đóng gói toàn hệ thống
```

*(Trong repo còn có bản web PHP ban đầu — là phiên bản giao diện gốc trước khi mình xây lại bằng Flask.)*

---

## Tài khoản demo

| Vai trò | Tài khoản | Mật khẩu |
|---------|-----------|----------|
| Admin   | `admin`   | `admin123` *(DB mẫu cũ; DB mới seed thì dùng mật khẩu bạn đặt qua `INITIAL_ADMIN_PASSWORD`)* |
| Khách   | `lananh@gmail.com` | `password123` |

---

## Ghi chú

- Model finetune (~vài GB) không đẩy lên GitHub, cần train lại bằng script trong `ai_service/finetune/`.
- File `.env` chứa thông tin nhạy cảm nên đã được bỏ qua, bạn tự tạo lại theo mẫu.

Cảm ơn thầy/cô đã xem đồ án của mình 💙

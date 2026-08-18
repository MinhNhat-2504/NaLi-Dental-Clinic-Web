# 🦷 NALI Dental Clinic — Website phòng khám nha khoa tích hợp AI

[![CI](https://github.com/MinhNhat-2504/NaLi-Dental-Clinic-Web/actions/workflows/ci.yml/badge.svg)](https://github.com/MinhNhat-2504/NaLi-Dental-Clinic-Web/actions/workflows/ci.yml)
[![Nhắc lịch](https://github.com/MinhNhat-2504/NaLi-Dental-Clinic-Web/actions/workflows/reminders.yml/badge.svg)](https://github.com/MinhNhat-2504/NaLi-Dental-Clinic-Web/actions/workflows/reminders.yml)

Đây là đồ án môn **Lập trình Python nâng cao** của mình. Ý tưởng là làm một website cho phòng khám
nha khoa mà **dùng được thật**: khách đặt lịch, chatbot AI tư vấn và tự đặt lịch, AI xem ảnh răng,
nhắc lịch qua email, hồ sơ bệnh nhân, đặt cọc giữ chỗ… và toàn bộ được **deploy miễn phí** để ai cũng vào xem được.

🌐 **Demo trực tuyến:** https://nali-dental-web.onrender.com
(AI service: https://nali-dental-ai.onrender.com/health — gói free nên lần đầu mở có thể chờ ~30–60 giây để server "thức dậy").

Web chính viết bằng **Flask** (đúng yêu cầu môn học), phần AI tách ra một service Python (FastAPI) riêng.

---

## Tính năng chính

**Phía khách hàng**
- Đăng ký / đăng nhập, xem dịch vụ (tìm kiếm + phân trang), bài viết kiến thức, đội ngũ bác sĩ
- **Đặt lịch hẹn** online: chọn khung giờ còn trống theo ngày, nhận **email xác nhận**, tự đổi/huỷ lịch (trước 4 giờ)
- **Đặt cọc giữ chỗ** qua chuyển khoản **VietQR** (QR động điền sẵn tiền + nội dung), lễ tân bấm "Đã nhận" là lịch tự xác nhận
- **Email nhắc lịch trước 24h** — tự chạy mỗi sáng 08:00 (GitHub Actions cron, không tốn tiền server)
- **Hồ sơ khám của tôi**: xem chẩn đoán, điều trị, dặn dò của bác sĩ và lịch tái khám
- **Thư viện kết quả** trước/sau với thanh trượt so sánh, lọc theo dịch vụ
- **Chatbot AI** tư vấn dịch vụ, báo giá, **tự đặt lịch** bằng hội thoại; **nhớ khách đã đăng nhập** (chào tên, nhắc lịch sắp tới, nhắc tái khám đúng theo hồ sơ, không hỏi lại tên/SĐT)
- **AI xem ảnh răng** (Gemini Vision): gửi ảnh → nhận xét sơ bộ + gợi ý dịch vụ phù hợp (kèm khuyến cáo không thay thế khám trực tiếp)
- Giao diện phong cách nha khoa lâm sàng, **dark mode**, responsive (menu ngăn kéo trên mobile), widget thời tiết

**Phía quản trị**
- Dashboard thống kê + biểu đồ; quản lý dịch vụ, lịch hẹn, bệnh nhân, phản hồi (đủ CRUD + tìm kiếm)
- **Hồ sơ bệnh nhân**: ghi kết quả khám ngay từ lịch hẹn, xem toàn bộ lịch sử của từng người
- **Ca điều trị**: upload ảnh trước/sau (tự nén, bỏ EXIF, lưu trong DB), gắn dịch vụ, nhãn "ảnh minh hoạ"
- **Chất lượng AI**: nhật ký mọi lượt chat, tỉ lệ trả lời được, độ trễ, danh sách câu bot chưa trả lời để bổ sung tri thức
- Xác nhận cọc, đánh dấu đã nhắc lịch, đổi trạng thái nhanh

---

## Công nghệ sử dụng

- **Backend web:** Python 3.11, Flask (Blueprint + app factory), Flask-WTF, Flask-SQLAlchemy, Flask-Login, Flask-Mail, Flask-Migrate (Alembic)
- **Cơ sở dữ liệu:** MySQL (local: XAMPP; production: Aiven MySQL free)
- **Frontend:** Jinja2, HTML/CSS/JS thuần (không framework nặng)
- **Lập trình mạng:** urllib gọi API ngoài (thời tiết Open-Meteo, VietQR), proxy tới AI service, tự viết REST API JSON, endpoint cron bảo vệ bằng token
- **AI service:** FastAPI + RAG (TF-IDF/embeddings) + LLM: Qwen2.5-3B **tự finetune bằng QLoRA** chạy qua Ollama (local) hoặc Gemini (cloud), có **fallback offline** bằng luật; Gemini Vision cho ảnh răng
- **Xử lý ảnh:** Pillow (nén, xoay theo EXIF rồi bỏ EXIF)
- **Kiểm thử & CI:** pytest (24 test, SQLite in-memory) + bộ test AI offline (29 kiểm tra), chạy tự động trên GitHub Actions mỗi lần push
- **Triển khai:** Render (Blueprint `render.yaml`, 2 web service free) + Aiven MySQL; Docker Compose cho VPS; Google Analytics 4 + Search Console

---

## Điểm mình tâm đắc nhất: con chatbot 🤖

Chatbot làm theo hướng **RAG**: lấy đúng dữ liệu dịch vụ/giá/thông tin phòng khám rồi mới trả lời, hạn chế bịa.
Nó **tự đặt lịch** vào database — đặt xong là thấy ngay trong trang admin.

Kiến trúc là **hybrid**: LLM lo phần tư vấn/hội thoại, còn những việc cần chính xác tuyệt đối
(đặt lịch, đọc hồ sơ bệnh nhân, ngày tái khám) đi qua **máy trạng thái xác định**, không phó mặc cho model.
Nhờ dashboard "Chất lượng AI" mình bắt được lúc bot bịa "bãi giữ xe 24/24" và bổ sung tri thức ngay.

Phần LLM mình **finetune model mở Qwen2.5-3B** bằng QLoRA trên dữ liệu nha khoa để đúng giọng NALI.
Trên cloud free không đủ RAM cho model local nên dùng Gemini; mất mạng/thiếu key thì tự chuyển offline — demo không bao giờ "chết".

---

## Cách chạy local

Cần: Python 3.11, MySQL (XAMPP hoặc MySQL 8).

**1. Database**
```bash
cd flask_app
cp .env.example .env          # rồi điền DB_PASS (trùng mật khẩu MySQL), MAIL_* nếu muốn gửi email thật
# DB mới tinh:
flask --app run.py init-db       # tạo toàn bộ bảng theo model
flask --app run.py db stamp head # đánh dấu schema đã ở bản mới nhất
# DB NALI đã có sẵn từ trước: chỉ cần  flask --app run.py db upgrade
INITIAL_ADMIN_PASSWORD=matkhaucuaban flask --app run.py seed-db   # dữ liệu mẫu + admin
flask --app run.py seed-content   # FAQ + bài kiến thức
flask --app run.py seed-cases     # 1 ca trước/sau demo (ảnh minh hoạ)
```

**2. Web Flask**
```bash
pip install -r requirements.txt
python run.py                  # http://127.0.0.1:5000
```

**3. AI service (chatbot + AI xem ảnh)**
```bash
cd ai_service
pip install -r requirements.txt
python main.py                 # http://127.0.0.1:8000
```
> Muốn dùng model finetune: cài Ollama và nạp model `nali-dental` (xem `ai_service/finetune/README.md`).
> Muốn dùng Gemini: đặt `GEMINI_API_KEY` trong `ai_service/.env`. Không có gì cả thì vẫn chạy chế độ offline.

**Test**
```bash
cd flask_app && pytest -q                       # 24 test
cd ai_service && LLM_BACKEND=offline python test_agent.py   # 29 kiểm tra offline
```

**Lệnh tiện ích:** `flask --app run.py send-reminders` (gửi email nhắc lịch ngày mai ngay lập tức).

> ⚠️ Về migration: DB dùng chung với bản PHP nên **không dùng** `flask db migrate` (autogenerate) — nó từng
> sinh lệnh xoá cột. Mọi migration trong `flask_app/migrations/versions/` đều **viết tay, idempotent**
> (chạy lại không lỗi). Thêm bảng/cột mới thì viết theo mẫu các file có sẵn rồi `flask db upgrade`.

---

## Triển khai

- **Miễn phí (đang chạy demo):** Render + Aiven + GitHub Actions — từng bước trong [`DEPLOY_FREE.md`](DEPLOY_FREE.md)
  (tạo DB, lấy key Gemini, deploy Blueprint, bật email nhắc lịch, đặt cọc VietQR, GA4/Search Console).
- **VPS + tên miền:** Docker Compose (`docker-compose.flask.prod.yml`: MySQL, gunicorn, nginx, certbot) — xem [`DEPLOY.md`](DEPLOY.md).

Biến môi trường chính (web): `DATABASE_URL`, `SECRET_KEY`, `AI_SERVICE_URL`, `MAIL_USERNAME/MAIL_PASSWORD/MAIL_DEFAULT_SENDER`,
`CRON_TOKEN` (nhắc lịch), `BANK_ID/BANK_ACCOUNT_NO/BANK_ACCOUNT_NAME` (đặt cọc; trống = ẩn), `GA_MEASUREMENT_ID`, `GOOGLE_SITE_VERIFICATION`.
AI service: `GEMINI_API_KEY`, `LLM_BACKEND` (auto|local|gemini|offline), `DATABASE_URL`.

---

## Cấu trúc thư mục (rút gọn)

```
flask_app/                 # Web chính bằng Flask (bản được chấm)
  app/
    __init__.py            # app factory + lệnh CLI (init-db, seed-*, send-reminders)
    models.py              # Patient, Staff, Product, Appointment, Feedback, FAQ, BlogPost,
                           # ChatLog, MedicalRecord, CaseStudy
    forms.py               # form Flask-WTF
    main.py / auth.py / booking.py / admin.py / api.py   # các blueprint
    reminders.py           # email nhắc lịch trước 24h
    imaging.py             # nén ảnh ca điều trị
    templates/, static/    # giao diện Jinja2 + CSS/JS
  migrations/              # Alembic (viết tay)
  tests/                   # pytest
ai_service/                # FastAPI: RAG + LLM (local/Gemini/offline) + tool đặt lịch + vision
  finetune/                # script finetune Qwen2.5-3B (QLoRA)
.github/workflows/         # ci.yml (test), reminders.yml (cron nhắc lịch 08:00)
render.yaml                # deploy free trên Render
docker-compose*.yml        # đóng gói cho VPS
```

*(Trong repo còn bản web PHP ban đầu — phiên bản giao diện gốc trước khi mình xây lại bằng Flask; hai bản dùng chung DB.)*

---

## Tài khoản demo

| Vai trò | Tài khoản | Mật khẩu |
|---------|-----------|----------|
| Admin   | `admin`   | mật khẩu đặt qua `INITIAL_ADMIN_PASSWORD` khi seed |
| Khách   | `lananh@gmail.com` | `password123` |

---

## Ghi chú

- Model finetune (~vài GB) không đẩy lên GitHub, train lại bằng script trong `ai_service/finetune/`.
- File `.env` chứa thông tin nhạy cảm nên đã bỏ qua khỏi git, tự tạo lại theo `.env.example`.
- Ảnh trong "Kết quả điều trị" hiện là **ảnh minh hoạ** (có nhãn); ảnh ca thật chỉ đăng khi khách đồng ý.
- AI xem ảnh răng chỉ là **nhận xét sơ bộ**, không thay thế khám trực tiếp — web luôn hiển thị khuyến cáo này.

Cảm ơn thầy/cô đã xem đồ án của mình 💙

# NALI Dental Clinic

[![CI](https://github.com/MinhNhat-2504/NaLi-Dental-Clinic-Web/actions/workflows/ci.yml/badge.svg)](https://github.com/MinhNhat-2504/NaLi-Dental-Clinic-Web/actions/workflows/ci.yml)

Website phòng khám nha khoa, làm cho môn Lập trình Python nâng cao. Lúc đầu chỉ định làm
mấy chức năng cơ bản cho đủ bài, nhưng làm tới đâu lại muốn thêm tới đó, nên bây giờ nó
thành một trang khá đầy đủ: khách đặt lịch, đặt cọc, nhận email nhắc lịch, xem hồ sơ khám,
có chatbot tư vấn và một phần AI xem ảnh răng. Tất cả đang chạy thật trên mạng, không tốn
đồng nào.

Demo: https://nali-dental-web.onrender.com
(server gói free nên lần đầu mở có thể phải chờ nửa phút cho nó thức dậy)

Web viết bằng Flask. Phần AI tách ra một service riêng bằng FastAPI, vì mình không muốn
chatbot có lỗi là kéo cả web theo.

## Có gì trong đó

Phía khách:

- Đăng ký, đăng nhập, xem dịch vụ (có tìm kiếm, phân trang), bài viết kiến thức, bác sĩ.
- Đặt lịch online theo khung giờ còn trống, nhận email xác nhận, tự đổi hoặc huỷ lịch trước 4 tiếng.
- Đặt cọc giữ chỗ bằng chuyển khoản: web tạo mã QR VietQR điền sẵn số tiền và nội dung,
  lễ tân bấm "đã nhận" là lịch tự chuyển sang đã xác nhận.
- Email nhắc lịch trước một ngày, chạy tự động mỗi sáng 8 giờ.
- Hồ sơ khám: xem lại bác sĩ chẩn đoán gì, điều trị gì, dặn gì, khi nào tái khám.
- Thư viện ảnh trước/sau có thanh trượt so sánh, lọc theo dịch vụ.
- Chatbot tư vấn dịch vụ, báo giá, đặt lịch luôn bằng chat. Đăng nhập rồi thì nó nhớ mình,
  chào đúng tên, nhắc lịch sắp tới, không hỏi lại tên số điện thoại nữa.
- Gửi ảnh răng cho AI xem, nó nhận xét sơ bộ và gợi ý dịch vụ (có ghi rõ không thay khám thật).
- Giao diện có dark mode, chạy ổn trên điện thoại.

Phía quản trị:

- Dashboard thống kê, quản lý dịch vụ, lịch hẹn, bệnh nhân, phản hồi.
- Ghi hồ sơ khám ngay từ lịch hẹn, xem lịch sử từng bệnh nhân.
- Đăng ca điều trị trước/sau (ảnh được nén và lưu trong database).
- Trang "Chất lượng AI": log mọi câu chat, câu nào bot không trả lời được thì hiện lên để
  mình bổ sung dữ liệu.

## Công nghệ

- Python 3.11, Flask (app factory, blueprint), Flask-SQLAlchemy, Flask-WTF, Flask-Login,
  Flask-Mail, Flask-Migrate.
- MySQL. Local thì XAMPP, trên mạng thì Aiven (gói free).
- Giao diện Jinja2 với HTML, CSS, JS thuần.
- AI service: FastAPI, RAG (TF-IDF), LLM chạy được 3 kiểu: Qwen2.5-3B tự finetune bằng QLoRA
  qua Ollama, Gemini, hoặc chế độ offline bằng luật. Xem ảnh răng dùng Gemini Vision.
- Pillow để nén ảnh.
- pytest (24 test, dùng SQLite trong bộ nhớ) và một bộ test riêng cho AI (28 kiểm tra).
  GitHub Actions chạy tự động mỗi lần push.
- Deploy: Render (2 web service free) + Aiven MySQL. Có thêm Docker Compose nếu muốn chạy
  trên VPS riêng. Email nhắc lịch và việc giữ server thức cũng chạy bằng GitHub Actions.

## Về con chatbot

Đây là phần mình dành nhiều thời gian nhất. Ý chính là bot không được bịa: trước khi trả lời
nó đọc dữ liệu dịch vụ, giá, thông tin phòng khám trong database rồi mới nói (RAG). Những việc
cần đúng tuyệt đối như ghi lịch hẹn hay đọc hồ sơ bệnh nhân thì không để model tự quyết mà đi
qua một đoạn code cố định, hỏi từng bước rồi mới lưu. Model thì mình finetune Qwen2.5-3B cho
đúng giọng phòng khám; trên cloud free không đủ RAM nên bản demo dùng Gemini, còn khi không có
mạng hay thiếu key thì tự chuyển sang chế độ offline để demo không bị đứng.

Cái trang "Chất lượng AI" ở admin thực ra là do một lần mình phát hiện bot bịa ra chuyện
"bãi giữ xe 24/24", nên làm luôn chỗ để soi lại các câu nó trả lời.

## Chạy trên máy

Cần Python 3.11 và MySQL.

Database:

```bash
cd flask_app
cp .env.example .env          # điền DB_PASS, thêm MAIL_* nếu muốn gửi email thật
flask --app run.py init-db       # database mới: tạo bảng
flask --app run.py db stamp head # rồi đánh dấu đã ở migration mới nhất
# database cũ đã có từ trước thì chỉ cần: flask --app run.py db upgrade
INITIAL_ADMIN_PASSWORD=matkhau flask --app run.py seed-db   # dữ liệu mẫu + tài khoản admin
flask --app run.py seed-content   # FAQ, bài viết
flask --app run.py seed-cases     # một ca trước/sau demo
```

Web:

```bash
pip install -r requirements.txt
python run.py        # http://127.0.0.1:5000
```

AI service:

```bash
cd ai_service
pip install -r requirements.txt
python main.py       # http://127.0.0.1:8000
```

Muốn dùng model finetune thì cài Ollama và nạp model `nali-dental` (xem `ai_service/finetune/README.md`).
Muốn dùng Gemini thì đặt `GEMINI_API_KEY` trong `ai_service/.env`. Không có gì thì vẫn chạy offline.

Test:

```bash
cd flask_app && pytest -q
cd ai_service && LLM_BACKEND=offline python test_agent.py
```

Một lưu ý về migration: mình không dùng `flask db migrate` tự sinh nữa. Database demo có vài cột
ngoài model (di sản từ bản cũ), autogenerate từng sinh ra lệnh xoá cột và mình suýt mất dữ liệu.
Các file trong `flask_app/migrations/versions/` đều viết tay và chạy lại không lỗi; thêm bảng
hay cột mới thì viết theo mẫu có sẵn rồi `flask db upgrade`.

## Deploy

- Miễn phí, đúng như bản demo đang chạy: xem `DEPLOY_FREE.md`, có từng bước (tạo DB Aiven,
  lấy key Gemini, deploy Render, bật email nhắc lịch, đặt cọc, Google Analytics).
- VPS và tên miền riêng với Docker Compose: xem `DEPLOY.md`.

Biến môi trường chính của web: `DATABASE_URL`, `SECRET_KEY`, `AI_SERVICE_URL`, `MAIL_USERNAME`,
`MAIL_PASSWORD`, `MAIL_DEFAULT_SENDER`, `CRON_TOKEN`, `BANK_ID`, `BANK_ACCOUNT_NO`,
`BANK_ACCOUNT_NAME`, `GA_MEASUREMENT_ID`, `GOOGLE_SITE_VERIFICATION`.
Của AI service: `GEMINI_API_KEY`, `LLM_BACKEND`, `DATABASE_URL`.

## Cấu trúc thư mục

```
flask_app/            web Flask
  app/
    __init__.py       app factory, các lệnh CLI
    models.py         Patient, Staff, Product, Appointment, Feedback, FAQ, BlogPost,
                      ChatLog, MedicalRecord, CaseStudy
    forms.py
    main.py auth.py booking.py admin.py api.py     các blueprint
    reminders.py      email nhắc lịch
    imaging.py        nén ảnh
    cache.py          cache nhỏ trong tiến trình
    templates/ static/
  migrations/         Alembic, viết tay
  tests/
ai_service/           FastAPI: RAG, LLM, tool đặt lịch, xem ảnh
  finetune/           script finetune Qwen
.github/workflows/    ci.yml, reminders.yml, keepalive.yml
render.yaml           deploy Render
docker-compose.prod.yml, docker-compose.https.yml   cho VPS
```

## Tài khoản demo

| Vai trò | Tài khoản | Mật khẩu |
|---|---|---|
| Admin | admin | đặt qua `INITIAL_ADMIN_PASSWORD` lúc seed |
| Khách | lananh@gmail.com | password123 |

## Ghi chú

- Model finetune nặng vài GB nên không đẩy lên đây, train lại bằng script trong `ai_service/finetune/`.
- File `.env` không commit, tự tạo theo `.env.example`.
- Ảnh trong mục kết quả điều trị hiện là ảnh minh hoạ có ghi nhãn. Ảnh ca thật chỉ đăng khi khách đồng ý.
- AI xem ảnh răng chỉ là nhận xét sơ bộ, không thay cho việc đi khám.

Trịnh Ngọc Minh Nhật

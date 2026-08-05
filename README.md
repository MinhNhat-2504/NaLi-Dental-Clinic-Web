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
# Import DB, hoặc để Flask tự dựng:
cd flask_app
flask --app run.py init-db
flask --app run.py seed-db     # tạo dữ liệu mẫu + tài khoản admin
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
| Admin   | `admin`   | `admin123` |
| Khách   | `lananh@gmail.com` | `password123` |

---

## Ghi chú

- Model finetune (~vài GB) không đẩy lên GitHub, cần train lại bằng script trong `ai_service/finetune/`.
- File `.env` chứa thông tin nhạy cảm nên đã được bỏ qua, bạn tự tạo lại theo mẫu.

Cảm ơn thầy/cô đã xem đồ án của mình 💙

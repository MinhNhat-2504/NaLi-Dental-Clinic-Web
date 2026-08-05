# BÁO CÁO ĐỒ ÁN MÔN HỌC — LẬP TRÌNH PYTHON NÂNG CAO

> **Cách dùng:** File này là nội dung báo cáo hoàn chỉnh (đã điền theo dự án NALI), bám đúng
> cấu trúc mẫu của cô. Copy từng mục vào file `Mau_Bao_Cao_DoAn_LapTrinhPythonNangCao.docx`,
> chèn ảnh chụp màn hình vào mục 4, rồi Update Field mục lục. Chỗ `[...]` là bạn tự điền.

---

## Trang bìa (điền)
- **Đề tài:** Website Phòng khám Nha khoa NALI tích hợp Trợ lý AI (LLM finetune)
- **Giảng viên hướng dẫn:** ThS. Nguyễn Thị Mỹ Linh
- **Nhóm thực hiện:** Nhóm [...] — [MSSV, Họ tên từng thành viên]

---

## 1. GIỚI THIỆU ĐỀ TÀI

### 1.1. Mô tả bài toán
Phòng khám nha khoa truyền thống quản lý lịch hẹn thủ công (điện thoại, sổ giấy) dẫn đến trùng lịch,
khó tra cứu và khách hàng phải chờ tư vấn trong giờ hành chính. Đề tài xây dựng **website quản lý phòng
khám nha khoa NALI** bằng **Flask**, cho phép: khách hàng xem dịch vụ, đăng ký/đăng nhập, **đặt lịch hẹn
trực tuyến**; quản trị viên quản lý dịch vụ và lịch hẹn. Điểm nhấn: tích hợp **Trợ lý AI (mô hình ngôn ngữ
lớn được finetune)** để tư vấn và hỗ trợ đặt lịch 24/7.
- **Đối tượng người dùng:** Khách (xem dịch vụ), Thành viên (đặt lịch, xem lịch của mình), Quản trị (CRUD, thống kê).

### 1.2. Mục tiêu và phạm vi
- **Mục tiêu:** Ứng dụng web Flask hoàn chỉnh với đầy đủ chức năng bắt buộc — đăng nhập/đăng ký,
  CRUD dịch vụ, **tìm kiếm**, **phân trang**; template Jinja2, form Flask-WTF, CSDL Flask-SQLAlchemy,
  gửi email Flask-Mail; và yếu tố lập trình mạng (urllib + JSON API).
- **Chức năng mở rộng của nhóm:** Chatbot AI dùng LLM (Qwen2.5-3B) tự finetune, đóng gói Docker.
- **Phạm vi:** Nghiệp vụ phòng khám nha khoa (dịch vụ, lịch hẹn, khách hàng); không bao gồm thanh toán online thực tế, hồ sơ bệnh án chi tiết.

### 1.3. Công nghệ sử dụng
| Nhóm | Công nghệ |
|---|---|
| Ngôn ngữ | Python 3.11 |
| Web framework | Flask 3.1, app factory + Blueprint |
| Template | Jinja2 (kế thừa `base.html`) |
| Form | Flask-WTF 1.2 + WTForms (validator, CSRF) |
| CSDL | Flask-SQLAlchemy 3.1 + MySQL (PyMySQL) |
| Xác thực | Flask-Login + bcrypt |
| Email | Flask-Mail (SMTP) |
| Lập trình mạng | urllib + json (gọi API, REST JSON) |
| Giao diện | HTML5, CSS3 (responsive, dark mode), Chart.js |
| AI (mở rộng) | LLM Qwen2.5-3B finetune (QLoRA), FastAPI, Ollama, RAG |
| Khác | Git/GitHub, Docker, Bootstrap-like custom CSS |

### 1.4. Phân công công việc trong nhóm
| Thành viên | Nhiệm vụ chính | Đóng góp |
|---|---|---|
| [SV1] | Models, database, blueprint admin (CRUD), báo cáo | [..]% |
| [SV2] | Auth, booking, forms, email, templates | [..]% |
| [SV3] | Chức năng AI mở rộng (finetune, chatbot), lập trình mạng, kiểm thử | [..]% |

---

## 2. PHÂN TÍCH VÀ THIẾT KẾ HỆ THỐNG

### 2.1. Chức năng của hệ thống
**Khách (chưa đăng nhập):** xem trang chủ, danh sách dịch vụ (tìm kiếm + phân trang), chi tiết dịch vụ,
đội ngũ bác sĩ, giới thiệu, liên hệ (bản đồ), chat với Trợ lý AI.
**Thành viên (đã đăng nhập):** đặt lịch hẹn, xem "Lịch hẹn của tôi".
**Quản trị (role=admin):** dashboard thống kê (biểu đồ), CRUD dịch vụ, quản lý trạng thái lịch hẹn.
> *Hình 2.1 — Sơ đồ Use Case* (vẽ 3 actor: Khách, Thành viên, Admin, kèm các use case trên).
> **Chức năng bắt buộc:** ✅ đăng nhập/đăng ký, ✅ CRUD (dịch vụ), ✅ tìm kiếm, ✅ phân trang.

### 2.2. Thiết kế cơ sở dữ liệu
CSDL MySQL `nali_dental`. Các model SQLAlchemy chính:

**Bảng 2.1. Mô tả các model**
| Model / Bảng | Cột chính | Khóa | Quan hệ / ghi chú |
|---|---|---|---|
| `Patient` (patients) | id, full_name, email, password, phone, gender | id (PK), email (unique) | Khách hàng đăng nhập; 1-n với Appointment |
| `Staff` (users) | id, username, password, full_name, role, specialty | id (PK) | Nhân sự: admin/doctor/receptionist |
| `Product` (products) | id, name, description, price, image, target_group, duration, is_active | id (PK) | Dịch vụ nha khoa |
| `Appointment` (appointments) | id, user_id, product_ids, customer_name, customer_phone, appointment_date, appointment_time, status, total_price | id (PK) | Lịch hẹn; user_id → patients.id |
| `Feedback` (feedback) | id, name, phone, email, rating, type, message | id (PK) | Phản hồi khách |

> *Hình 2.2 — Sơ đồ quan hệ (ERD):* Patient (1) — (n) Appointment (n) — (1) Product; Staff quản lý.

### 2.3. Thiết kế giao diện
- Kế thừa template: `base.html` (header, footer, chatbot, dark-mode) → các trang con (`main/`, `auth/`, `booking/`, `admin/`).
- Luồng màn hình: Trang chủ → Dịch vụ (tìm kiếm/phân trang) → Chi tiết → Đặt lịch (yêu cầu đăng nhập) → Lịch hẹn của tôi.
- Giao diện responsive, có **dark mode**, hiệu ứng cuộn, slider Trước/Sau.
> *Hình 2.3 — Wireframe:* trang chủ, trang dịch vụ, trang đặt lịch.

---

## 3. XÂY DỰNG ỨNG DỤNG VỚI FLASK

### 3.1. Môi trường và cấu trúc dự án
Tạo virtual environment và cài đặt: `python -m venv .venv && pip install -r requirements.txt`.
Cấu trúc theo **Large Application Structure** (app factory + blueprint):
```
flask_app/
├── run.py                # điểm khởi chạy
├── config.py             # cấu hình (class Config, đọc .env)
├── requirements.txt
└── app/
    ├── __init__.py       # create_app() — app factory
    ├── extensions.py     # db, login_manager, mail, csrf
    ├── models.py         # Patient, Staff, Product, Appointment, Feedback
    ├── forms.py          # LoginForm, RegisterForm, AppointmentForm, ProductForm...
    ├── auth.py / main.py / booking.py / admin.py / api.py   # các Blueprint
    ├── templates/        # Jinja2 (base.html + trang con)
    └── static/           # css, js, images
```
```python
# app/__init__.py — app factory
def create_app(config_class=Config):
    app = Flask(__name__)
    app.config.from_object(config_class)
    db.init_app(app); login_manager.init_app(app); mail.init_app(app); csrf.init_app(app)
    app.register_blueprint(main_bp); app.register_blueprint(auth_bp); ...
    return app
```

### 3.2. Routes và xử lý nghiệp vụ
Dùng Blueprint tách theo chức năng. Đăng nhập kiểm tra cả khách (email) lẫn nhân sự (username):
```python
@auth_bp.route("/dang-nhap", methods=["GET", "POST"])
def login():
    form = LoginForm()
    if form.validate_on_submit():
        user = Patient.query.filter_by(email=form.email.data).first() \
               or Staff.query.filter_by(username=form.email.data).first()
        if user and user.check_password(form.password.data):
            login_user(user, remember=form.remember.data)
            return redirect(url_for("main.index"))
    return render_template("auth/login.html", form=form)
```
Phân quyền admin bằng decorator tùy biến `@admin_required` (chỉ role='admin' mới vào `/admin`).

### 3.3. Templates với Jinja2
Kế thừa `{% extends 'base.html' %}`, dùng macro tái sử dụng thẻ dịch vụ, vòng lặp/điều kiện, và **phân trang**:
```jinja
{% for p in services %}{{ service_card(p) }}{% endfor %}
{% for n in pagination.iter_pages() %}
  <a href="{{ url_for('main.services', q=q, page=n) }}" class="{{ 'active' if n==pagination.page }}">{{ n }}</a>
{% endfor %}
```

### 3.4. Web Forms với Flask-WTF
Khai báo form kế thừa `FlaskForm`, có validator và **CSRF tự động**:
```python
class RegisterForm(FlaskForm):
    email = StringField("Email", validators=[DataRequired(), Email()])
    phone = StringField("SĐT", validators=[DataRequired(), Regexp(r"^0\d{9}$")])
    password = PasswordField("Mật khẩu", validators=[DataRequired(), Length(6, 100)])
    confirm  = PasswordField("Nhập lại", validators=[EqualTo("password")])
```
Form dùng cho: đăng nhập, đăng ký, đặt lịch, thêm/sửa dịch vụ, tìm kiếm.

### 3.5. Cơ sở dữ liệu với Flask-SQLAlchemy
Khai báo model, thao tác CRUD (query / add / commit / delete). Ví dụ thêm dịch vụ:
```python
p = Product(name=form.name.data, price=form.price.data, ...)
db.session.add(p); db.session.commit()
```
Tìm kiếm + phân trang:
```python
query = Product.query.filter(Product.name.like(f"%{q}%"))
pagination = query.paginate(page=page, per_page=6, error_out=False)
```

### 3.6. Gửi email với Flask-Mail
Sau khi đặt lịch, gửi email xác nhận (cấu hình SMTP qua biến môi trường; nếu chưa cấu hình thì bỏ qua an toàn):
```python
msg = Message("Xác nhận đặt lịch tại NALI", recipients=[appt.customer_email])
msg.body = f"Mã lịch hẹn #{appt.id}, ngày {appt.appointment_date} {appt.appointment_time}..."
mail.send(msg)
```

### 3.7. Lập trình mạng và tích hợp API (CLO3)
Ứng dụng thể hiện đủ 3 khía cạnh lập trình mạng:
1. **Cung cấp REST API JSON nội bộ:** `/api/services` trả JSON danh sách dịch vụ.
2. **Đọc JSON từ API PUBLIC bên ngoài:** `/api/weather` dùng `urllib` gọi Open-Meteo (không cần key)
   lấy thời tiết TP.HCM, hiển thị trên trang Liên hệ:
```python
url = "https://api.open-meteo.com/v1/forecast?latitude=10.82&longitude=106.63&current=temperature_2m,weather_code"
with urllib.request.urlopen(url, timeout=8) as resp:
    data = json.loads(resp.read().decode("utf-8"))
return jsonify({"temp": data["current"]["temperature_2m"], ...})
```
3. **Proxy tới AI service:** `/api/chat` dùng `urllib` gửi POST + đọc JSON từ FastAPI (chatbot LLM).

### 3.8. Chức năng mở rộng của nhóm (điểm cộng sáng tạo)
- **Chatbot AI với LLM tự finetune:** dùng **Qwen2.5-3B** finetune bằng **QLoRA** trên tập dữ liệu Q&A
  nha khoa NALI; phục vụ qua **Ollama** (OpenAI-compatible). Kỹ thuật **RAG** (tìm kiếm ngữ nghĩa
  embeddings/TF-IDF) giúp trả lời đúng giá & thông tin thật; **tool-calling** hỗ trợ đặt lịch.
- **Kiến trúc hybrid nhiều tầng dự phòng:** LLM tự host → Gemini → offline (không bao giờ "chết" khi demo).
- **Dashboard biểu đồ** (Chart.js) + **dark mode** + **slider Trước/Sau** + **Google Maps chi nhánh**.
- **Đóng gói Docker** (docker-compose: web + MySQL + AI service + Ollama).

---

## 4. KẾT QUẢ VÀ KIỂM THỬ

### 4.1. Kết quả giao diện
Chèn 4–6 ảnh chụp: (Hình 4.1) Trang chủ; (4.2) Danh sách dịch vụ + tìm kiếm + phân trang;
(4.3) Đăng nhập/đăng ký; (4.4) Đặt lịch; (4.5) Dashboard admin + biểu đồ; (4.6) Chatbot AI.

### 4.2. Kiểm thử chức năng
**Bảng 4.1. Kết quả kiểm thử**
| TT | Chức năng / kịch bản | Dữ liệu thử | Kết quả | Đánh giá |
|---|---|---|---|---|
| 1 | Đăng ký tài khoản mới | email mới, SĐT hợp lệ | Tạo tài khoản, đăng nhập | Đạt |
| 2 | Đăng nhập sai mật khẩu | admin / sai | Báo lỗi, không vào | Đạt |
| 3 | Tìm kiếm dịch vụ | q="implant" | Lọc đúng kết quả | Đạt |
| 4 | Phân trang | page=2 | Hiển thị trang 2 | Đạt |
| 5 | Đặt lịch (đã đăng nhập) | tên, SĐT, ngày, giờ | Ghi DB, gửi email, mã #14 | Đạt |
| 6 | CRUD dịch vụ (admin) | thêm/sửa/xóa | Cập nhật DB | Đạt |
| 7 | Đổi trạng thái lịch hẹn | pending→confirmed | Cập nhật thành công | Đạt |
| 8 | API /api/services | GET | Trả JSON 15 dịch vụ | Đạt |
| 9 | Chatbot hỏi giá | "Tẩy trắng bao nhiêu?" | Trả lời đúng 2.500.000đ (RAG) | Đạt |
| 10 | Chặn truy cập admin khi chưa đăng nhập | GET /admin | Redirect đăng nhập | Đạt |

---

## 5. KẾT LUẬN VÀ HƯỚNG PHÁT TRIỂN
Nhóm đã hoàn thành ứng dụng web Flask đầy đủ chức năng bắt buộc (đăng nhập/đăng ký, CRUD, tìm kiếm,
phân trang) với Jinja2, Flask-WTF, Flask-SQLAlchemy, Flask-Mail và yếu tố lập trình mạng (urllib/JSON/API).
Điểm nổi bật là **Trợ lý AI dùng LLM tự finetune** và **đóng gói Docker** — thể hiện năng lực Python nâng cao.
**Kỹ năng đạt được:** kiến trúc Flask module hóa, ORM, bảo mật (CSRF, băm mật khẩu), lập trình mạng, và
kỹ thuật AI (QLoRA, RAG). **Hạn chế:** email cần cấu hình SMTP thật; LLM chạy CPU còn chậm.
**Hướng phát triển:** thanh toán trực tuyến, triển khai hosting công khai (đã có sẵn cấu hình Docker/HF Spaces),
tối ưu tốc độ LLM (GPU/lượng tử hóa), bổ sung hồ sơ bệnh án điện tử.

---

## TÀI LIỆU THAM KHẢO
[1] M. Grinberg, *Flask Web Development*, 2nd ed. O'Reilly, 2018.
[2] Pallets Projects, "Flask Documentation," flask.palletsprojects.com. [Truy cập: ...].
[3] "Flask-SQLAlchemy / Flask-WTF / Flask-Login / Flask-Mail Documentation." [Truy cập: ...].
[4] Qwen Team, "Qwen2.5 Technical Report," 2024. [Truy cập: ...].
[5] Unsloth, "QLoRA Finetuning Documentation." [Truy cập: ...].

## PHỤ LỤC
- **A. Mã nguồn & cách chạy:** Link GitHub [...]. Chạy: `cd flask_app` → tạo venv → `pip install -r requirements.txt`
  → `python run.py` → mở http://127.0.0.1:5000. Tài khoản demo: admin/admin123 (quản trị), lananh@gmail.com/password123 (khách).
- **B. Dữ liệu mẫu:** `flask init-db` + `flask seed-db` (dựng bảng & seed từ phía Flask), hoặc dùng chung DB với bản PHP.
- **B'. Kiểm thử tự động (CLO5):** `cd flask_app && pytest -q` — 10 test (auth, phân quyền admin, tìm kiếm, phân trang, API, 404) đều Đạt.
- **C. Chức năng AI:** hướng dẫn finetune ở `ai_service/finetune/README.md`; đóng gói Docker ở `DEPLOY.md`.

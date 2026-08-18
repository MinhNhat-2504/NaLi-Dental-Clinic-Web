# 🆓 Deploy NALI hoàn toàn MIỄN PHÍ (Render + Aiven)

Không tốn đồng nào. Kết quả: 1 link công khai kiểu `https://nali-dental-web.onrender.com`
chạy web Flask + chatbot AI + AI xem ảnh răng + email thật.

> Giới hạn của gói free (chấp nhận được để demo/khoe): service **ngủ sau 15 phút** không ai
> vào, lần đầu mở lại chờ **30–60 giây**. Không chạy được LLM finetune local (không đủ RAM),
> chatbot dùng Gemini + RAG — vẫn trả lời đúng dữ liệu phòng khám.

---

## Bước 1 — Tạo MySQL miễn phí (Aiven) — 5 phút
Render không có MySQL free, nên dùng Aiven (5GB, không hết hạn).

1. Vào **https://aiven.io** → Sign up (dùng GitHub/Google) → chọn **Free plan**.
2. **Create service** → chọn **MySQL** → plan **Free** → region gần (Singapore) → tạo.
3. Chờ trạng thái **Running** (~2 phút). Vào tab **Overview**, copy **Service URI**, dạng:
   ```
   mysql://avnadmin:XXXX@mysql-xxxx.aivencloud.com:12345/defaultdb?ssl-mode=REQUIRED
   ```
4. Đổi tên DB cho đúng: trong Aiven → tab **Databases** → tạo database tên **`nali_dental`**.
   Sửa `defaultdb` trong URI thành `nali_dental`. **Giữ chuỗi này** — sẽ dán vào Render.

**Nạp dữ liệu vào Aiven** (chạy 1 lần từ máy bạn):
```bash
cd flask_app
# Windows PowerShell:
$env:DATABASE_URL="mysql://avnadmin:XXXX@...aivencloud.com:12345/nali_dental?ssl-mode=REQUIRED"
$env:APP_ENV="production"; $env:DB_PASS="x"; $env:INITIAL_ADMIN_PASSWORD="MatKhauAdminMoi123"
flask --app run.py db upgrade
flask --app run.py init-db
flask --app run.py seed-db
flask --app run.py seed-content
```
> Xong sẽ có 15 dịch vụ, admin, 6 FAQ, 4 bài viết trên DB cloud.

---

## Bước 2 — Lấy Gemini API key (miễn phí) — 2 phút
1. Vào **https://aistudio.google.com/apikey** → **Create API key**.
2. Copy key (bắt đầu bằng `AIza`, dài **39 ký tự**). Dùng cho chatbot + AI xem ảnh răng.

---

## Bước 3 — Deploy lên Render — 5 phút
1. Vào **https://render.com** → Sign up bằng **GitHub**.
2. **New → Blueprint** → chọn repo `NaLi-Dental-Clinic-Web` → Render tự đọc `render.yaml`
   và hiện 2 service: `nali-dental-web` và `nali-dental-ai`. Bấm **Apply**.
3. Render sẽ hỏi các biến `sync: false`. Điền:

   | Service | Biến | Giá trị |
   |---|---|---|
   | cả 2 | `DATABASE_URL` | chuỗi Aiven ở Bước 1 |
   | web | `DB_PASS` | gõ bất kỳ, vd `x` (chỉ để qua rào chắn production) |
   | web | `MAIL_USERNAME` | Gmail của bạn |
   | web | `MAIL_PASSWORD` | App Password 16 ký tự |
   | web | `MAIL_DEFAULT_SENDER` | Gmail của bạn |
   | ai | `GEMINI_API_KEY` | key ở Bước 2 |

4. Chờ build (~3–5 phút mỗi service). Xanh là xong.

**Link của bạn:** `https://nali-dental-web.onrender.com` 🎉

---

## Bước 4 — Kiểm tra
- Mở link web → trang chủ hiện, đăng nhập admin bằng mật khẩu bạn đặt ở `INITIAL_ADMIN_PASSWORD`.
- Bấm 🤖 chat: hỏi giá → trả lời đúng. Bấm 📷 gửi ảnh răng → AI nhận xét.
- Đăng ký tài khoản mới bằng email thật → nhận email chào mừng.
- `https://nali-dental-ai.onrender.com/health` → `"ai_mode":"gemini"`, `"database_ket_noi":true`.

---

## Bước 5 — Bật email nhắc lịch trước 24h (0đ, GitHub Actions) — 3 phút

Web có sẵn endpoint `POST /api/cron/reminders` (gửi email cho các lịch hẹn ngày mai, mỗi lịch chỉ nhắc 1 lần).
GitHub Actions gọi nó mỗi sáng **08:00 giờ VN** theo file `.github/workflows/reminders.yml`.

1. Render → service **nali-dental-web** → Environment: đảm bảo có `MAIL_USERNAME`, `MAIL_PASSWORD` (Gmail App Password),
   `MAIL_DEFAULT_SENDER`; copy giá trị `CRON_TOKEN` (Render tự sinh khi sync Blueprint — nếu chưa có thì tự thêm 1 chuỗi ngẫu nhiên dài).
2. GitHub → repo → **Settings → Secrets and variables → Actions → New repository secret**:
   - `SITE_URL` = `https://nali-dental-web.onrender.com`
   - `CRON_TOKEN` = giá trị vừa copy ở Render.
3. Thử ngay: tab **Actions → "Nhắc lịch hẹn (email trước 24h)" → Run workflow**. Log phải in `HTTP 200 {"ok": true, "due": ..., "sent": ...}`.

Chạy tay trên máy: `cd flask_app && flask --app run.py send-reminders`.
Trong Admin → Lịch hẹn, lịch đã nhắc có biểu tượng chuông 🔔.

## Bước 6 — Bật đặt cọc giữ chỗ qua VietQR (0đ) — 1 phút

Không cần cổng thanh toán. Web tự tạo ảnh QR động (img.vietqr.io) đã điền số tiền + nội dung `NALI <mã lịch> <SĐT>`;
khách quét bằng app ngân hàng bất kỳ, bấm "Tôi đã chuyển", lễ tân vào Admin → Lịch hẹn bấm **✔ Đã nhận** → lịch tự xác nhận + email.

Render → `nali-dental-web` → Environment thêm:
- `BANK_ID` = mã ngân hàng theo VietQR (MB, VCB, TCB, ACB, BIDV, VPB, TPB, STB...)
- `BANK_ACCOUNT_NO` = số tài khoản phòng khám
- `BANK_ACCOUNT_NAME` = tên chủ tài khoản (không dấu)
- (tuỳ chọn) `DEPOSIT_AMOUNT` = số tiền cọc, mặc định 100000

Để trống `BANK_ACCOUNT_NO` thì lựa chọn "Đặt cọc" tự ẩn khỏi form đặt lịch.

## Nếu gặp lỗi
| Triệu chứng | Nguyên nhân / cách sửa |
|---|---|
| Build lỗi `No module named ...` | Kiểm tra `rootDir` đúng `flask_app` / `ai_service` |
| Web 500 sau deploy | `DATABASE_URL` sai hoặc chưa chạy `db upgrade` ở Bước 1 |
| Chatbot trả lời "offline" | `GEMINI_API_KEY` chưa đúng (phải 39 ký tự, bắt đầu `AIza`) |
| Mở link chờ lâu | Bình thường với free tier — service đang "thức dậy" |
| Muốn không bị ngủ | Dùng dịch vụ ping miễn phí (cron-job.org) gọi `/health` mỗi 10 phút |

## Chi phí
**0đ/tháng.** Aiven free vĩnh viễn, Render free 750 giờ/tháng (đủ chạy liên tục 1 service; 2 service thì
service phụ sẽ tự ngủ khi hết giờ — vẫn thức lại khi có người dùng).

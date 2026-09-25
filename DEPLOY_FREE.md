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
3. Hạn mức gói free tính theo **ngày, riêng từng model**: `gemini-3.6-flash` chỉ **20 lượt/ngày**, hết là chatbot
   rơi về chế độ offline. Vì vậy chat mặc định dùng `gemini-3.5-flash-lite` (hạn mức cao hơn nhiều, eval 30 câu
   vẫn 100%). Muốn đổi thì đặt `GEMINI_MODEL` trên Render; AI xem ảnh vẫn dùng `gemini-3.6-flash` (ít lượt).

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

## Bước 5 — Giữ server thức + email nhắc lịch bằng cron-job.org (0đ) — 5 phút

Hai việc cần chạy theo giờ: (1) ping server để Render free không ngủ và MySQL free của Aiven không tự tắt
vì không hoạt động, (2) gọi endpoint gửi email nhắc lịch mỗi sáng. Lúc đầu mình dùng GitHub Actions nhưng
lịch cron của GitHub bị trễ vài giờ và hay bỏ lượt, nên chuyển sang https://cron-job.org (miễn phí, đúng giờ).

Web có sẵn 2 endpoint cho việc này:
- `GET /healthz/db` — trả 200 nếu app và database đều sống (mỗi lần gọi chạy `SELECT 1` nên Aiven tính là có hoạt động).
- `POST /api/cron/reminders` — gửi email cho các lịch hẹn ngày mai, mỗi lịch chỉ nhắc 1 lần; cần header `X-Cron-Token`.

1. Đăng ký tài khoản cron-job.org → **Cronjobs → Create cronjob**. Tạo 3 job:

| Title | URL | Schedule |
|---|---|---|
| NALI web | `https://nali-dental-web.onrender.com/healthz/db` | Every 10 minutes, giới hạn giờ **7:00–21:00** |
| NALI AI | `https://nali-dental-ai.onrender.com/health` | Every 10 minutes, giới hạn giờ **8:00–18:00** |
| NALI reminders | `https://nali-dental-web.onrender.com/api/cron/reminders` | Every day lúc **08:00** |

   Ở phần Schedule chọn múi giờ **Asia/Ho_Chi_Minh**. Với 2 job ping, dùng chế độ *Custom*: minutes `0,10,20,30,40,50`,
   hours `7-21` (web) hoặc `8-18` (AI). Giới hạn giờ như vậy để tổng thời gian chạy của 2 service dưới **750 giờ/tháng**
   của gói free Render (chạy 24/7 cả hai sẽ vượt và bị tạm dừng).

2. Job **NALI reminders**: tab **Advanced** → Request method `POST` → Headers thêm `X-Cron-Token` = giá trị `CRON_TOKEN`
   lấy ở Render → nali-dental-web → Environment. Timeout đặt 30s.
   (Nếu không thấy chỗ thêm header thì thêm `?token=<giá trị>` vào cuối URL cũng được.)

3. Bấm **Test run** ở mỗi job: 2 job ping phải trả 200; job reminders trả `{"ok": true, "due": ..., "sent": ...}`.
   Lần đầu server đang ngủ có thể mất 30–40 giây, bấm test lại lần nữa.

4. Trong Settings của cron-job.org có thể tắt email thông báo lỗi nếu thấy phiền; lỗi vẫn xem được trong tab History.

Chạy tay trên máy: `cd flask_app && flask --app run.py send-reminders`.
Trong Admin → Lịch hẹn, lịch đã nhắc có biểu tượng chuông.

## Bước 5b — Redis dùng chung cho rate limit (tuỳ chọn, 0đ) — 2 phút

Mặc định giới hạn đăng nhập sai / rate limit API đếm trong từng worker gunicorn (web chạy 2 worker nên
giới hạn thực tế gấp đôi). Muốn đếm chung: Render → **New → Key Value** (gói Free 25MB) → tạo xong copy
**Internal Key Value URL** (dạng `redis://red-xxxx:6379`) → dán vào biến `REDIS_URL` của `nali-dental-web`.
Redis lỗi thì web tự quay về đếm trong tiến trình, không ảnh hưởng người dùng.

## Bước 5c — Eval Gemini trong CI và token nạp lại cấu hình — 2 phút

- GitHub → repo → Settings → Secrets and variables → Actions → thêm secret `GEMINI_API_KEY` (cùng key đang dùng
  trên Render). Job "eval Gemini (LLM-as-judge)" chạy **thứ Hai hàng tuần** hoặc khi bấm **Run workflow** (không chạy
  mỗi push vì quota free tính theo ngày): 30 câu trên `gemini-3.5-flash-lite`, chấm theo lô bằng Gemini, lưu artifact
  `eval-gemini-<sha>` và tóm tắt trong Job Summary. Đổi model đo bằng Variables `GEMINI_EVAL_MODEL`, `GEMINI_JUDGE_MODEL`.
  Không có secret thì job tự bỏ qua. Chạy tay trên máy: `LLM_BACKEND=gemini python eval_agent.py --judge gemini --history`.
- Render → cả 2 service thêm `AI_ADMIN_TOKEN` (một chuỗi ngẫu nhiên, giống nhau ở hai nơi). Web gửi token này khi
  gọi `POST /reload` của AI service sau khi admin sửa Cài đặt phòng khám. Để trống thì endpoint mở.

## Bước 6 — Bật đặt cọc giữ chỗ qua VietQR (0đ) — 1 phút

Không cần cổng thanh toán. Web tự tạo ảnh QR động (img.vietqr.io) đã điền số tiền + nội dung `NALI <mã lịch> <SĐT>`;
khách quét bằng app ngân hàng bất kỳ, bấm "Tôi đã chuyển", lễ tân vào Admin → Lịch hẹn bấm **✔ Đã nhận** → lịch tự xác nhận + email.

Render → `nali-dental-web` → Environment thêm:
- `BANK_ID` = mã ngân hàng theo VietQR (MB, VCB, TCB, ACB, BIDV, VPB, TPB, STB...)
- `BANK_ACCOUNT_NO` = số tài khoản phòng khám
- `BANK_ACCOUNT_NAME` = tên chủ tài khoản (không dấu)
- (tuỳ chọn) `DEPOSIT_AMOUNT` = số tiền cọc, mặc định 100000

Để trống `BANK_ACCOUNT_NO` thì lựa chọn "Đặt cọc" tự ẩn khỏi form đặt lịch.

## Bước 7 — Google Analytics 4 + Search Console (0đ) — 5 phút

**GA4 (đo lượt truy cập, nguồn khách, số người bấm đặt lịch/chat):**
1. https://analytics.google.com → Bắt đầu đo lường → tài khoản `NALI Dental` → thuộc tính `NALI Dental Web` (múi giờ VN, VND).
2. Nền tảng **Web** → URL `nali-dental-web.onrender.com` → Tạo luồng → copy **Mã đo lường** `G-XXXXXXXXXX`.
3. Render → `nali-dental-web` → Environment → thêm `GA_MEASUREMENT_ID` = `G-XXXXXXXXXX`.
   Web tự gửi các sự kiện: `book_click`, `booking_submit`, `generate_lead` (đặt lịch xong), `chat_open`, `chat_message`, `phone_click`, `feedback_submit`.
   Trong GA4 → Quản trị → Sự kiện → đánh dấu `generate_lead` là *chuyển đổi* để xem tỉ lệ khách đặt lịch.

**Search Console (lên Google tìm kiếm, xem từ khoá):**
1. https://search.google.com/search-console → Thêm tài sản → **Tiền tố URL** → `https://nali-dental-web.onrender.com`.
2. Xác minh bằng **Thẻ HTML** → copy phần `content="..."` → Render thêm `GOOGLE_SITE_VERIFICATION` = giá trị đó → deploy xong quay lại bấm **Xác minh**.
3. Menu **Sơ đồ trang web** → nhập `sitemap.xml` → Gửi (web đã có sẵn `/sitemap.xml` và `/robots.txt`).

## Bước 9 — Báo lễ tân qua Telegram (0đ) — 3 phút

Có lịch mới (web hoặc chatbot), khách báo đã chuyển cọc, phản hồi mới → điện thoại lễ tân nhận tin ngay.

1. Mở Telegram, tìm **@BotFather** → gõ `/newbot` → đặt tên (vd `NALI Le tan`) và username (vd `nali_letan_bot`)
   → BotFather trả về **token** dạng `123456789:AAH...` → copy.
2. Lấy chat id: tìm bot vừa tạo, bấm **Start** và nhắn "hi". Sau đó mở trình duyệt:
   `https://api.telegram.org/bot<TOKEN>/getUpdates` → tìm `"chat":{"id":123456789` → đó là **chat id**.
   (Muốn báo vào nhóm: thêm bot vào nhóm, nhắn 1 câu trong nhóm rồi làm tương tự; id nhóm là số âm.)
3. Render → **cả 2 service** (`nali-dental-web` và `nali-dental-ai`) → Environment → thêm
   `TELEGRAM_BOT_TOKEN` và `TELEGRAM_CHAT_ID`. Xong.

Thử: đặt một lịch trên web → điện thoại rung.

## Bước 8 — Thư viện ca điều trị trước/sau

Admin → **Ca điều trị** → Thêm ca: tiêu đề, dịch vụ, thời gian, mô tả, ảnh TRƯỚC + ảnh SAU (JPG/PNG/WEBP, tối đa 12MB).
Ảnh được nén ~150KB, bỏ EXIF và lưu **trong MySQL** (Render free xoá file upload khi deploy lại, DB thì không).
Khách xem tại `/ket-qua` (thanh trượt so sánh, lọc theo dịch vụ); trang chi tiết dịch vụ hiện ca liên quan.
Tạo 1 ca demo bằng ảnh minh hoạ: `flask --app run.py seed-cases` (nhãn "Ảnh minh hoạ" hiện cho khách biết).
Chỉ đăng ảnh thật khi khách đồng ý; nên chụp cận răng, không lộ mặt.

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

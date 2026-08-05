# 🤖 NALI Trợ Lý AI — Chatbot RAG + Đặt lịch (Đồ án Python nâng cao)

Một **AI Agent** cho website Nha khoa NALI, viết bằng **Python (FastAPI)**. Không chỉ
trả lời câu hỏi, trợ lý còn **thực sự đặt lịch hẹn** vào cơ sở dữ liệu của phòng khám.

> Web NALI viết bằng PHP; phần AI này là một **service Python độc lập** — đúng yêu
> cầu môn Python nâng cao — web PHP gọi sang qua HTTP.

---

## 🧠 Bộ não LLM (chọn qua `LLM_BACKEND`)
| Backend | Mô tả | Khi nào |
|---------|-------|---------|
| **local** | **Qwen2.5-3B FINETUNE** tự host (Ollama/llama.cpp) — tool-calling JSON | Ưu tiên cho đồ án |
| gemini | Google Gemini API | Fallback khi không có LLM local |
| offline | Luật + TF-IDF, không cần mạng | Fallback cuối, demo không chết |

- **Finetune model**: xem [`finetune/README.md`](finetune/README.md) (QLoRA Qwen2.5-3B trên GPU → GGUF → Ollama).
- **Docker & Deploy**: xem [`../DEPLOY.md`](../DEPLOY.md) (docker-compose full-stack + HF Spaces free).

## 🎯 3 khả năng cốt lõi

| # | Khả năng | Kỹ thuật |
|---|----------|----------|
| 1 | **Tư vấn dịch vụ, giá, giờ mở cửa…** | **RAG** (truy hồi ngữ nghĩa trên dữ liệu thật của phòng khám) |
| 2 | **Kiểm tra khung giờ còn trống** | Truy vấn MySQL + sinh slot theo giờ làm việc |
| 3 | **Đặt lịch bằng hội thoại** | **Function Calling** — AI tự gọi hàm ghi vào bảng `appointments` |

Lịch do AI đặt **hiện ngay trong Admin Panel** của web (dùng chung bảng `appointments`).

---

## 🏗️ Kiến trúc

```
┌─────────────────┐   HTTP/JSON   ┌──────────────────────────┐   SQL    ┌──────────────┐
│  Web PHP (NALI) │ ────────────▶ │  AI Service (Python)     │ ───────▶ │ MySQL        │
│  chat widget    │   /chat       │  FastAPI + Gemini + RAG  │          │ nali_dental  │
└─────────────────┘ ◀──────────── └──────────────────────────┘          └──────────────┘
                     câu trả lời
```

**Luồng xử lý 1 câu hỏi:**
1. Widget gửi `{session_id, message}` tới `POST /chat`.
2. `retriever` truy hồi tri thức liên quan (RAG) → chèn vào prompt.
3. **Gemini** đọc ngữ cảnh, tự quyết định gọi công cụ (`tim_dich_vu`, `kiem_tra_lich_trong`, `dat_lich_hen`).
4. Công cụ thao tác MySQL, trả kết quả → Gemini viết câu trả lời tiếng Việt.
5. Nếu Gemini/mạng lỗi → **tự chuyển sang agent offline** (TF-IDF + slot-filling).

---

## 📁 Cấu trúc mã nguồn

| File | Vai trò |
|------|---------|
| `config.py` | Đọc cấu hình từ `.env` (singleton, dataclass bất biến) |
| `database.py` | Lớp truy cập MySQL (đọc dịch vụ, lịch trống, ghi lịch hẹn) |
| `knowledge.py` | Dựng kho tri thức RAG (thông tin phòng khám + dịch vụ) |
| `retriever.py` | Tìm kiếm ngữ nghĩa: Gemini embeddings **hoặc** TF-IDF offline |
| `tools.py` | 3 "công cụ" nghiệp vụ + parse ngày/giờ tiếng Việt |
| `gemini_agent.py` | Agent online: Gemini + Function Calling |
| `fallback_agent.py` | Agent offline: máy trạng thái slot-filling |
| `main.py` | FastAPI server (`/chat`, `/reset`, `/health`, `/docs`) |
| `test_agent.py` | Kiểm thử phần lõi, chạy offline |

---

## 🚀 Cách chạy

### 1. Cài thư viện
```bash
cd C:\xampp\htdocs\nali\ai_service
python -m venv .venv
.venv\Scripts\activate        # Windows
pip install -r requirements.txt
```

### 2. Cấu hình
```bash
copy .env.example .env
```
Mở `.env`, dán **Gemini API key** (miễn phí tại https://aistudio.google.com/apikey).
> Để trống key vẫn chạy được — hệ thống tự dùng **chế độ offline** (TF-IDF).

### 3. Bật MySQL
Mở **XAMPP Control Panel** → Start **Apache** và **MySQL**. Import DB `nali_dental`
nếu chưa có (xem README chính của web).

### 4. Chạy AI service
```bash
python main.py
```
Mở http://127.0.0.1:8000/docs để xem/test API (Swagger). Kiểm tra
http://127.0.0.1:8000/health để biết chế độ AI, backend RAG, kết nối DB.

### 5. Mở website
Truy cập http://localhost/nali/ → bong bóng 🤖 góc phải dưới là chatbot.

---

## 🧪 Kiểm thử
```bash
python test_agent.py
```
Kiểm tra: parse ngày/giờ tiếng Việt, tìm dịch vụ, RAG (TF-IDF), luồng đặt lịch —
tất cả chạy **không cần** server/API/DB (14 test).

---

## 🎤 Điểm nhấn khi thuyết trình (để "ăn" điểm cao)

1. **Không chỉ chatbot — là AI Agent có hành động**: mở Admin Panel, đặt lịch qua
   chatbot, F5 → lịch hẹn xuất hiện. Đây là điểm gây ấn tượng nhất.
2. **RAG chống "bịa"**: AI chỉ trả lời dựa trên dữ liệu thật của phòng khám; đổi giá
   trong DB → câu trả lời đổi theo. Giải thích embeddings vs TF-IDF.
3. **Function Calling**: cho xem `tools.py` — mô hình tự chọn hàm để gọi, tự điền tham số.
4. **Kiến trúc tách lớp & phòng thủ**: config/DAL/RAG/agent/API tách bạch; có fallback
   nhiều tầng (Gemini→offline, DB thật→dữ liệu tĩnh) nên demo không bao giờ chết.
5. **Kỹ thuật Python nâng cao**: `async` (FastAPI), dataclass, context manager,
   type hints, decorator (`@lru_cache`), xử lý ngoại lệ tùy biến, kiểm thử.

---

## 🔧 Xử lý sự cố

| Triệu chứng | Cách xử lý |
|-------------|-----------|
| Widget báo "Chưa chạy AI service :8000" | Chưa chạy `python main.py`, hoặc sai cổng |
| `/health` báo `database_ket_noi: false` | Bật MySQL trong XAMPP; kiểm tra `.env` (user/mật khẩu/cổng) |
| Trả lời kém tự nhiên | Đang ở chế độ offline — kiểm tra `GEMINI_API_KEY` trong `.env` |
| Lỗi tiếng Việt trên console | Đã tự ép UTF-8; nếu vẫn lỗi: `set PYTHONUTF8=1` |

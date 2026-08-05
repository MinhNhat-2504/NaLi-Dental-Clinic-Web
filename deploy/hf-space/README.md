---
title: NALI Dental AI
emoji: 🦷
colorFrom: blue
colorTo: indigo
sdk: docker
app_port: 7860
pinned: false
---

# NALI Dental AI — Space công khai

Chatbot RAG + đặt lịch dùng **Qwen2.5-3B finetune** (chạy bằng llama.cpp).

## Cách deploy (tóm tắt — chi tiết ở `DEPLOY.md` gốc dự án)
1. Đưa file GGUF đã finetune (`nali-qwen-q4.gguf`) lên một **HF model repo** của bạn.
2. Tạo **Space** kiểu **Docker**, copy nội dung `ai_service/` + 3 file trong thư mục này
   (`Dockerfile`, `start.sh`, `README.md`) vào Space.
3. Trong Space → **Settings → Secrets**:
   - `MODEL_REPO` = `yourname/nali-dental-gguf`
   - `MODEL_FILE` = `nali-qwen-q4.gguf`
   - (tuỳ chọn) `GEMINI_API_KEY` để có fallback.
4. Space tự build & chạy → URL công khai dạng `https://<user>-nali-dental-ai.hf.space`.
5. Trỏ web tới URL này: đặt biến môi trường `AI_SERVICE_URL` cho web PHP,
   hoặc sửa hằng số trong `ai_chat_widget.php`.

> Lưu ý: Space free chạy CPU nên trả lời chậm hơn GPU. Tính năng **tư vấn (RAG)** hoạt
> động đầy đủ; **đặt lịch ghi DB** cần kết nối MySQL (chạy đủ ở bản Docker/VPS).

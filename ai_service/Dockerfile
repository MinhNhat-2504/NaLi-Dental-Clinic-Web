# ===== AI Service (FastAPI) — Trợ lý AI NALI =====
FROM python:3.11-slim

WORKDIR /app

# Cài thư viện trước (tận dụng cache Docker)
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Mã nguồn service
COPY . .

EXPOSE 8000

# Chạy server (0.0.0.0 để truy cập được từ ngoài container)
CMD ["uvicorn", "main:app", "--host", "0.0.0.0", "--port", "8000"]

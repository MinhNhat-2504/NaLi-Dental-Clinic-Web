# ===== HuggingFace Space (Docker) — NALI AI + LLM tự host =====
# Chạy Qwen2.5-3B finetune (GGUF) bằng llama.cpp + FastAPI của ai_service,
# tạo 1 URL công khai MIỄN PHÍ cho chatbot.
FROM python:3.11-slim

RUN apt-get update && apt-get install -y --no-install-recommends \
        build-essential curl git && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# Cài thư viện ai_service + máy chủ LLM (llama.cpp) + tải model từ HF Hub
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt \
    && pip install --no-cache-dir "llama-cpp-python[server]==0.3.2" huggingface_hub

# Mã nguồn ai_service (main.py, retriever.py, tools.py, ...) + script khởi động
COPY . .
RUN chmod +x start.sh

ENV LLM_BACKEND=local \
    LOCAL_LLM_URL=http://127.0.0.1:8001/v1 \
    LOCAL_LLM_MODEL=nali-dental \
    HF_HOME=/app/.cache \
    PORT=7860

EXPOSE 7860
CMD ["./start.sh"]

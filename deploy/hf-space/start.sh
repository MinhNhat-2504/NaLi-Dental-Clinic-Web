#!/bin/bash
# start.sh — Khởi động llama.cpp (chạy model NALI finetune) + FastAPI trên HF Space.
set -e

: "${MODEL_REPO:?Hãy đặt MODEL_REPO trong Space Secrets, vd: yourname/nali-dental-gguf}"
: "${MODEL_FILE:=nali-qwen-q4.gguf}"

echo "[space] Tải model $MODEL_FILE từ $MODEL_REPO ..."
python -c "import os; from huggingface_hub import hf_hub_download; \
hf_hub_download(repo_id=os.environ['MODEL_REPO'], filename=os.environ['MODEL_FILE'], local_dir='/app/models')"

echo "[space] Khởi động máy chủ LLM (llama.cpp, OpenAI-compatible)..."
python -m llama_cpp.server \
    --model "/app/models/$MODEL_FILE" \
    --model_alias nali-dental \
    --host 127.0.0.1 --port 8001 --n_ctx 2048 &

echo "[space] Chờ LLM sẵn sàng..."
until curl -sf http://127.0.0.1:8001/v1/models >/dev/null 2>&1; do sleep 2; done

echo "[space] Khởi động FastAPI (ai_service) trên cổng 7860."
exec uvicorn main:app --host 0.0.0.0 --port 7860

# 🧠 Finetune Qwen2.5-3B cho NALI (QLoRA)

Quy trình biến **Qwen2.5-3B-Instruct** thành **"nali-dental"** — một LLM mở
*hiểu phòng khám NALI*, đúng giọng tư vấn và biết đặt lịch bằng tool-call JSON.

```
generate_dataset.py → train_qlora.py → merge_and_export.py → GGUF → Ollama → ai_service
   (dữ liệu SFT)        (QLoRA/LoRA)      (gộp fp16)        (nén)   (phục vụ)  (LLM_BACKEND=local)
```

## Yêu cầu
- GPU NVIDIA (CUDA), ~6–8GB VRAM trở lên.
- Python 3.10/3.11. Cài torch theo CUDA của máy (xem [pytorch.org](https://pytorch.org/get-started/locally/)),
  rồi: `pip install -r finetune/requirements.txt`.

## Các bước

### 1. Sinh dữ liệu huấn luyện
```bash
python finetune/generate_dataset.py
# -> finetune/data/nali_sft.jsonl  (≈110 mẫu: hỏi giá, tư vấn, triệu chứng, tool-call, đặt lịch)
```

### 2. Finetune QLoRA
```bash
python finetune/train_qlora.py
# -> finetune/out/nali-qwen-lora/  (adapter LoRA, ~30–60 phút tùy GPU)
```

### 3. Gộp adapter vào model gốc
```bash
python finetune/merge_and_export.py
# -> finetune/out/nali-qwen-merged/  (model fp16 hoàn chỉnh)
```

### 4. Chuyển sang GGUF (để Ollama chạy nhẹ)
```bash
git clone https://github.com/ggerganov/llama.cpp
pip install -r llama.cpp/requirements.txt
python llama.cpp/convert_hf_to_gguf.py finetune/out/nali-qwen-merged \
       --outfile finetune/out/nali-qwen.f16.gguf --outtype f16
# Nén 4-bit cho nhẹ & nhanh (khuyên dùng):
llama.cpp/llama-quantize finetune/out/nali-qwen.f16.gguf \
       finetune/out/nali-qwen-q4.gguf Q4_K_M
```

### 5. Nạp vào Ollama
```bash
# Cài Ollama: https://ollama.com  (hoặc dùng container trong docker-compose)
ollama create nali-dental -f finetune/Modelfile
ollama run nali-dental "Tẩy trắng răng giá bao nhiêu?"   # thử nhanh
```

### 6. Cho web dùng model này
Trong `ai_service/.env`:
```
LLM_BACKEND=local
LOCAL_LLM_URL=http://localhost:11434/v1
LOCAL_LLM_MODEL=nali-dental
```
Khởi động lại `python main.py` → `/health` sẽ báo `"ai_mode":"local"`.

## Mẹo & xử lý sự cố
- **Hết VRAM khi train**: giảm `per_device_train_batch_size=1`, tăng `gradient_accumulation_steps`.
- **bitsandbytes lỗi trên Windows**: đảm bảo `bitsandbytes>=0.44` và đúng bản CUDA torch.
- **Muốn dữ liệu mạnh hơn**: thêm mẫu trong `generate_dataset.py` (càng nhiều tình huống thật càng tốt).
- **Không có GPU?** Có thể train trên Google Colab/Kaggle (T4 free) với cùng script này.
- **Đánh giá**: so sánh câu trả lời trước/sau finetune trên vài câu hỏi NALI để đưa vào báo cáo.

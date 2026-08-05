"""
train_qlora.py — Finetune Qwen2.5-3B-Instruct bằng QLoRA trên dữ liệu NALI.

QLoRA = quantize model xuống 4-bit + chỉ train các lớp adapter LoRA nhỏ, nên
finetune được model 3B trên GPU chỉ ~6-8GB VRAM (máy sinh viên chạy được).

Yêu cầu: 1 GPU NVIDIA (CUDA). Cài thư viện:  pip install -r finetune/requirements.txt
Chạy:  python finetune/train_qlora.py
Kết quả: adapter LoRA ở finetune/out/nali-qwen-lora  (merge/export ở bước sau).
"""
from __future__ import annotations

import os
import sys

# Ép console UTF-8 để in tiếng Việt không lỗi trên Windows
for _s in (sys.stdout, sys.stderr):
    try:
        _s.reconfigure(encoding="utf-8")  # type: ignore[attr-defined]
    except Exception:
        pass

import torch
from datasets import load_dataset
from peft import LoraConfig
from transformers import AutoModelForCausalLM, AutoTokenizer, BitsAndBytesConfig
from trl import SFTConfig, SFTTrainer

HERE = os.path.dirname(os.path.abspath(__file__))
BASE_MODEL = os.getenv("BASE_MODEL", "Qwen/Qwen2.5-3B-Instruct")
DATA_PATH = os.path.join(HERE, "data", "nali_sft.jsonl")
OUT_DIR = os.path.join(HERE, "out", "nali-qwen-lora")


def main() -> None:
    assert torch.cuda.is_available(), "Cần GPU NVIDIA (CUDA) để chạy QLoRA."
    assert os.path.exists(DATA_PATH), f"Chưa có dataset. Chạy generate_dataset.py trước. ({DATA_PATH})"

    # bf16 nếu GPU hỗ trợ (Ampere trở lên), ngược lại fp16
    use_bf16 = torch.cuda.is_bf16_supported()
    compute_dtype = torch.bfloat16 if use_bf16 else torch.float16
    print(f"GPU: {torch.cuda.get_device_name(0)} | dtype train: {'bf16' if use_bf16 else 'fp16'}")

    # 1) Nạp model ở 4-bit (QLoRA)
    bnb = BitsAndBytesConfig(
        load_in_4bit=True,
        bnb_4bit_quant_type="nf4",
        bnb_4bit_compute_dtype=compute_dtype,
        bnb_4bit_use_double_quant=True,
    )
    tokenizer = AutoTokenizer.from_pretrained(BASE_MODEL)
    if tokenizer.pad_token is None:
        tokenizer.pad_token = tokenizer.eos_token
    model = AutoModelForCausalLM.from_pretrained(
        BASE_MODEL, quantization_config=bnb, device_map="auto", torch_dtype=compute_dtype,
    )
    model.config.use_cache = False

    # 2) Cấu hình LoRA (chỉ train các adapter nhỏ)
    lora = LoraConfig(
        r=16, lora_alpha=32, lora_dropout=0.05, bias="none", task_type="CAUSAL_LM",
        target_modules=["q_proj", "k_proj", "v_proj", "o_proj",
                        "gate_proj", "up_proj", "down_proj"],
    )

    # 3) Dataset -> áp chat template của Qwen thành chuỗi văn bản
    ds = load_dataset("json", data_files=DATA_PATH, split="train")

    def to_text(ex):
        return {"text": tokenizer.apply_chat_template(
            ex["messages"], tokenize=False, add_generation_prompt=False)}

    ds = ds.map(to_text, remove_columns=ds.column_names)
    print(f"Số mẫu huấn luyện: {len(ds)}")

    # 4) Cấu hình & chạy SFT (QLoRA)
    cfg = SFTConfig(
        output_dir=OUT_DIR,
        num_train_epochs=3,
        per_device_train_batch_size=1,   # an toàn cho GPU 8GB (RTX 4060 Laptop)
        gradient_accumulation_steps=8,   # batch hiệu dụng = 8
        learning_rate=2e-4,
        lr_scheduler_type="cosine",
        warmup_ratio=0.03,
        logging_steps=10,
        save_strategy="epoch",
        bf16=use_bf16,
        fp16=not use_bf16,
        gradient_checkpointing=True,
        max_seq_length=1024,
        dataset_text_field="text",
        packing=False,
        report_to="none",
    )
    trainer = SFTTrainer(
        model=model, args=cfg, train_dataset=ds,
        peft_config=lora, processing_class=tokenizer,
    )
    trainer.train()
    trainer.save_model(OUT_DIR)
    tokenizer.save_pretrained(OUT_DIR)
    print(f"\n✅ Xong! Adapter LoRA lưu tại: {OUT_DIR}")
    print("   Bước tiếp: python finetune/merge_and_export.py  (gộp + xuất để nạp vào Ollama)")


if __name__ == "__main__":
    main()

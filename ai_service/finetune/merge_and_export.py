"""
merge_and_export.py — Gộp adapter LoRA vào model gốc -> model đầy đủ (fp16).

Sau bước này bạn có 1 thư mục model hoàn chỉnh để chuyển sang GGUF và nạp vào
Ollama (xem README). Chạy được trên CPU nếu GPU không đủ VRAM để gộp.

Chạy:  python finetune/merge_and_export.py
Kết quả: finetune/out/nali-qwen-merged/
"""
from __future__ import annotations

import os
import sys

for _s in (sys.stdout, sys.stderr):
    try:
        _s.reconfigure(encoding="utf-8")  # type: ignore[attr-defined]
    except Exception:
        pass

import torch
from peft import PeftModel
from transformers import AutoModelForCausalLM, AutoTokenizer

HERE = os.path.dirname(os.path.abspath(__file__))
BASE_MODEL = os.getenv("BASE_MODEL", "Qwen/Qwen2.5-3B-Instruct")
ADAPTER_DIR = os.path.join(HERE, "out", "nali-qwen-lora")
MERGED_DIR = os.path.join(HERE, "out", "nali-qwen-merged")


def main() -> None:
    assert os.path.exists(ADAPTER_DIR), f"Chưa có adapter. Chạy train_qlora.py trước. ({ADAPTER_DIR})"

    # Gộp trên CPU cho an toàn (không cần nhiều VRAM)
    print("Đang nạp model gốc (fp16)...")
    base = AutoModelForCausalLM.from_pretrained(
        BASE_MODEL, torch_dtype=torch.float16, device_map="cpu")
    print("Đang gắn & gộp adapter LoRA...")
    model = PeftModel.from_pretrained(base, ADAPTER_DIR)
    model = model.merge_and_unload()

    os.makedirs(MERGED_DIR, exist_ok=True)
    model.save_pretrained(MERGED_DIR, safe_serialization=True)
    AutoTokenizer.from_pretrained(BASE_MODEL).save_pretrained(MERGED_DIR)
    print(f"\n✅ Đã gộp -> {MERGED_DIR}")
    print("   Bước tiếp: chuyển sang GGUF + nạp Ollama (xem finetune/README.md).")


if __name__ == "__main__":
    main()

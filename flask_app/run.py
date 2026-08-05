"""
run.py — Điểm khởi chạy ứng dụng Flask NALI.
Chạy:  python run.py      (mở http://127.0.0.1:5000)
"""
import sys

# Ép console UTF-8 để log tiếng Việt không lỗi trên Windows
for _s in (sys.stdout, sys.stderr):
    try:
        _s.reconfigure(encoding="utf-8")  # type: ignore[attr-defined]
    except Exception:
        pass

import os

from app import create_app

app = create_app()

if __name__ == "__main__":
    # Debug đọc từ biến môi trường (mặc định bật cho phát triển; tắt khi deploy)
    debug = os.getenv("FLASK_DEBUG", "1").lower() in ("1", "true", "yes")
    app.run(host="127.0.0.1", port=5000, debug=debug)

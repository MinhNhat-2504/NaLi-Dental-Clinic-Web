"""
config.py — Cấu hình ứng dụng Flask NALI Dental.
Đọc từ biến môi trường (.env), có giá trị mặc định cho môi trường phát triển.
"""
import os
from dotenv import load_dotenv

load_dotenv()


class Config:
    SECRET_KEY = os.getenv("SECRET_KEY", "nali-dev-secret-key-doi-khi-deploy")

    # --- Cơ sở dữ liệu (dùng chung MySQL nali_dental với bản PHP) ---
    DB_USER = os.getenv("DB_USER", "root")
    DB_PASS = os.getenv("DB_PASS", "123456")
    DB_HOST = os.getenv("DB_HOST", "localhost")
    DB_PORT = os.getenv("DB_PORT", "3306")
    DB_NAME = os.getenv("DB_NAME", "nali_dental")
    SQLALCHEMY_DATABASE_URI = (
        f"mysql+pymysql://{DB_USER}:{DB_PASS}@{DB_HOST}:{DB_PORT}/{DB_NAME}?charset=utf8mb4"
    )
    SQLALCHEMY_TRACK_MODIFICATIONS = False

    # --- Flask-Mail (email xác nhận đặt lịch) ---
    MAIL_SERVER = os.getenv("MAIL_SERVER", "smtp.gmail.com")
    MAIL_PORT = int(os.getenv("MAIL_PORT", "587"))
    MAIL_USE_TLS = True
    MAIL_USERNAME = os.getenv("MAIL_USERNAME", "")
    MAIL_PASSWORD = os.getenv("MAIL_PASSWORD", "")
    MAIL_DEFAULT_SENDER = os.getenv("MAIL_DEFAULT_SENDER", "NALI Dental <no-reply@nali.local>")
    # Chưa cấu hình SMTP -> không gửi thật (in ra console) để app không lỗi khi demo
    MAIL_SUPPRESS_SEND = not bool(os.getenv("MAIL_USERNAME"))

    # --- AI service (chatbot LLM finetune) ---
    AI_SERVICE_URL = os.getenv("AI_SERVICE_URL", "http://127.0.0.1:8000")

    # --- Phân trang ---
    PER_PAGE = int(os.getenv("PER_PAGE", "6"))

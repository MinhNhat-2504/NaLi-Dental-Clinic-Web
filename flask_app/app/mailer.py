"""mailer.py — Helper gửi email dùng chung (Flask-Mail).
Bọc try/except + ghi log (không nuốt lỗi âm thầm). Nếu chưa cấu hình SMTP thì
Flask-Mail tự bỏ qua theo MAIL_SUPPRESS_SEND.
"""
from flask import current_app
from flask_mail import Message

from .extensions import mail


def send_email(subject: str, recipient: str, body: str) -> bool:
    if not recipient:
        return False
    try:
        mail.send(Message(subject=subject, recipients=[recipient], body=body))
        return True
    except Exception as exc:  # noqa: BLE001
        current_app.logger.warning("Gửi email thất bại (%s): %s", recipient, exc)
        return False

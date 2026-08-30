"""notify.py — Báo cho lễ tân qua Telegram (miễn phí) khi có việc cần xử lý:
lịch hẹn mới, khách báo đã chuyển cọc, phản hồi mới.

Cần 2 biến: TELEGRAM_BOT_TOKEN (tạo bằng @BotFather) và TELEGRAM_CHAT_ID (id của
người/nhóm nhận). Thiếu thì hàm im lặng, không ảnh hưởng luồng chính. Gửi trong thread
nền để request của khách không phải chờ Telegram.
"""
from __future__ import annotations

import json
import threading
import urllib.request

from flask import current_app


def _post(token: str, chat_id: str, text: str, logger) -> bool:
    url = f"https://api.telegram.org/bot{token}/sendMessage"
    body = json.dumps({"chat_id": chat_id, "text": text, "parse_mode": "HTML",
                       "disable_web_page_preview": True}).encode("utf-8")
    req = urllib.request.Request(url, data=body, headers={"Content-Type": "application/json"}, method="POST")
    try:
        with urllib.request.urlopen(req, timeout=10) as resp:
            return resp.status == 200
    except Exception as exc:  # noqa: BLE001
        logger.warning("Telegram gửi thất bại: %s", exc)
        return False


def telegram_enabled() -> bool:
    cfg = current_app.config
    return bool(cfg.get("TELEGRAM_BOT_TOKEN") and cfg.get("TELEGRAM_CHAT_ID"))


def send_telegram(text: str, *, wait: bool = False) -> bool:
    """Gửi 1 tin nhắn. wait=True để chờ kết quả (dùng trong test); mặc định gửi nền."""
    if not telegram_enabled():
        return False
    cfg = current_app.config
    token, chat_id, logger = cfg["TELEGRAM_BOT_TOKEN"], cfg["TELEGRAM_CHAT_ID"], current_app.logger
    if wait or cfg.get("TESTING"):
        return _post(token, chat_id, text, logger)
    threading.Thread(target=_post, args=(token, chat_id, text, logger), daemon=True).start()
    return True


def _esc(s) -> str:
    return str(s or "").replace("&", "&amp;").replace("<", "&lt;").replace(">", "&gt;")


def notify_new_appointment(appt, service_name: str = "", source: str = "web") -> None:
    when = f"{appt.appointment_time.strftime('%H:%M')} {appt.appointment_date.strftime('%d/%m/%Y')}"
    coc = f"\nĐặt cọc: {int(appt.deposit_amount):,}đ (chờ chuyển)".replace(",", ".") if getattr(appt, "deposit_status", None) else ""
    send_telegram(f"<b>Lịch hẹn mới #{appt.id}</b> ({source})\n"
                  f"Khách: {_esc(appt.customer_name)} — {_esc(appt.customer_phone)}\n"
                  f"Thời gian: {when}\nDịch vụ: {_esc(service_name or 'Tư vấn')}{coc}\n"
                  f"Ghi chú: {_esc(appt.notes) or '—'}")


def notify_deposit_reported(appt) -> None:
    send_telegram(f"<b>Khách báo đã chuyển cọc</b> — lịch #{appt.id}\n"
                  f"{_esc(appt.customer_name)} — {_esc(appt.customer_phone)}\n"
                  f"Số tiền: {int(appt.deposit_amount or 0):,}đ".replace(",", ".") +
                  f"\nNội dung CK: NALI {appt.id} {_esc(appt.customer_phone)}\nVào Admin → Lịch hẹn bấm ✔ Đã nhận để xác nhận.")


def notify_feedback(fb) -> None:
    send_telegram(f"<b>Phản hồi mới</b> ({fb.rating}/5 — {_esc(fb.type)})\n"
                  f"{_esc(fb.name)} — {_esc(fb.phone or fb.email or '')}\n{_esc(fb.message)[:500]}")

"""notify.py — Báo Telegram cho lễ tân khi chatbot đặt lịch thành công (thư viện chuẩn, không chặn).
Cần TELEGRAM_BOT_TOKEN và TELEGRAM_CHAT_ID trong biến môi trường; thiếu thì bỏ qua."""
from __future__ import annotations

import json
import logging
import os
import threading
import urllib.request

logger = logging.getLogger("nali.notify")


def _post(token: str, chat_id: str, text: str) -> None:
    url = f"https://api.telegram.org/bot{token}/sendMessage"
    body = json.dumps({"chat_id": chat_id, "text": text, "parse_mode": "HTML"}).encode("utf-8")
    req = urllib.request.Request(url, data=body, headers={"Content-Type": "application/json"}, method="POST")
    try:
        urllib.request.urlopen(req, timeout=10).read()
    except Exception as exc:  # noqa: BLE001
        logger.warning("Telegram gửi thất bại: %s", exc)


def send_telegram(text: str) -> bool:
    token = os.getenv("TELEGRAM_BOT_TOKEN", "").strip()
    chat_id = os.getenv("TELEGRAM_CHAT_ID", "").strip()
    if not token or not chat_id:
        return False
    threading.Thread(target=_post, args=(token, chat_id, text), daemon=True).start()
    return True


def _esc(s) -> str:
    return str(s or "").replace("&", "&amp;").replace("<", "&lt;").replace(">", "&gt;")


def notify_booking(result: dict) -> None:
    """Gọi sau khi dat_lich_hen thành công."""
    send_telegram(f"<b>Lịch hẹn mới #{result.get('ma_lich_hen')}</b> (chatbot)\n"
                  f"Khách: {_esc(result.get('ho_ten'))} — {_esc(result.get('so_dien_thoai'))}\n"
                  f"Thời gian: {_esc(result.get('gio'))} {_esc(result.get('ngay'))}\n"
                  f"Dịch vụ: {_esc(result.get('dich_vu') or 'Tư vấn')} ({_esc(result.get('gia_text'))})")

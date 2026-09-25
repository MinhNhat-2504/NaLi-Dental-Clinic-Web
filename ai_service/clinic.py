"""clinic.py — Thông tin phòng khám dùng trong prompt và kho tri thức, đọc từ bảng clinic_settings.

Cùng bảng với web Flask (admin sửa ở /admin/cai-dat). DB không có bảng hoặc không kết nối được
thì dùng DEFAULTS (giống giá trị mặc định phía web) để chatbot vẫn chạy offline.
Giữ trong bộ nhớ; web gọi POST /reload sau khi admin lưu để đọc lại.
"""
from __future__ import annotations

import json

from database import DatabaseUnavailable, fetch_clinic_settings

DEFAULTS = {
    "name": "NALI Dental Clinic",
    "hotline": "0945 457 512",
    "email": "nalidental@gmail.com",
    "hours_text": "T2 - CN: 08:00 - 20:00",
    "open_hour": "8",
    "close_hour": "20",
    "branches": json.dumps([
        {"name": "Bình Thạnh", "address": "69/68 Đặng Thùy Trâm, Q. Bình Thạnh, TP.HCM"},
        {"name": "Quận 1", "address": "123 Nguyễn Huệ, Quận 1, TP.HCM"},
        {"name": "Gò Vấp", "address": "456 Quang Trung, Q. Gò Vấp, TP.HCM"},
    ], ensure_ascii=False),
}

_cache: dict | None = None


def load_clinic() -> dict:
    try:
        rows = fetch_clinic_settings()
    except DatabaseUnavailable:
        rows = {}
    data = {k: (rows.get(k) if rows.get(k) not in (None, "") else v) for k, v in DEFAULTS.items()}
    try:
        branches = json.loads(data["branches"])
        assert isinstance(branches, list)
    except (ValueError, AssertionError):
        branches = json.loads(DEFAULTS["branches"])
    data["branches"] = [b for b in branches if isinstance(b, dict) and b.get("name") and b.get("address")]
    return data


def get_clinic() -> dict:
    global _cache
    if _cache is None:
        _cache = load_clinic()
    return _cache


def refresh() -> dict:
    global _cache
    _cache = load_clinic()
    return _cache


def hotline() -> str:
    return get_clinic()["hotline"]

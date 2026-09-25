"""clinic.py — Thông tin phòng khám (tên, hotline, email, giờ, chi nhánh) lấy từ bảng clinic_settings.

Trước đây các giá trị này nằm rải rác trong template, email và AI service. Giờ có một nguồn duy nhất:
admin sửa ở /admin/cai-dat, web và AI service cùng đọc. DB chưa có bảng hoặc đang lỗi thì dùng DEFAULTS
để trang lỗi/footer vẫn hiển thị được.
"""
from __future__ import annotations

import json
import urllib.request

from flask import current_app

from .cache import cached, invalidate
from .extensions import db

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
KEYS = tuple(DEFAULTS)


def _load_rows() -> dict:
    from .models import ClinicSetting
    try:
        rows = {r.key: r.value for r in ClinicSetting.query.all()}
    except Exception:  # noqa: BLE001  DB lỗi / chưa migrate -> dùng mặc định
        db.session.rollback()
        rows = {}
    data = {k: (rows.get(k) if rows.get(k) not in (None, "") else v) for k, v in DEFAULTS.items()}
    try:
        branches = json.loads(data["branches"])
        assert isinstance(branches, list)
    except (ValueError, AssertionError):
        branches = json.loads(DEFAULTS["branches"])
    data["branches"] = [b for b in branches if isinstance(b, dict) and b.get("name") and b.get("address")]
    data["main_address"] = data["branches"][0]["address"] if data["branches"] else ""
    data["phone_e164"] = "+84" + "".join(ch for ch in data["hotline"] if ch.isdigit()).lstrip("0")
    return data


def get_clinic() -> dict:
    """Dict dùng trong template/email. Cache 60 giây trong tiến trình, xoá khi admin lưu."""
    return cached("clinic:settings", 60, _load_rows)


def parse_branches_text(text: str) -> list[dict]:
    """Textarea admin: mỗi dòng 'Tên | Địa chỉ'."""
    out = []
    for line in (text or "").splitlines():
        if "|" not in line:
            continue
        name, address = (p.strip() for p in line.split("|", 1))
        if name and address:
            out.append({"name": name, "address": address})
    return out


def branches_to_text(branches: list[dict]) -> str:
    return "\n".join(f"{b['name']} | {b['address']}" for b in branches)


def save_clinic(values: dict) -> None:
    """Ghi các key hợp lệ vào clinic_settings (upsert), xoá cache, báo AI service nạp lại tri thức."""
    from .models import ClinicSetting
    for key in KEYS:
        if key not in values:
            continue
        val = values[key]
        if key == "branches" and isinstance(val, list):
            val = json.dumps(val, ensure_ascii=False)
        row = db.session.get(ClinicSetting, key) or ClinicSetting(key=key)
        row.value = str(val).strip()
        db.session.add(row)
    db.session.commit()
    invalidate("clinic")
    invalidate("home")
    _notify_ai_reload()


def _notify_ai_reload() -> None:
    """AI service giữ kho tri thức trong bộ nhớ -> gọi /reload để nó đọc lại. Lỗi thì bỏ qua (chỉ chậm cập nhật)."""
    url = current_app.config.get("AI_SERVICE_URL", "").rstrip("/")
    if not url:
        return
    headers = {"Content-Type": "application/json"}
    token = current_app.config.get("AI_ADMIN_TOKEN", "")
    if token:
        headers["X-Admin-Token"] = token
    try:
        req = urllib.request.Request(url + "/reload", data=b"{}", headers=headers, method="POST")
        urllib.request.urlopen(req, timeout=8).read()
    except Exception as exc:  # noqa: BLE001
        current_app.logger.warning("Không báo được AI service nạp lại cấu hình: %s", exc)

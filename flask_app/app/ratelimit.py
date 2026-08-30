"""ratelimit.py — Giới hạn tần suất + khoá đăng nhập sai nhiều lần, chạy trong tiến trình.

Không cần Redis: mỗi worker gunicorn có bộ đếm riêng, đủ để chặn dò mật khẩu và spam API
ở quy mô phòng khám. Dùng chung một cấu trúc: key -> danh sách mốc thời gian trong cửa sổ.
"""
from __future__ import annotations

import threading
import time

_lock = threading.Lock()
_hits: dict[str, list[float]] = {}
_locked_until: dict[str, float] = {}


def _prune(key: str, window: int, now: float) -> list[float]:
    entries = [t for t in _hits.get(key, []) if now - t < window]
    if entries:
        _hits[key] = entries
    else:
        _hits.pop(key, None)
    return entries


def allow(key: str, limit: int, window: int) -> bool:
    """True nếu key còn trong hạn `limit` lượt / `window` giây; đồng thời ghi nhận 1 lượt."""
    now = time.time()
    with _lock:
        entries = _prune(key, window, now)
        if len(entries) >= limit:
            return False
        entries.append(now)
        _hits[key] = entries
        return True


def record_failure(key: str, max_failures: int = 5, window: int = 900, lock_for: int = 900) -> int:
    """Ghi 1 lần thất bại (đăng nhập sai). Đủ `max_failures` trong `window` giây -> khoá `lock_for` giây.
    Trả về số lần còn được thử (0 = vừa bị khoá)."""
    now = time.time()
    with _lock:
        entries = _prune(key, window, now)
        entries.append(now)
        _hits[key] = entries
        remaining = max_failures - len(entries)
        if remaining <= 0:
            _locked_until[key] = now + lock_for
            _hits.pop(key, None)
            return 0
        return remaining


def locked_seconds(key: str) -> int:
    """Số giây còn bị khoá (0 = không khoá)."""
    now = time.time()
    with _lock:
        until = _locked_until.get(key, 0)
        if until <= now:
            _locked_until.pop(key, None)
            return 0
        return int(until - now) + 1


def clear(key: str) -> None:
    with _lock:
        _hits.pop(key, None)
        _locked_until.pop(key, None)


def reset_all() -> None:
    """Dùng cho test."""
    with _lock:
        _hits.clear()
        _locked_until.clear()

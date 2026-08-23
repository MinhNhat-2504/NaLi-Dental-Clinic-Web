"""cache.py — Cache nhỏ trong tiến trình (TTL) cho các truy vấn lặp lại nhiều.
DB cloud ở xa (mỗi truy vấn ~0.2-0.3s đi về) nên gom/giữ kết quả 60s giúp trang chủ,
thư viện ca nhanh hơn rõ rệt. Không cần Redis; mỗi worker gunicorn có cache riêng (đủ dùng)."""
from __future__ import annotations

import threading
import time
from typing import Any, Callable

_lock = threading.Lock()
_store: dict[str, tuple[float, Any]] = {}
MAX_ITEMS = 512


def cached(key: str, ttl: int, fn: Callable[[], Any]) -> Any:
    """Trả giá trị cache nếu còn hạn, không thì gọi fn() và lưu lại."""
    now = time.time()
    with _lock:
        hit = _store.get(key)
        if hit and hit[0] > now:
            return hit[1]
    value = fn()
    with _lock:
        if len(_store) >= MAX_ITEMS:
            # bỏ các mục hết hạn; nếu vẫn đầy thì xoá hết cho đơn giản
            for k in [k for k, (exp, _) in _store.items() if exp <= now]:
                _store.pop(k, None)
            if len(_store) >= MAX_ITEMS:
                _store.clear()
        _store[key] = (now + ttl, value)
    return value


def invalidate(prefix: str = "") -> None:
    """Xoá cache theo tiền tố (vd sau khi admin sửa dịch vụ/ca)."""
    with _lock:
        for k in [k for k in _store if k.startswith(prefix)]:
            _store.pop(k, None)

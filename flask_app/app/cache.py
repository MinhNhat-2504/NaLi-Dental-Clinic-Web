"""cache.py — Cache nhỏ theo thời gian sống (TTL) cho các truy vấn lặp lại nhiều.

DB cloud ở xa (mỗi truy vấn ~0.2-0.3s đi về) nên giữ kết quả 60 giây giúp trang chủ, thư viện ca
nhanh hơn rõ rệt. Giá trị (đối tượng ORM) luôn nằm trong bộ nhớ của từng worker.

Khi có REDIS_URL, Redis chỉ giữ một "epoch" dùng chung: invalidate() tăng epoch, worker nào thấy epoch
đổi thì bỏ toàn bộ cache cục bộ. Nhờ vậy admin sửa dịch vụ trên worker A thì worker B cũng thấy ngay,
không phải chờ hết 60 giây. Không có Redis: cache và invalidate chỉ có hiệu lực trong worker gọi.
"""
from __future__ import annotations

import threading
import time
from typing import Any, Callable

_lock = threading.Lock()
_store: dict[str, tuple[float, Any]] = {}
_seen_epoch: int | None = None
MAX_ITEMS = 512
EPOCH_KEY = "nali:cache:epoch"


def _redis():
    from .ratelimit import _redis as _shared
    return _shared()


def _sync_epoch() -> None:
    """Đọc epoch trên Redis; khác lần trước thì xoá cache cục bộ."""
    global _seen_epoch
    r = _redis()
    if r is None:
        return
    try:
        raw = r.get(EPOCH_KEY)
    except Exception:  # noqa: BLE001
        return
    epoch = int(raw) if raw else 0
    if _seen_epoch is None:
        _seen_epoch = epoch
    elif epoch != _seen_epoch:
        _seen_epoch = epoch
        with _lock:
            _store.clear()


def cached(key: str, ttl: int, fn: Callable[[], Any]) -> Any:
    """Trả giá trị cache nếu còn hạn, không thì gọi fn() và lưu lại."""
    _sync_epoch()
    now = time.time()
    with _lock:
        hit = _store.get(key)
        if hit and hit[0] > now:
            return hit[1]
    value = fn()
    with _lock:
        if len(_store) >= MAX_ITEMS:
            for k in [k for k, (exp, _) in _store.items() if exp <= now]:
                _store.pop(k, None)
            if len(_store) >= MAX_ITEMS:
                _store.clear()
        _store[key] = (now + ttl, value)
    return value


def invalidate(prefix: str = "") -> None:
    """Xoá cache theo tiền tố ở worker này; có Redis thì báo mọi worker khác xoá theo (tăng epoch)."""
    global _seen_epoch
    with _lock:
        for k in [k for k in _store if k.startswith(prefix)]:
            _store.pop(k, None)
    r = _redis()
    if r is not None:
        try:
            _seen_epoch = int(r.incr(EPOCH_KEY))
        except Exception:  # noqa: BLE001
            pass

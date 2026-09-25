"""ratelimit.py — Giới hạn tần suất + khoá đăng nhập sai nhiều lần.

Hai backend, chọn tự động theo config REDIS_URL:
  * Không có REDIS_URL: bộ đếm trong tiến trình. Đủ cho một worker; với gunicorn nhiều worker thì mỗi
    worker đếm riêng nên giới hạn thực tế bằng cấu hình nhân số worker.
  * Có REDIS_URL: bộ đếm trong Redis (INCR/EXPIRE nguyên tử), dùng chung cho mọi worker và mọi instance.
    REDIS_URL="fake://" dùng fakeredis, chỉ để test.
Cùng một API cho cả hai: allow(), record_failure(), locked_seconds(), clear(), reset_all().
"""
from __future__ import annotations

import threading
import time

from flask import current_app, has_app_context

PREFIX = "nali:rl:"


# ---------- Backend Redis (tuỳ chọn) ----------
def _redis():
    """Client Redis cho app hiện tại, hoặc None nếu không cấu hình / không nối được."""
    if not has_app_context():
        return None
    url = current_app.config.get("REDIS_URL") or ""
    if not url:
        return None
    ext = current_app.extensions
    if "nali_redis" in ext:
        return ext["nali_redis"]
    client = None
    try:
        if url.startswith("fake://"):
            import fakeredis
            client = fakeredis.FakeRedis()
        else:
            import redis
            client = redis.Redis.from_url(url, socket_timeout=2, socket_connect_timeout=2)
            client.ping()
    except Exception as exc:  # noqa: BLE001  Redis lỗi -> dùng bộ đếm trong tiến trình, không làm web chết
        current_app.logger.warning("Redis không dùng được, dùng rate limit trong tiến trình: %s", exc)
        client = None
    ext["nali_redis"] = client
    return client


# ---------- Backend trong tiến trình ----------
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


# ---------- API ----------
def allow(key: str, limit: int, window: int) -> bool:
    """True nếu key còn trong hạn `limit` lượt / `window` giây; đồng thời ghi nhận 1 lượt."""
    r = _redis()
    if r is not None:
        k = f"{PREFIX}hit:{key}"
        count = r.incr(k)
        if count == 1:
            r.expire(k, window)
        return int(count) <= limit
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
    r = _redis()
    if r is not None:
        fk, lk = f"{PREFIX}fail:{key}", f"{PREFIX}lock:{key}"
        count = int(r.incr(fk))
        if count == 1:
            r.expire(fk, window)
        remaining = max_failures - count
        if remaining <= 0:
            r.set(lk, "1", ex=lock_for)
            r.delete(fk)
            return 0
        return remaining
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
    r = _redis()
    if r is not None:
        ttl = r.ttl(f"{PREFIX}lock:{key}")
        return int(ttl) if ttl and ttl > 0 else 0
    now = time.time()
    with _lock:
        until = _locked_until.get(key, 0)
        if until <= now:
            _locked_until.pop(key, None)
            return 0
        return int(until - now) + 1


def clear(key: str) -> None:
    r = _redis()
    if r is not None:
        r.delete(f"{PREFIX}hit:{key}", f"{PREFIX}fail:{key}", f"{PREFIX}lock:{key}")
        return
    with _lock:
        _hits.pop(key, None)
        _locked_until.pop(key, None)


def reset_all() -> None:
    """Dùng cho test."""
    r = _redis()
    if r is not None:
        keys = list(r.scan_iter(f"{PREFIX}*"))
        if keys:
            r.delete(*keys)
    with _lock:
        _hits.clear()
        _locked_until.clear()

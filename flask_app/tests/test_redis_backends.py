"""Rate limit và cache với backend Redis (fakeredis): dùng chung giữa các "worker" (app instance)."""
import pytest

from app import create_app, ratelimit, cache
from tests.conftest import TestConfig


class RedisTestConfig(TestConfig):
    REDIS_URL = "fake://"


@pytest.fixture()
def rapp():
    ratelimit.reset_all()
    app = create_app(RedisTestConfig)
    with app.app_context():
        ratelimit.reset_all()
        yield app
        ratelimit.reset_all()


def test_redis_client_is_used(rapp):
    with rapp.app_context():
        assert ratelimit._redis() is not None


def test_rate_limit_counts_in_redis(rapp):
    with rapp.app_context():
        allowed = [ratelimit.allow("ip:1", 3, 60) for _ in range(5)]
        assert allowed == [True, True, True, False, False]
        r = ratelimit._redis()
        assert int(r.get("nali:rl:hit:ip:1")) == 5
        assert 0 < r.ttl("nali:rl:hit:ip:1") <= 60


def test_lockout_in_redis(rapp):
    with rapp.app_context():
        assert ratelimit.record_failure("login:a", max_failures=3, window=60, lock_for=120) == 2
        assert ratelimit.record_failure("login:a", max_failures=3, window=60, lock_for=120) == 1
        assert ratelimit.record_failure("login:a", max_failures=3, window=60, lock_for=120) == 0
        assert 0 < ratelimit.locked_seconds("login:a") <= 120
        ratelimit.clear("login:a")
        assert ratelimit.locked_seconds("login:a") == 0


def test_cache_epoch_invalidates_other_worker(rapp):
    """Hai worker chia sẻ Redis: worker A invalidate -> worker B bỏ cache cục bộ."""
    calls = []

    def load():
        calls.append(1)
        return len(calls)

    with rapp.app_context():
        assert cache.cached("k", 60, load) == 1
        assert cache.cached("k", 60, load) == 1          # còn hạn -> không gọi lại
        # giả lập worker khác tăng epoch trên Redis
        ratelimit._redis().incr(cache.EPOCH_KEY)
        assert cache.cached("k", 60, load) == 2          # epoch đổi -> nạp lại
        cache.invalidate("k")
        assert cache.cached("k", 60, load) == 3


def test_falls_back_when_redis_unreachable():
    """REDIS_URL trỏ nơi không có Redis -> vẫn chạy bằng bộ đếm trong tiến trình."""
    class BadRedisConfig(TestConfig):
        REDIS_URL = "redis://127.0.0.1:1/0"
    app = create_app(BadRedisConfig)
    with app.app_context():
        ratelimit.reset_all()
        assert ratelimit._redis() is None
        assert [ratelimit.allow("x", 2, 60) for _ in range(3)] == [True, True, False]
        ratelimit.reset_all()

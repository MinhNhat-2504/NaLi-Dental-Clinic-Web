"""Proxy streaming /api/chat/stream: SSE về trình duyệt, AI chết vẫn trả fallback + ghi log."""
import json

from app.models import ChatLog


def _events(data: bytes):
    out = []
    for block in data.decode("utf-8").split("\n\n"):
        block = block.strip()
        if block.startswith("data:"):
            out.append(json.loads(block[5:]))
    return out


def test_stream_proxy_fallback_when_ai_down(app, client):
    r = client.post("/api/chat/stream", json={"session_id": "st1", "message": "giờ mở cửa?"})
    assert r.status_code == 200 and r.mimetype == "text/event-stream"
    ev = _events(r.data)
    assert any("delta" in e for e in ev) and ev[-1].get("done") is True and ev[-1]["mode"] == "offline"
    with app.app_context():
        log = ChatLog.query.filter_by(session_id="st1").first()
        assert log is not None and "hotline" in log.answer.lower() and bool(log.unanswered)


def test_stream_proxy_rate_limited(app, client):
    app.config["CHAT_RATE_LIMIT"] = 1
    from app import ratelimit
    ratelimit.reset_all()
    assert client.post("/api/chat/stream", json={"message": "a"}).status_code == 200
    assert client.post("/api/chat/stream", json={"message": "b"}).status_code == 429
    ratelimit.reset_all()

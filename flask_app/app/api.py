"""
api.py — Blueprint API JSON + LẬP TRÌNH MẠNG (CLO3).

* /api/services         : REST API trả JSON danh sách dịch vụ (của chính ứng dụng).
* /api/chat             : PROXY tới AI service (FastAPI) bằng urllib — minh hoạ đọc
                          dữ liệu JSON từ dịch vụ mạng bên ngoài (lập trình mạng).

Dùng urllib chuẩn thư viện + json để thể hiện rõ kỹ năng lập trình mạng.
"""
import json
import urllib.error
import urllib.request

from flask import Blueprint, current_app, jsonify, request
from flask_login import current_user

from .extensions import csrf
from .models import Appointment, ChatLog, Patient, Product
from .extensions import db

api_bp = Blueprint("api", __name__, url_prefix="/api")


@api_bp.route("/services")
def services_json():
    """REST API nội bộ: trả JSON danh sách dịch vụ (hỗ trợ tìm kiếm)."""
    q = (request.args.get("q") or "").strip()
    query = Product.query.filter_by(is_active=1)
    if q:
        query = query.filter(Product.name.like(f"%{q}%"))
    data = [{
        "id": p.id, "name": p.name, "description": p.description,
        "price": float(p.price or 0), "price_text": p.price_text,
        "duration": p.duration, "image": p.image,
    } for p in query.order_by(Product.id.desc()).all()]
    return jsonify({"success": True, "count": len(data), "services": data})


@api_bp.route("/weather")
def weather():
    """LẬP TRÌNH MẠNG (CLO3): đọc JSON thời tiết TP.HCM từ API PUBLIC bên ngoài
    (Open-Meteo, không cần API key) bằng urllib. Trả về gọn cho widget hiển thị."""
    url = ("https://api.open-meteo.com/v1/forecast?latitude=10.82&longitude=106.63"
           "&current=temperature_2m,weather_code&timezone=Asia%2FBangkok")
    codes = {0: "Trời quang", 1: "Ít mây", 2: "Có mây", 3: "Nhiều mây",
             45: "Sương mù", 51: "Mưa phùn nhẹ", 61: "Mưa nhẹ", 63: "Mưa vừa",
             65: "Mưa to", 80: "Mưa rào", 95: "Dông"}
    # Cache 10 phút để không gọi API ngoài mỗi lần tải trang (chịu được lúc API chậm)
    import time as _t
    cache = current_app.extensions.setdefault("nali_weather_cache", {})
    if cache and _t.time() - cache.get("ts", 0) < 600:
        return jsonify(cache["data"])
    last_exc = None
    for _attempt in range(2):  # thử lại 1 lần nếu mạng cloud chập chờn
        try:
            req = urllib.request.Request(url, headers={"User-Agent": "NALI-Dental/1.0"})
            with urllib.request.urlopen(req, timeout=15) as resp:
                data = json.loads(resp.read().decode("utf-8"))
            cur = data.get("current", {})
            out = {"success": True, "city": "TP. Hồ Chí Minh",
                   "temp": cur.get("temperature_2m"),
                   "desc": codes.get(cur.get("weather_code"), "Đang cập nhật")}
            cache.update(ts=_t.time(), data=out)
            return jsonify(out)
        except (urllib.error.URLError, OSError, ValueError) as exc:
            last_exc = exc
    current_app.logger.warning("Weather API lỗi: %s", last_exc)
    return jsonify({"success": False, "city": "TP. Hồ Chí Minh"}), 200



def _user_context() -> str:
    """Dựng ngữ cảnh 'khách đã đăng nhập' để chatbot chào tên, nhắc lịch, gợi ý tái khám.
    Chỉ áp dụng cho bệnh nhân (Patient); khách vãng lai/admin trả về chuỗi rỗng."""
    from datetime import date, timedelta
    if not getattr(current_user, "is_authenticated", False) or not isinstance(current_user._get_current_object(), Patient):
        return ""
    p = current_user._get_current_object()
    lines = [f"Họ tên: {p.full_name}", f"SĐT: {p.phone}"]
    q = Appointment.query.filter((Appointment.user_id == p.id) | (Appointment.customer_email == p.email))
    upcoming = (q.filter(Appointment.appointment_date >= date.today(),
                         Appointment.status.in_(("pending", "confirmed")))
                 .order_by(Appointment.appointment_date, Appointment.appointment_time).first())
    last_done = (q.filter(Appointment.status == "completed")
                  .order_by(Appointment.appointment_date.desc()).first())
    if upcoming:
        svc = ""
        if upcoming.product_ids:
            prod = Product.query.get(int(upcoming.product_ids.split(",")[0])) if upcoming.product_ids.split(",")[0].isdigit() else None
            svc = f" ({prod.name})" if prod else ""
        lines.append(f"Lịch hẹn sắp tới: {upcoming.appointment_date.strftime('%d/%m/%Y')} lúc {upcoming.appointment_time.strftime('%H:%M')}{svc}, trạng thái {upcoming.status}")
    else:
        lines.append("Lịch hẹn sắp tới: chưa có")
    if last_done:
        days = (date.today() - last_done.appointment_date).days
        lines.append(f"Lần khám gần nhất: {last_done.appointment_date.strftime('%d/%m/%Y')} ({days} ngày trước)")
        if days >= 180:
            lines.append("Gợi ý: đã quá 6 tháng kể từ lần khám gần nhất, nên nhắc khách tái khám/lấy cao răng định kỳ.")
    lines.append("Hướng dẫn: hãy chào khách bằng tên, nếu có lịch sắp tới thì nhắc nhẹ; đừng hỏi lại tên/SĐT khi đặt lịch.")
    return chr(10).join(lines)



_UNANSWERED_HINTS = ("chưa có thông tin", "không phản hồi", "chưa trả lời được", "gọi hotline", "chưa rõ ý")


def _log_chat(session_id: str, question: str, answer: str, mode: str, latency_ms: int) -> None:
    """Ghi nhật ký chat để đo chất lượng AI. Lỗi ghi log KHÔNG được làm hỏng chat."""
    try:
        low = (answer or "").lower()
        unanswered = (mode == "offline") or any(h in low for h in _UNANSWERED_HINTS) or not answer
        uid = None
        if getattr(current_user, "is_authenticated", False) and isinstance(current_user._get_current_object(), Patient):
            uid = current_user._get_current_object().id
        db.session.add(ChatLog(session_id=(session_id or "")[:80], user_id=uid, question=(question or "")[:4000],
                               answer=(answer or "")[:8000], mode=mode, latency_ms=latency_ms, unanswered=unanswered))
        db.session.commit()
    except Exception as exc:  # noqa: BLE001
        db.session.rollback()
        current_app.logger.warning("Không ghi được chat log: %s", exc)


@api_bp.route("/chat", methods=["POST"])
@csrf.exempt
def chat_proxy():
    """Nhận tin nhắn từ widget -> gọi AI service (mạng) -> trả JSON về trình duyệt.

    Đây là ví dụ LẬP TRÌNH MẠNG: dùng urllib gửi POST + đọc JSON từ API bên ngoài.
    """
    payload = request.get_json(silent=True) or {}
    body = json.dumps({
        "session_id": payload.get("session_id", "web"),
        "message": payload.get("message", ""),
        "user_context": _user_context(),
    }).encode("utf-8")

    url = current_app.config["AI_SERVICE_URL"].rstrip("/") + "/chat"
    req = urllib.request.Request(url, data=body,
                                 headers={"Content-Type": "application/json"}, method="POST")
    import time as _t
    t0 = _t.time()
    try:
        with urllib.request.urlopen(req, timeout=120) as resp:
            data = json.loads(resp.read().decode("utf-8"))
        _log_chat(payload.get("session_id", "web"), payload.get("message", ""),
                  data.get("reply", ""), data.get("mode", ""), int((_t.time() - t0) * 1000))
        return jsonify(data)
    except (urllib.error.URLError, OSError, ValueError) as exc:
        current_app.logger.warning("AI service lỗi: %s", exc)
        fallback = "Xin lỗi, Trợ lý AI tạm thời không phản hồi. Vui lòng gọi hotline 0945 457 512 ạ."
        _log_chat(payload.get("session_id", "web"), payload.get("message", ""), fallback, "offline",
                  int((_t.time() - t0) * 1000))
        return jsonify({"reply": fallback, "mode": "offline"}), 200



# Route phân tích ảnh răng (tách file cho gọn)
from ._vision_proxy import register as _register_vision  # noqa: E402
_register_vision(api_bp)

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

from .extensions import csrf
from .models import Product

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
    try:
        with urllib.request.urlopen(url, timeout=8) as resp:
            data = json.loads(resp.read().decode("utf-8"))
        cur = data.get("current", {})
        return jsonify({
            "success": True, "city": "TP. Hồ Chí Minh",
            "temp": cur.get("temperature_2m"),
            "desc": codes.get(cur.get("weather_code"), "Đang cập nhật"),
        })
    except (urllib.error.URLError, OSError, ValueError) as exc:
        current_app.logger.warning("Weather API lỗi: %s", exc)
        return jsonify({"success": False, "city": "TP. Hồ Chí Minh"}), 200


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
    }).encode("utf-8")

    url = current_app.config["AI_SERVICE_URL"].rstrip("/") + "/chat"
    req = urllib.request.Request(url, data=body,
                                 headers={"Content-Type": "application/json"}, method="POST")
    try:
        with urllib.request.urlopen(req, timeout=120) as resp:
            data = json.loads(resp.read().decode("utf-8"))
        return jsonify(data)
    except (urllib.error.URLError, OSError, ValueError) as exc:
        current_app.logger.warning("AI service lỗi: %s", exc)
        return jsonify({
            "reply": "Xin lỗi, Trợ lý AI tạm thời không phản hồi. Vui lòng gọi hotline 0945 457 512 ạ.",
            "mode": "offline",
        }), 200



# Route phân tích ảnh răng (tách file cho gọn)
from ._vision_proxy import register as _register_vision  # noqa: E402
_register_vision(api_bp)

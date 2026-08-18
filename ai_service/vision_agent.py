"""
vision_agent.py — Phân tích ẢNH RĂNG bằng mô hình đa phương thức (Gemini Vision).

Khách chụp ảnh răng gửi lên -> AI mô tả sơ bộ những gì quan sát được (ố vàng,
mảng bám, răng lệch, sưng nướu...) và GỢI Ý dịch vụ NALI phù hợp để đặt lịch.

Nguyên tắc an toàn y tế (rất quan trọng):
  * KHÔNG chẩn đoán bệnh, KHÔNG kê đơn. Chỉ nhận xét sơ bộ + khuyên đến khám.
  * Luôn kèm câu miễn trừ trách nhiệm.
  * Ảnh không phải răng miệng -> từ chối lịch sự.
"""
from __future__ import annotations

import base64
import json
import re

from config import settings
from knowledge import _format_price, services_catalog

VISION_MODEL = "gemini-1.5-flash"   # nhanh, miễn phí, hỗ trợ ảnh

_PROMPT = """Bạn là trợ lý hình ảnh của phòng khám Nha khoa NALI. Hãy xem ảnh khách gửi.

Yêu cầu trả lời DUY NHẤT một JSON (không thêm chữ nào ngoài JSON) theo mẫu:
{
  "la_anh_rang_mieng": true/false,
  "quan_sat": ["nhận xét ngắn 1", "nhận xét ngắn 2", "..."],
  "muc_do": "binh_thuong" | "nen_kham" | "nen_kham_som",
  "dich_vu_goi_y": ["tên dịch vụ 1", "tên dịch vụ 2"],
  "loi_khuyen": "1-2 câu lời khuyên thân thiện, tiếng Việt"
}

Danh sách dịch vụ NALI có thể gợi ý (chỉ chọn trong danh sách này):
{services}

Quy tắc:
- Chỉ mô tả những gì NHÌN THẤY (màu răng, mảng bám/cao răng, răng lệch/thưa/mẻ, nướu đỏ/sưng...). Không đoán bệnh cụ thể, không dùng từ chẩn đoán như "bạn bị viêm nha chu".
- Nếu ảnh mờ, tối, hoặc không phải răng miệng: đặt la_anh_rang_mieng=false, quan_sat=[lý do], các trường khác để rỗng/binh_thuong.
- Giọng nhẹ nhàng, không gây hoang mang. Tiếng Việt.
"""

_JSON_RE = re.compile(r"\{.*\}", re.DOTALL)


def _services_text() -> str:
    lines = []
    for s in services_catalog():
        lines.append(f"- {s['name']} ({_format_price(s.get('price', 0))})")
    return "\n".join(lines)


def _match_services(names: list[str]) -> list[dict]:
    """Khớp tên dịch vụ AI gợi ý với catalog thật để trả kèm giá."""
    catalog = services_catalog()
    out = []
    for n in names or []:
        key = (n or "").strip().lower()
        for s in catalog:
            if key and (key in s["name"].lower() or s["name"].lower() in key):
                out.append({"ten": s["name"], "gia_text": _format_price(s.get("price", 0)),
                            "id": s.get("id")})
                break
    return out


def analyze_dental_image(image_bytes: bytes, mime_type: str = "image/jpeg") -> dict:
    """Phân tích ảnh răng. Trả về dict có cấu trúc, luôn kèm miễn trừ trách nhiệm."""
    disclaimer = ("Đây chỉ là nhận xét sơ bộ từ hình ảnh, KHÔNG phải chẩn đoán y khoa. "
                  "Vui lòng đến NALI để bác sĩ thăm khám trực tiếp.")

    if not settings.has_gemini:
        return {"success": False,
                "message": "Tính năng phân tích ảnh cần Gemini API (chưa cấu hình GEMINI_API_KEY).",
                "mien_tru": disclaimer}

    import google.generativeai as genai
    genai.configure(api_key=settings.gemini_api_key)
    model = genai.GenerativeModel(VISION_MODEL)

    prompt = _PROMPT.replace("{services}", _services_text())
    try:
        resp = model.generate_content(
            [prompt, {"mime_type": mime_type, "data": base64.b64encode(image_bytes).decode()}],
            generation_config={"temperature": 0.2, "max_output_tokens": 600},
        )
        text = (resp.text or "").strip()
    except Exception as exc:  # noqa: BLE001
        return {"success": False, "message": f"Không phân tích được ảnh: {exc}", "mien_tru": disclaimer}

    m = _JSON_RE.search(text)
    try:
        data = json.loads(m.group(0)) if m else {}
    except json.JSONDecodeError:
        data = {}

    if not data:
        return {"success": False, "message": "AI không trả về kết quả hợp lệ, thử ảnh rõ hơn nhé.",
                "mien_tru": disclaimer}

    is_dental = bool(data.get("la_anh_rang_mieng", False))
    return {
        "success": True,
        "la_anh_rang_mieng": is_dental,
        "quan_sat": data.get("quan_sat") or [],
        "muc_do": data.get("muc_do") or "binh_thuong",
        "dich_vu_goi_y": _match_services(data.get("dich_vu_goi_y") or []) if is_dental else [],
        "loi_khuyen": data.get("loi_khuyen") or "",
        "mien_tru": disclaimer,
    }

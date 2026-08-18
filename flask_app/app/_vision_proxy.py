"""
_vision_proxy.py — Route proxy phân tích ảnh răng (tách file để dễ đọc).
Nhận ảnh từ widget -> chuyển tiếp multipart sang AI service -> trả JSON.
"""
import json
import urllib.error
import urllib.request
import uuid

from flask import current_app, jsonify, request

from .extensions import csrf

CRLF = "\r\n"
ALLOWED = ("image/jpeg", "image/png", "image/webp")
MAX_BYTES = 5 * 1024 * 1024


def register(api_bp):
    @api_bp.route("/analyze-image", methods=["POST"])
    @csrf.exempt
    def analyze_image_proxy():
        """AI đọc ảnh răng: chỉ nhận xét sơ bộ, không chẩn đoán. Giới hạn 5MB, JPG/PNG/WebP."""
        f = request.files.get("file")
        if not f or f.mimetype not in ALLOWED:
            return jsonify({"success": False, "message": "Vui lòng chọn ảnh JPG/PNG/WebP."}), 400
        data = f.read()
        if len(data) > MAX_BYTES:
            return jsonify({"success": False, "message": "Ảnh quá lớn (tối đa 5MB)."}), 413

        boundary = uuid.uuid4().hex
        head = (
            f"--{boundary}{CRLF}"
            f'Content-Disposition: form-data; name="file"; filename="upload"{CRLF}'
            f"Content-Type: {f.mimetype}{CRLF}{CRLF}"
        ).encode()
        tail = f"{CRLF}--{boundary}--{CRLF}".encode()
        body = head + data + tail

        url = current_app.config["AI_SERVICE_URL"].rstrip("/") + "/analyze-image"
        req = urllib.request.Request(
            url, data=body, method="POST",
            headers={"Content-Type": f"multipart/form-data; boundary={boundary}"},
        )
        try:
            with urllib.request.urlopen(req, timeout=120) as resp:
                return jsonify(json.loads(resp.read().decode("utf-8")))
        except urllib.error.HTTPError as exc:
            try:
                detail = json.loads(exc.read().decode("utf-8")).get("detail", "")
            except Exception:  # noqa: BLE001
                detail = ""
            return jsonify({"success": False, "message": detail or "AI không xử lý được ảnh."}), 200
        except (urllib.error.URLError, OSError, ValueError) as exc:
            current_app.logger.warning("AI vision lỗi: %s", exc)
            return jsonify({"success": False, "message": "Trợ lý AI tạm thời không phản hồi."}), 200

"""
local_llm_agent.py — Agent dùng LLM MỞ TỰ HOST (Qwen2.5-3B finetune).

Gọi model qua chuẩn OpenAI-compatible nên chạy được với nhiều "máy chủ model":
Ollama (http://localhost:11434/v1), llama.cpp server, vLLM, HuggingFace TGI...

Vì model nhỏ (3B) không có function-calling gốc như Gemini, ta tự cài một giao
thức tool-calling đơn giản bằng JSON: model trả về {"tool": "...", "args": {...}}
khi cần hành động (tra dịch vụ, xem giờ trống, đặt lịch); ngược lại trả lời thường.
Đây chính là kỹ thuật "ReAct/JSON action" — điểm nhấn kỹ thuật cho đồ án.
"""
from __future__ import annotations

import json
import re
import urllib.error
import urllib.request

from config import settings
from fallback_agent import _BOOK_KEYWORDS, FallbackAgent
from retriever import Retriever
from tools import _strip_accents, dat_lich_hen, kiem_tra_lich_trong, tim_dich_vu

# Ánh xạ tên tool -> hàm thật trong tools.py
_TOOLS = {
    "tim_dich_vu": tim_dich_vu,
    "kiem_tra_lich_trong": kiem_tra_lich_trong,
    "dat_lich_hen": dat_lich_hen,
}

SYSTEM_PROMPT = """Bạn là "NALI Trợ Lý", trợ lý ảo của phòng khám Nha khoa NALI.
Nhiệm vụ: tư vấn dịch vụ nha khoa và giúp khách ĐẶT LỊCH HẸN.

QUY TẮC:
- Luôn trả lời bằng tiếng Việt, thân thiện, ngắn gọn; xưng "NALI", gọi khách "anh/chị".
- Chỉ dựa vào [DỮ LIỆU NALI] được cung cấp; không bịa. Không chẩn đoán bệnh.
- Bạn có các CÔNG CỤ. Khi cần dùng, hãy trả về DUY NHẤT một dòng JSON (không thêm chữ nào khác):
    {"tool": "tim_dich_vu", "args": {"tu_khoa": "..."}}
    {"tool": "kiem_tra_lich_trong", "args": {"ngay": "..."}}
    {"tool": "dat_lich_hen", "args": {"ho_ten": "...", "so_dien_thoai": "...", "ngay": "...", "gio": "...", "dich_vu": "..."}}
- CHỈ gọi dat_lich_hen khi đã có ĐỦ: họ tên + số điện thoại + ngày + giờ. Thiếu gì thì HỎI khách, đừng gọi tool.
- Khi KHÔNG cần công cụ, trả lời khách bình thường (KHÔNG kèm JSON).
- Sau khi nhận [KẾT QUẢ CÔNG CỤ], hãy viết câu trả lời tự nhiên cho khách."""

_JSON_RE = re.compile(r"\{.*\}", re.DOTALL)


def extract_tool_call(text: str) -> dict | None:
    """Rút lời gọi công cụ dạng JSON từ câu trả lời của model.

    Trả về {"tool": str, "args": dict} nếu hợp lệ, ngược lại None.
    Chịu được trường hợp model bọc JSON trong ```json ... ``` hoặc lẫn text.
    """
    if not text or "{" not in text:
        return None
    candidate = text.strip()
    if candidate.startswith("```"):
        candidate = candidate.strip("`")
        candidate = re.sub(r"^json", "", candidate.strip(), flags=re.IGNORECASE).strip()
    m = _JSON_RE.search(candidate)
    if not m:
        return None
    try:
        obj = json.loads(m.group(0))
    except (json.JSONDecodeError, ValueError):
        return None
    tool = obj.get("tool")
    if isinstance(tool, str) and tool in _TOOLS:
        args = obj.get("args") if isinstance(obj.get("args"), dict) else {}
        return {"tool": tool, "args": args}
    return None


def _chat_completion(messages: list[dict], *, temperature: float = 0.3,
                     max_tokens: int = 512, timeout: int = 60) -> str:
    """Gọi endpoint OpenAI-compatible /chat/completions, trả về nội dung text."""
    url = settings.local_llm_url.rstrip("/") + "/chat/completions"
    payload = json.dumps({
        "model": settings.local_llm_model,
        "messages": messages,
        "temperature": temperature,
        "max_tokens": max_tokens,
        "stream": False,
    }).encode("utf-8")
    req = urllib.request.Request(
        url, data=payload,
        headers={
            "Content-Type": "application/json",
            "Authorization": f"Bearer {settings.local_llm_key}",
        },
        method="POST",
    )
    with urllib.request.urlopen(req, timeout=timeout) as resp:
        data = json.loads(resp.read().decode("utf-8"))
    return data["choices"][0]["message"]["content"] or ""


def local_llm_available(require_model: bool = True) -> bool:
    """True nếu endpoint LLM tự host phản hồi (và có model nếu require_model).

    require_model=True (mặc định, dùng cho chế độ 'auto'): kiểm tra cả sự tồn tại
    của LOCAL_LLM_MODEL để không chọn 'local' khi Ollama chạy nhưng chưa nạp model.
    require_model=False (chế độ 'local' tường minh): chỉ cần endpoint sống — hợp với
    llama.cpp server (id model có thể khác tên cấu hình).
    """
    url = settings.local_llm_url.rstrip("/") + "/models"
    try:
        req = urllib.request.Request(
            url, headers={"Authorization": f"Bearer {settings.local_llm_key}"}
        )
        with urllib.request.urlopen(req, timeout=4) as resp:
            if resp.status != 200:
                return False
            data = json.loads(resp.read().decode("utf-8"))
    except (urllib.error.URLError, OSError, ValueError):
        return False
    if not require_model:
        return True
    want = settings.local_llm_model
    # Ollama may return `"data": null` while it has no OpenAI-compatible
    # models yet.  Treat that exactly like an empty catalog so `auto` falls
    # back to Gemini/offline instead of crashing the whole API at startup.
    models = data.get("data") or []
    if not isinstance(models, list):
        return False
    ids = [m.get("id", "") for m in models if isinstance(m, dict)]
    return any(i == want or i.startswith(want + ":") or i.startswith(want) for i in ids)


class LocalLLMAgent:
    """Điều phối LLM tự host + RAG + vòng lặp tool-calling."""

    MAX_TOOL_STEPS = 4

    def __init__(self, retriever: Retriever) -> None:
        self.retriever = retriever
        # Lịch sử hội thoại rút gọn theo từng phiên (không gồm ngữ cảnh RAG)
        self._history: dict[str, list[dict]] = {}
        # Đặt lịch dùng máy trạng thái XÁC ĐỊNH (không phó mặc cho LLM 3B),
        # đảm bảo hành động ghi DB luôn chính xác. LLM lo phần tư vấn/hội thoại.
        self._booking = FallbackAgent(retriever)

    def reset(self, session_id: str) -> None:
        self._history.pop(session_id, None)
        self._booking.reset(session_id)

    def _history_for(self, session_id: str) -> list[dict]:
        return self._history.setdefault(session_id, [])

    def reply(self, session_id: str, message: str, user_context: str = "") -> str:
        # --- Định tuyến lai (hybrid): đặt lịch -> logic xác định; còn lại -> LLM ---
        booking_active = self._booking._state(session_id).active
        wants_booking = any(k in _strip_accents(message) for k in _BOOK_KEYWORDS)
        if booking_active or wants_booking:
            return self._booking.reply(session_id, message, user_context=user_context)
        # Hỏi về hồ sơ khám/dặn dò/tái khám -> trả lời xác định từ dữ liệu (model 3B hay bịa chi tiết y khoa)
        rec = FallbackAgent.record_answer(message, user_context)
        if rec:
            return rec

        history = self._history_for(session_id)
        context = self.retriever.context_for(message, k=4)
        who = f"[THÔNG TIN KHÁCH ĐÃ ĐĂNG NHẬP]\n{user_context}\n[HẾT]\n\n" if user_context else ""
        user_turn = (
            f"{who}[DỮ LIỆU NALI]\n{context}\n[HẾT DỮ LIỆU]\n\n"
            f"Khách hỏi: {message}"
        )

        # Dựng messages: system + lịch sử + lượt hiện tại
        messages = [{"role": "system", "content": SYSTEM_PROMPT}]
        messages.extend(history[-8:])  # giữ tối đa 8 lượt gần nhất
        messages.append({"role": "user", "content": user_turn})

        final_text = ""
        for _ in range(self.MAX_TOOL_STEPS):
            reply = _chat_completion(messages).strip()
            call = extract_tool_call(reply)
            if not call:
                final_text = reply
                break
            # Thực thi công cụ và đưa kết quả lại cho model
            try:
                result = _TOOLS[call["tool"]](**call["args"])
            except TypeError as exc:
                result = {"loi": f"Tham số không hợp lệ: {exc}"}
            messages.append({"role": "assistant", "content": reply})
            messages.append({
                "role": "user",
                "content": f"[KẾT QUẢ CÔNG CỤ {call['tool']}]\n"
                           f"{json.dumps(result, ensure_ascii=False)}\n"
                           f"Hãy trả lời khách bằng tiếng Việt tự nhiên dựa trên kết quả này.",
            })
        else:
            # Hết số bước mà vẫn đòi gọi tool -> lấy câu trả lời cuối
            final_text = _chat_completion(messages).strip()

        final_text = final_text or "Dạ NALI chưa rõ ý, anh/chị nói lại giúp em nhé ạ."
        # Lưu lịch sử (bản gọn: câu hỏi gốc + câu trả lời)
        history.append({"role": "user", "content": message})
        history.append({"role": "assistant", "content": final_text})
        return final_text

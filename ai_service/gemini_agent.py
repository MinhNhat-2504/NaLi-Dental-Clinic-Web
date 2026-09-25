"""
gemini_agent.py — Agent AI online dùng Google Gemini + Function Calling.

Gemini lo phần tư vấn và tự quyết định khi nào cần gọi công cụ tra cứu:
  * Khách hỏi giá/dịch vụ  -> gọi tim_dich_vu()
  * Khách hỏi giờ trống    -> gọi kiem_tra_lich_trong()
Việc ĐẶT LỊCH không giao cho Gemini: giống backend local, ý định đặt lịch được nhận diện
bằng luật và chuyển sang máy trạng thái slot-filling trong FallbackAgent, để hai backend
hành xử giống nhau và LLM không tự điền tham số ghi vào database.

RAG: trước mỗi câu hỏi, ta truy hồi tri thức liên quan (retriever) và chèn vào
prompt để câu trả lời bám sát dữ liệu thật của phòng khám, tránh "bịa".
"""
from __future__ import annotations

from datetime import date

import google.generativeai as genai

from config import settings
from retriever import Retriever
from fallback_agent import FallbackAgent, wants_booking
from tools import _strip_accents, kiem_tra_lich_trong, tim_dich_vu

SYSTEM_INSTRUCTION = """Bạn là "NALI Trợ Lý", trợ lý ảo của phòng khám Nha khoa NALI.
Nhiệm vụ: tư vấn dịch vụ nha khoa và giúp khách ĐẶT LỊCH HẸN.

Nguyên tắc:
- Luôn trả lời bằng tiếng Việt, thân thiện, ngắn gọn, xưng "NALI" và gọi khách là "anh/chị".
- Chỉ dùng thông tin trong phần [DỮ LIỆU NALI] được cung cấp; nếu không có thì nói chưa có thông tin và mời gọi hotline 0945 457 512.
- Bạn KHÔNG phải bác sĩ, không chẩn đoán bệnh. Với triệu chứng, hãy gợi ý dịch vụ phù hợp và khuyên đến khám.
- Bạn KHÔNG tự đặt lịch. Khi khách muốn đặt lịch, mời khách nhắn đúng chữ "đặt lịch" để hệ thống
  hướng dẫn từng bước (họ tên, số điện thoại, ngày, giờ).
- Có thể dùng công cụ kiem_tra_lich_trong để gợi ý giờ còn trống khi khách phân vân.
"""


class GeminiAgent:
    """Quản lý phiên hội thoại và điều phối Gemini + công cụ."""

    def __init__(self, retriever: Retriever) -> None:
        self.retriever = retriever
        genai.configure(api_key=settings.gemini_api_key)
        today = date.today().isoformat()
        self.model = genai.GenerativeModel(
            model_name=settings.gemini_model,
            system_instruction=SYSTEM_INSTRUCTION + f"\n\nHôm nay là ngày {today}.",
            tools=[tim_dich_vu, kiem_tra_lich_trong],
        )
        # Mỗi session_id giữ một phiên chat riêng để nhớ ngữ cảnh hội thoại
        self._chats: dict[str, "genai.ChatSession"] = {}
        # Đặt lịch đi qua máy trạng thái xác định, dùng chung với backend local
        self._booking = FallbackAgent(retriever)

    def _chat_for(self, session_id: str) -> "genai.ChatSession":
        if session_id not in self._chats:
            # Bật function calling tự động: SDK tự thực thi các tool Python
            self._chats[session_id] = self.model.start_chat(
                enable_automatic_function_calling=True
            )
        return self._chats[session_id]

    def reply(self, session_id: str, message: str, user_context: str = "") -> str:
        """Nhận tin nhắn khách -> trả câu trả lời của trợ lý.

        user_context: ngữ cảnh khách đã đăng nhập (tên, lịch hẹn sắp tới...) do web
        cung cấp — giúp bot chào đúng tên, nhắc lịch, gợi ý tái khám.
        """
        # Đặt lịch (đang trong luồng, hoặc khách vừa yêu cầu) -> slot-filling xác định, không qua Gemini
        if self._booking._state(session_id).active or wants_booking(_strip_accents(message)):
            return self._booking.reply(session_id, message, user_context=user_context)
        # Hỏi về hồ sơ khám/dặn dò/tái khám -> trả lời XÁC ĐỊNH từ dữ liệu, không để LLM diễn giải y khoa
        rec = FallbackAgent.record_answer(message, user_context)
        if rec:
            return rec
        context = self.retriever.context_for(message, k=4)
        who = f"[THÔNG TIN KHÁCH ĐÃ ĐĂNG NHẬP]\n{user_context}\n[HẾT]\n\n" if user_context else ""
        augmented = (
            f"{who}[DỮ LIỆU NALI]\n{context}\n[HẾT DỮ LIỆU]\n\n"
            f"Câu hỏi của khách: {message}"
        )
        chat = self._chat_for(session_id)
        response = chat.send_message(augmented)
        return (response.text or "").strip() or (
            "Dạ NALI chưa rõ ý anh/chị, anh/chị nói lại giúp em nhé ạ."
        )

    def reply_stream(self, session_id: str, message: str, user_context: str = ""):
        """Gemini đang bật automatic function calling (không stream được kèm tool) -> lấy câu trả lời
        đầy đủ rồi cắt cụm từ để giao diện hiện dần. Vẫn nhanh hơn cảm giác chờ 'cục' vì widget hiện ngay."""
        from fallback_agent import chunk_text
        yield from chunk_text(self.reply(session_id, message, user_context=user_context))

    def reset(self, session_id: str) -> None:
        """Xoá lịch sử hội thoại của một phiên."""
        self._chats.pop(session_id, None)
        self._booking.reset(session_id)

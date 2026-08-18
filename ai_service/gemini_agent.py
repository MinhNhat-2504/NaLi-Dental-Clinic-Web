"""
gemini_agent.py — Agent AI online dùng Google Gemini + Function Calling.

Đây là "bộ não" chính. Gemini tự quyết định khi nào cần gọi công cụ:
  * Khách hỏi giá/dịch vụ  -> gọi tim_dich_vu()
  * Khách hỏi giờ trống    -> gọi kiem_tra_lich_trong()
  * Khách muốn đặt lịch     -> thu thập đủ thông tin rồi gọi dat_lich_hen()

RAG: trước mỗi câu hỏi, ta truy hồi tri thức liên quan (retriever) và chèn vào
prompt để câu trả lời bám sát dữ liệu thật của phòng khám, tránh "bịa".
"""
from __future__ import annotations

from datetime import date

import google.generativeai as genai

from config import settings
from retriever import Retriever
from tools import dat_lich_hen, kiem_tra_lich_trong, tim_dich_vu

SYSTEM_INSTRUCTION = """Bạn là "NALI Trợ Lý", trợ lý ảo của phòng khám Nha khoa NALI.
Nhiệm vụ: tư vấn dịch vụ nha khoa và giúp khách ĐẶT LỊCH HẸN.

Nguyên tắc:
- Luôn trả lời bằng tiếng Việt, thân thiện, ngắn gọn, xưng "NALI" và gọi khách là "anh/chị".
- Chỉ dùng thông tin trong phần [DỮ LIỆU NALI] được cung cấp; nếu không có thì nói chưa có thông tin và mời gọi hotline 0945 457 512.
- Bạn KHÔNG phải bác sĩ, không chẩn đoán bệnh. Với triệu chứng, hãy gợi ý dịch vụ phù hợp và khuyên đến khám.
- Khi khách muốn đặt lịch, hãy hỏi cho đủ: HỌ TÊN, SỐ ĐIỆN THOẠI, NGÀY, GIỜ (và dịch vụ nếu có).
  Chỉ gọi công cụ dat_lich_hen khi đã có đủ họ tên + số điện thoại + ngày + giờ.
- Sau khi đặt lịch thành công, nhắc lại mã lịch hẹn và thông tin để khách yên tâm.
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
            tools=[tim_dich_vu, kiem_tra_lich_trong, dat_lich_hen],
        )
        # Mỗi session_id giữ một phiên chat riêng để nhớ ngữ cảnh hội thoại
        self._chats: dict[str, "genai.ChatSession"] = {}

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
        # Hỏi về hồ sơ khám/dặn dò/tái khám -> trả lời XÁC ĐỊNH từ dữ liệu, không để LLM diễn giải y khoa
        from fallback_agent import FallbackAgent
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

    def reset(self, session_id: str) -> None:
        """Xoá lịch sử hội thoại của một phiên."""
        self._chats.pop(session_id, None)

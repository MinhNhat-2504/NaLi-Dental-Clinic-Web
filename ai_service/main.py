"""
main.py — FastAPI server của Trợ lý AI NALI.

Khởi tạo một lần: bộ retriever (RAG) + agent chính. Phục vụ:
  * POST /chat   -> nhận tin nhắn, trả câu trả lời của trợ lý.
  * POST /reset  -> xoá ngữ cảnh một phiên chat.
  * GET  /health -> trạng thái hệ thống (DB, chế độ AI, backend RAG).
  * GET  /docs   -> tài liệu API tự sinh (Swagger UI) — rất tiện khi demo.

Chọn "bộ não" theo LLM_BACKEND (auto|local|gemini|offline):
  local  = Qwen2.5-3B finetune tự host (Ollama/llama.cpp) — ưu tiên cho đồ án.
  gemini = Google Gemini API.
  offline= luật + TF-IDF, không cần mạng.
Dù chọn gì, nếu agent chính lỗi giữa chừng sẽ tự chuyển offline để không gián đoạn.
"""
from __future__ import annotations

import logging
import sys

# Ép stdout/stderr sang UTF-8 để log tiếng Việt không lỗi trên console Windows
for _stream in (sys.stdout, sys.stderr):
    try:
        _stream.reconfigure(encoding="utf-8")  # type: ignore[attr-defined]
    except Exception:
        pass

from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel, Field

from config import settings
from database import db_available
from fallback_agent import FallbackAgent
from gemini_agent import GeminiAgent
from local_llm_agent import LocalLLMAgent, local_llm_available
from retriever import Retriever

logging.basicConfig(level=logging.INFO, format="%(asctime)s [%(levelname)s] %(message)s")
logger = logging.getLogger("nali-ai")

app = FastAPI(
    title="NALI Dental — Trợ lý AI",
    description="Chatbot RAG + đặt lịch (LLM tự host Qwen2.5 / Gemini / offline).",
    version="2.0.0",
)

# Cho phép web PHP (localhost) gọi sang service này
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],  # Demo cục bộ; production nên giới hạn origin cụ thể
    allow_methods=["*"],
    allow_headers=["*"],
)


class _State:
    """Giữ các đối tượng khởi tạo một lần khi server bật."""
    retriever: Retriever | None = None
    primary = None           # agent chính (local/gemini) — có .reply(), .reset()
    primary_mode: str = "offline"
    fallback: FallbackAgent | None = None


state = _State()


def _select_primary(retriever: Retriever):
    """Chọn agent chính theo LLM_BACKEND. Trả về (agent|None, mode)."""

    def try_local(strict: bool = True):
        # Chế độ 'local' tường minh: chỉ cần endpoint sống (strict=False)
        if settings.has_local_llm and local_llm_available(require_model=strict):
            try:
                return LocalLLMAgent(retriever), "local"
            except Exception as exc:  # noqa: BLE001
                logger.warning("Không khởi tạo được LocalLLM (%s).", exc)
        return None

    def try_gemini():
        if settings.has_gemini:
            try:
                return GeminiAgent(retriever), "gemini"
            except Exception as exc:  # noqa: BLE001
                logger.warning("Không khởi tạo được Gemini (%s).", exc)
        return None

    backend = settings.llm_backend
    if backend == "offline":
        return None, "offline"
    if backend == "local":
        return try_local(strict=False) or (None, "offline")
    if backend == "gemini":
        return try_gemini() or (None, "offline")
    # auto: ưu tiên local -> gemini -> offline
    return try_local() or try_gemini() or (None, "offline")


@app.on_event("startup")
def _startup() -> None:
    logger.info("Đang khởi tạo kho tri thức (RAG)...")
    state.retriever = Retriever()
    state.fallback = FallbackAgent(state.retriever)
    logger.info(
        "RAG sẵn sàng: backend=%s, %d tài liệu, dữ liệu DB thật=%s",
        state.retriever.backend, len(state.retriever.documents),
        state.retriever.used_live_db,
    )
    agent, mode = _select_primary(state.retriever)
    state.primary, state.primary_mode = agent, mode
    if mode == "local":
        logger.info("Bộ não chính: LLM TỰ HOST (%s @ %s).",
                    settings.local_llm_model, settings.local_llm_url)
    elif mode == "gemini":
        logger.info("Bộ não chính: Gemini (%s).", settings.gemini_model)
    else:
        logger.info("Bộ não chính: OFFLINE (luật + TF-IDF).")


# ----------------------------- Schemas -----------------------------
class ChatRequest(BaseModel):
    session_id: str = Field("guest", description="Định danh phiên chat (giữ ngữ cảnh).")
    message: str = Field(..., min_length=1, description="Tin nhắn của khách hàng.")


class ChatResponse(BaseModel):
    reply: str
    mode: str  # "local" | "gemini" | "offline"


# ----------------------------- Endpoints -----------------------------
@app.post("/chat", response_model=ChatResponse)
def chat(req: ChatRequest) -> ChatResponse:
    """Điểm vào chính: nhận câu hỏi, trả câu trả lời của trợ lý AI."""
    message = req.message.strip()

    if state.primary is not None:
        try:
            reply = state.primary.reply(req.session_id, message)
            return ChatResponse(reply=reply, mode=state.primary_mode)
        except Exception as exc:  # noqa: BLE001
            logger.warning("%s lỗi (%s) -> fallback offline.", state.primary_mode, exc)

    reply = state.fallback.reply(req.session_id, message)
    return ChatResponse(reply=reply, mode="offline")


@app.post("/reset")
def reset(req: ChatRequest) -> dict:
    """Xoá ngữ cảnh hội thoại của một phiên (bắt đầu lại từ đầu)."""
    if state.primary is not None:
        state.primary.reset(req.session_id)
    if state.fallback is not None:
        state.fallback.reset(req.session_id)
    return {"ok": True}


@app.get("/health")
def health() -> dict:
    """Trạng thái hệ thống — tiện để kiểm tra nhanh khi demo."""
    r = state.retriever
    return {
        "status": "ok",
        "ai_mode": state.primary_mode,
        "llm_backend_cfg": settings.llm_backend,
        "local_llm": {"url": settings.local_llm_url, "model": settings.local_llm_model,
                      "san_sang": local_llm_available()},
        "gemini_san_sang": settings.has_gemini,
        "rag_backend": r.backend if r else None,
        "docs_count": len(r.documents) if r else 0,
        "dung_du_lieu_db_that": r.used_live_db if r else False,
        "database_ket_noi": db_available(),
    }


@app.get("/")
def root() -> dict:
    return {"service": "NALI AI Assistant", "tai_lieu": "/docs", "suc_khoe": "/health"}


if __name__ == "__main__":
    import uvicorn

    uvicorn.run("main:app", host=settings.host, port=settings.port, reload=True)

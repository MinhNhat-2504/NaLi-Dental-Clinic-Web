"""
retriever.py — Bộ truy hồi (Retrieval) cho RAG.

Cung cấp tìm kiếm ngữ nghĩa trên corpus tri thức với 2 backend:

  * "embedding": dùng Gemini `text-embedding-004` để mã hoá câu hỏi & tài liệu
    thành vector, xếp hạng bằng cosine similarity. Thông minh nhất (hiểu nghĩa).
  * "tfidf":     dùng scikit-learn TF-IDF — chạy offline, không cần internet/API.

Backend được chọn tự động: có GEMINI_API_KEY -> thử "embedding", lỗi thì tụt về
"tfidf". Nhờ vậy chatbot luôn tìm kiếm được, kể cả khi mất mạng lúc demo.
"""
from __future__ import annotations

import numpy as np

from config import settings
from knowledge import Document, build_corpus


def _cosine_topk(query_vec: np.ndarray, matrix: np.ndarray, k: int) -> list[int]:
    """Trả về chỉ số của `k` vector trong `matrix` gần `query_vec` nhất (cosine)."""
    q = query_vec / (np.linalg.norm(query_vec) + 1e-9)
    m = matrix / (np.linalg.norm(matrix, axis=1, keepdims=True) + 1e-9)
    scores = m @ q
    return list(np.argsort(scores)[::-1][:k])


class Retriever:
    """Đánh chỉ mục corpus một lần rồi phục vụ nhiều truy vấn."""

    def __init__(self) -> None:
        self.documents, self.used_live_db = build_corpus()
        self.backend: str = "tfidf"
        self._doc_matrix: np.ndarray | None = None
        self._tfidf = None  # TfidfVectorizer khi dùng backend tfidf

        if settings.has_gemini:
            try:
                self._build_embeddings()
                self.backend = "embedding"
            except Exception:
                # Bất kỳ lỗi nào (mạng, quota, thư viện) -> tụt về TF-IDF
                self._build_tfidf()
        else:
            self._build_tfidf()

    # ---------- Backend 1: Gemini embeddings ----------
    def _embed(self, texts: list[str], task_type: str) -> np.ndarray:
        """Gọi Gemini embedding cho danh sách văn bản."""
        import google.generativeai as genai

        genai.configure(api_key=settings.gemini_api_key)
        vectors = []
        for t in texts:
            res = genai.embed_content(
                model="models/text-embedding-004",
                content=t,
                task_type=task_type,
            )
            vectors.append(res["embedding"])
        return np.array(vectors, dtype=np.float32)

    def _build_embeddings(self) -> None:
        texts = [d.as_text() for d in self.documents]
        self._doc_matrix = self._embed(texts, task_type="retrieval_document")

    # ---------- Backend 2: TF-IDF offline ----------
    def _build_tfidf(self) -> None:
        from sklearn.feature_extraction.text import TfidfVectorizer

        self.backend = "tfidf"
        texts = [d.as_text() for d in self.documents]
        # analyzer theo từ + char n-gram giúp khớp tốt tiếng Việt không dấu
        self._tfidf = TfidfVectorizer(ngram_range=(1, 2), min_df=1)
        self._doc_matrix = self._tfidf.fit_transform(texts).toarray().astype(np.float32)

    # ---------- API công khai ----------
    def search(self, query: str, k: int = 4) -> list[Document]:
        """Trả về `k` Document liên quan nhất tới `query`."""
        if not query.strip() or self._doc_matrix is None:
            return self.documents[:k]

        if self.backend == "embedding":
            q_vec = self._embed([query], task_type="retrieval_query")[0]
        else:
            q_vec = self._tfidf.transform([query]).toarray()[0].astype(np.float32)

        idxs = _cosine_topk(q_vec, self._doc_matrix, k)
        return [self.documents[i] for i in idxs]

    def context_for(self, query: str, k: int = 4) -> str:
        """Ghép các tài liệu liên quan thành một khối ngữ cảnh cho LLM."""
        docs = self.search(query, k)
        return "\n".join(f"- {d.title}: {d.content}" for d in docs)

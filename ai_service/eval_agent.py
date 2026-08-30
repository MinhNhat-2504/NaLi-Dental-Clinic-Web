"""
eval_agent.py — Bộ đo chất lượng chatbot: hỏi 30 câu chuẩn, chấm tự động, in điểm theo chủ đề.

Chạy:  python eval_agent.py                 (offline: agent luật + RAG, không cần API/DB)
       LLM_BACKEND=gemini python eval_agent.py   (đo bộ não thật đang dùng trên production)
       python eval_agent.py --min 80        (thoát mã 1 nếu dưới 80% -> dùng trong CI)

Cách chấm: câu trả lời được coi là ĐÚNG nếu chứa ít nhất 1 từ khoá mong đợi (so sánh
không dấu, không phân biệt hoa thường). Câu cần DB (giá dịch vụ) tự bỏ qua khi không có MySQL.
Kết quả ghi vào eval/last_result.json để đưa vào báo cáo / dashboard.
"""
from __future__ import annotations

import argparse
import json
import os
import sys
import time
from collections import defaultdict
from pathlib import Path

for _s in (sys.stdout, sys.stderr):
    try:
        _s.reconfigure(encoding="utf-8")  # type: ignore[attr-defined]
    except Exception:
        pass

from database import db_available
from fallback_agent import FallbackAgent
from retriever import Retriever
from tools import _strip_accents

HERE = Path(__file__).parent


def _norm(s: str) -> str:
    return _strip_accents(s or "").lower()


def build_agent(backend: str, retriever: Retriever):
    """Chọn agent theo LLM_BACKEND giống main.py; lỗi thì về offline."""
    if backend == "gemini":
        from gemini_agent import GeminiAgent
        return GeminiAgent(retriever), "gemini"
    if backend in ("local", "auto"):
        from local_llm_agent import LocalLLMAgent, local_llm_available
        if local_llm_available(require_model=(backend == "auto")):
            return LocalLLMAgent(retriever), "local"
        if backend == "auto" and os.getenv("GEMINI_API_KEY"):
            from gemini_agent import GeminiAgent
            return GeminiAgent(retriever), "gemini"
    return FallbackAgent(retriever), "offline"


def run(min_score: float | None = None, verbose: bool = True) -> dict:
    questions = json.loads((HERE / "eval" / "questions.json").read_text(encoding="utf-8"))
    retriever = Retriever()
    agent, mode = build_agent(os.getenv("LLM_BACKEND", "offline").lower(), retriever)
    has_db = db_available()
    results, by_topic = [], defaultdict(lambda: [0, 0])
    t_start = time.time()
    for i, item in enumerate(questions):
        if item.get("needs_db") and not has_db:
            results.append({**item, "skipped": True})
            continue
        sid = f"eval-{i}"
        ctx = item.get("user_context", "")
        t0 = time.time()
        if "flow" in item:                       # hội thoại nhiều lượt, chấm câu cuối
            for turn in item["flow"]:
                answer = agent.reply(sid, turn, user_context=ctx)
            q = " -> ".join(item["flow"])
        else:
            q = item["q"]
            answer = agent.reply(sid, q, user_context=ctx)
        ms = int((time.time() - t0) * 1000)
        ok = any(_norm(k) in _norm(answer) for k in item["expect"])
        by_topic[item["topic"]][0] += ok
        by_topic[item["topic"]][1] += 1
        results.append({"topic": item["topic"], "q": q, "ok": ok, "ms": ms, "answer": answer[:300]})
        if verbose:
            print(f"  {'PASS' if ok else 'FAIL'}  [{item['topic']}] {q[:60]}  ({ms} ms)")
            if not ok:
                print(f"        -> {answer[:160].replace(chr(10), ' ')}")
    graded = [r for r in results if not r.get("skipped")]
    passed = sum(r["ok"] for r in graded)
    score = round(100 * passed / len(graded), 1) if graded else 0.0
    summary = {
        "mode": mode, "db": has_db, "total": len(graded), "passed": passed, "score": score,
        "skipped": len(results) - len(graded),
        "avg_ms": int(sum(r["ms"] for r in graded) / len(graded)) if graded else 0,
        "by_topic": {t: {"passed": v[0], "total": v[1]} for t, v in sorted(by_topic.items())},
        "elapsed_s": round(time.time() - t_start, 1),
        "results": results,
    }
    (HERE / "eval" / "last_result.json").write_text(json.dumps(summary, ensure_ascii=False, indent=2), encoding="utf-8")
    if verbose:
        print("\n  Chủ đề            Đúng/Tổng")
        for t, v in summary["by_topic"].items():
            print(f"  {t:<18}{v['passed']}/{v['total']}")
        print(f"\n===== CHẤT LƯỢNG BOT ({mode}{', không DB' if not has_db else ''}): "
              f"{passed}/{len(graded)} = {score}%  | trung bình {summary['avg_ms']} ms/câu"
              f"{' | bỏ qua ' + str(summary['skipped']) + ' câu cần DB' if summary['skipped'] else ''} =====")
    if min_score is not None and score < min_score:
        print(f"Dưới ngưỡng {min_score}% -> FAIL")
        sys.exit(1)
    return summary


if __name__ == "__main__":
    ap = argparse.ArgumentParser()
    ap.add_argument("--min", type=float, default=None, help="ngưỡng %% tối thiểu, dưới thì exit 1")
    ap.add_argument("--quiet", action="store_true")
    a = ap.parse_args()
    run(a.min, verbose=not a.quiet)

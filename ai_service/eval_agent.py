"""
eval_agent.py — Bộ đo chất lượng chatbot: hỏi 30 câu chuẩn, chấm tự động, in điểm theo chủ đề.

Chạy:  python eval_agent.py                          offline, chấm bằng từ khoá (không cần API/DB)
       LLM_BACKEND=gemini python eval_agent.py --judge gemini
                                                    đo backend Gemini, chấm bằng LLM-as-judge
       python eval_agent.py --min 80                 thoát mã 1 nếu dưới 80% (dùng trong CI)
       python eval_agent.py --history                lưu thêm bản ghi eval/history/<thời gian>_<backend>_<judge>_<sha>.json
       python eval_agent.py --sample 12              chỉ chạy 12 câu, chia đều theo chủ đề (tiết kiệm quota)

Hai cách chấm:
  * keyword: câu trả lời ĐÚNG nếu chứa ít nhất 1 từ khoá mong đợi (so sánh không dấu). Nhanh, offline,
    nhưng không bắt được câu "đúng từ khoá, sai nghĩa".
  * gemini (LLM-as-judge): gom 10 câu một lượt, đưa câu hỏi + ý mong đợi + câu trả lời cho Gemini, nhận về
    JSON [{"i", "dung", "ly_do"}]. Chấm được ngữ nghĩa và mâu thuẫn. Luôn ghi kèm điểm keyword để so sánh.
    Model giám khảo đặt bằng GEMINI_JUDGE_MODEL (mặc định gemini-3.5-flash-lite: hạn mức free cao hơn
    model flagship, và khác model của chatbot để bớt thiên vị).
Quota: gói free của Gemini tính theo NGÀY cho từng model (gemini-3.6-flash: 20 lượt/ngày), nên đo backend
Gemini không nên chạy mỗi lần push; CI chạy hàng tuần với GEMINI_MODEL là model lite.
Câu cần DB (giá dịch vụ) tự bỏ qua khi không có MySQL. Kết quả ghi vào eval/last_result.json.
"""
from __future__ import annotations

import argparse
import json
import os
import random
import subprocess
import sys
import time
from collections import defaultdict
from datetime import datetime
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
JUDGE_BATCH = 10

JUDGE_PROMPT = """Bạn là giám khảo chấm câu trả lời của chatbot phòng khám nha khoa NALI.
Với MỖI mục dưới đây, chấm ĐÚNG khi: câu trả lời truyền đạt đúng ít nhất một ý mong đợi (không cần khớp
từng chữ), không nêu thông tin trái ngược với các ý đó, và không bịa thêm số liệu cụ thể (giá, giờ, địa chỉ)
khác với ý mong đợi. Nếu ý mong đợi là kiểu "chưa có thông tin / gọi hotline" và chatbot từ chối lịch sự
thì ĐÚNG. Nếu chatbot hỏi lại đúng bước mà ý mong đợi yêu cầu (ví dụ hỏi họ tên) thì ĐÚNG.

{items}

Trả về đúng một mảng JSON, mỗi phần tử: {{"i": <số thứ tự>, "dung": true hoặc false, "ly_do": "một câu ngắn"}}.
Phải có đủ {n} phần tử, không thêm chữ nào ngoài JSON."""

ITEM_TEMPLATE = """### Mục {i}
Câu hỏi của khách: {question}
Các ý mong đợi:
{expect}
Câu trả lời của chatbot:
{answer}
"""


def _norm(s: str) -> str:
    return _strip_accents(s or "").lower()


def _git_sha() -> str:
    try:
        return subprocess.check_output(["git", "rev-parse", "--short", "HEAD"], cwd=HERE, text=True,
                                       stderr=subprocess.DEVNULL).strip()
    except Exception:  # noqa: BLE001
        return os.getenv("GITHUB_SHA", "")[:7] or "unknown"


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


def _is_rate_limited(exc: Exception) -> bool:
    msg = str(exc).lower()
    return any(k in msg for k in ("429", "quota", "resource", "exhausted", "rate"))


def _is_daily_quota(exc: Exception) -> bool:
    return "perday" in str(exc).lower().replace(" ", "")


def _with_retry(fn, tries: int = 3, wait: float = 30.0):
    """Gemini free tier trả 429 theo phút thì đợi rồi thử lại; hết quota theo NGÀY thì dừng ngay."""
    for i in range(tries):
        try:
            return fn()
        except Exception as exc:  # noqa: BLE001
            if _is_daily_quota(exc):
                raise RuntimeError("Hết hạn mức Gemini trong ngày cho model này. "
                                   "Đổi GEMINI_MODEL/GEMINI_JUDGE_MODEL sang model lite hoặc chạy lại ngày mai.") from exc
            if i == tries - 1 or not _is_rate_limited(exc):
                raise
            time.sleep(wait * (i + 1))


def parse_verdict(raw: str) -> tuple[bool, str]:
    """Đọc JSON giám khảo cho MỘT câu; chịu được ```json ...``` hoặc rác quanh JSON."""
    txt = _strip_fence(raw)
    start, end = txt.find("{"), txt.rfind("}")
    if start == -1 or end == -1:
        return False, "giám khảo không trả JSON"
    try:
        obj = json.loads(txt[start:end + 1])
    except ValueError:
        return False, "JSON giám khảo không hợp lệ"
    return _truthy(obj.get("dung")), str(obj.get("ly_do", ""))[:200]


def parse_verdicts(raw: str, n: int) -> list[tuple[bool, str]]:
    """Đọc mảng JSON giám khảo cho một lô n câu. Thiếu phần tử nào thì phần tử đó coi là SAI."""
    txt = _strip_fence(raw)
    start, end = txt.find("["), txt.rfind("]")
    out = [(False, "giám khảo không chấm mục này")] * n
    if start == -1 or end == -1:
        return out
    try:
        arr = json.loads(txt[start:end + 1])
    except ValueError:
        return out
    for obj in arr if isinstance(arr, list) else []:
        try:
            i = int(obj.get("i"))
        except (TypeError, ValueError, AttributeError):
            continue
        if 1 <= i <= n:
            out[i - 1] = (_truthy(obj.get("dung")), str(obj.get("ly_do", ""))[:200])
    return out


def _strip_fence(raw: str) -> str:
    txt = (raw or "").strip()
    if txt.startswith("```"):
        txt = txt.strip("`")
        if txt.lower().startswith("json"):
            txt = txt[4:]
    return txt


def _truthy(val) -> bool:
    if isinstance(val, str):
        return val.strip().lower() in ("true", "dung", "đúng", "yes")
    return bool(val)


class GeminiJudge:
    def __init__(self) -> None:
        import google.generativeai as genai
        from config import settings
        genai.configure(api_key=settings.gemini_api_key)
        self.model_name = os.getenv("GEMINI_JUDGE_MODEL", "gemini-3.5-flash-lite").strip()
        self.model = genai.GenerativeModel(
            self.model_name,
            generation_config={"response_mime_type": "application/json", "temperature": 0},
        )

    def judge_batch(self, items: list[dict]) -> list[tuple[bool, str]]:
        """items: [{q, expect, answer}] tối đa JUDGE_BATCH -> [(dung, ly_do)]."""
        body = "\n".join(ITEM_TEMPLATE.format(i=i + 1, question=it["q"], answer=it["answer"],
                                              expect="\n".join(f"- {e}" for e in it["expect"]))
                         for i, it in enumerate(items))
        prompt = JUDGE_PROMPT.format(items=body, n=len(items))
        raw = _with_retry(lambda: (self.model.generate_content(prompt).text or ""))
        return parse_verdicts(raw, len(items))


def _sample(questions: list[dict], n: int) -> list[dict]:
    """Lấy n câu chia đều theo chủ đề, thứ tự cố định (seed) để so sánh giữa các lần chạy."""
    if n <= 0 or n >= len(questions):
        return questions
    rng = random.Random(42)
    by_topic: dict[str, list[dict]] = defaultdict(list)
    for q in questions:
        by_topic[q["topic"]].append(q)
    for lst in by_topic.values():
        rng.shuffle(lst)
    picked: list[dict] = []
    while len(picked) < n:
        progressed = False
        for lst in by_topic.values():
            if lst and len(picked) < n:
                picked.append(lst.pop())
                progressed = True
        if not progressed:
            break
    return sorted(picked, key=questions.index)


def run(min_score: float | None = None, verbose: bool = True, judge: str = "keyword",
        history: bool = False, pause: float = 0.0, sample: int = 0) -> dict:
    questions = _sample(json.loads((HERE / "eval" / "questions.json").read_text(encoding="utf-8")), sample)
    retriever = Retriever()
    agent, mode = build_agent(os.getenv("LLM_BACKEND", "offline").lower(), retriever)
    judge_impl = None
    if judge == "gemini":
        if not os.getenv("GEMINI_API_KEY"):
            print("Không có GEMINI_API_KEY -> chấm bằng keyword.")
            judge = "keyword"
        else:
            judge_impl = GeminiJudge()
    has_db = db_available()
    results: list[dict] = []
    t_start = time.time()
    # 1) Hỏi chatbot, chấm keyword ngay
    for i, item in enumerate(questions):
        if item.get("needs_db") and not has_db:
            results.append({**item, "skipped": True})
            continue
        sid = f"eval-{i}"
        ctx = item.get("user_context", "")
        t0 = time.time()
        if "flow" in item:                       # hội thoại nhiều lượt, chấm câu cuối
            for turn in item["flow"]:
                answer = _with_retry(lambda t=turn: agent.reply(sid, t, user_context=ctx))
            q = " -> ".join(item["flow"])
        else:
            q = item["q"]
            answer = _with_retry(lambda: agent.reply(sid, q, user_context=ctx))
        ms = int((time.time() - t0) * 1000)
        ok_kw = any(_norm(k) in _norm(answer) for k in item["expect"])
        results.append({"topic": item["topic"], "q": q, "expect": item["expect"], "ok": ok_kw, "ok_keyword": ok_kw,
                        "judge_reason": "", "ms": ms, "answer": answer[:600]})
        if verbose and judge_impl is None:
            print(f"  {'PASS' if ok_kw else 'FAIL'}  [{item['topic']}] {q[:60]}  ({ms} ms)")
            if not ok_kw:
                print(f"        -> {answer[:160].replace(chr(10), ' ')}")
        if pause:
            time.sleep(pause)
    graded = [r for r in results if not r.get("skipped")]
    # 2) LLM-as-judge theo lô
    if judge_impl is not None and graded:
        for start in range(0, len(graded), JUDGE_BATCH):
            chunk = graded[start:start + JUDGE_BATCH]
            for r, (ok, reason) in zip(chunk, judge_impl.judge_batch(chunk)):
                r["ok"], r["judge_reason"] = ok, reason
            if pause:
                time.sleep(pause)
        if verbose:
            for r in graded:
                print(f"  {'PASS' if r['ok'] else 'FAIL'}  [{r['topic']}] {r['q'][:60]}  ({r['ms']} ms)  "
                      f"kw={'ok' if r['ok_keyword'] else 'x'}")
                if not r["ok"]:
                    print(f"        -> {r['answer'][:160].replace(chr(10), ' ')}")
                    print(f"        giám khảo: {r['judge_reason']}")
    by_topic: dict[str, list[int]] = defaultdict(lambda: [0, 0])
    for r in graded:
        by_topic[r["topic"]][0] += r["ok"]
        by_topic[r["topic"]][1] += 1
    passed = sum(r["ok"] for r in graded)
    kw_passed = sum(r["ok_keyword"] for r in graded)
    score = round(100 * passed / len(graded), 1) if graded else 0.0
    for r in results:
        r.pop("expect", None)
    summary = {
        "mode": mode, "judge": judge,
        "judge_model": getattr(judge_impl, "model_name", None),
        "chat_model": os.getenv("GEMINI_MODEL") if mode == "gemini" else None,
        "db": has_db, "total": len(graded), "passed": passed, "score": score,
        "keyword_score": round(100 * kw_passed / len(graded), 1) if graded else 0.0,
        "skipped": len(results) - len(graded), "sample": sample or None,
        "avg_ms": int(sum(r["ms"] for r in graded) / len(graded)) if graded else 0,
        "by_topic": {t: {"passed": v[0], "total": v[1]} for t, v in sorted(by_topic.items())},
        "elapsed_s": round(time.time() - t_start, 1),
        "git_sha": _git_sha(), "timestamp": datetime.now().isoformat(timespec="minutes"),
        "results": results,
    }
    (HERE / "eval" / "last_result.json").write_text(json.dumps(summary, ensure_ascii=False, indent=2), encoding="utf-8")
    if history:
        hdir = HERE / "eval" / "history"
        hdir.mkdir(exist_ok=True)
        name = f"{datetime.now():%Y%m%d-%H%M}_{mode}_{judge}_{summary['git_sha']}.json"
        (hdir / name).write_text(json.dumps({k: v for k, v in summary.items() if k != "results"},
                                            ensure_ascii=False, indent=2), encoding="utf-8")
    if verbose:
        print("\n  Chủ đề            Đúng/Tổng")
        for t, v in summary["by_topic"].items():
            print(f"  {t:<18}{v['passed']}/{v['total']}")
        print(f"\n===== CHẤT LƯỢNG BOT ({mode}{', model ' + summary['chat_model'] if summary['chat_model'] else ''}, "
              f"chấm {judge}{' bằng ' + summary['judge_model'] if summary['judge_model'] else ''}"
              f"{', không DB' if not has_db else ''}): {passed}/{len(graded)} = {score}%"
              + (f" (keyword {summary['keyword_score']}%)" if judge != "keyword" else "")
              + f" | trung bình {summary['avg_ms']} ms/câu | commit {summary['git_sha']}"
              f"{' | bỏ qua ' + str(summary['skipped']) + ' câu cần DB' if summary['skipped'] else ''} =====")
    step_summary = os.getenv("GITHUB_STEP_SUMMARY")
    if step_summary:
        with open(step_summary, "a", encoding="utf-8") as f:
            f.write(f"## Eval chatbot ({mode}, chấm {judge})\n\n"
                    f"| Điểm | Keyword | Đúng/Tổng | Bỏ qua | Trung bình | Commit |\n|---|---|---|---|---|---|\n"
                    f"| {score}% | {summary['keyword_score']}% | {passed}/{len(graded)} | {summary['skipped']} "
                    f"| {summary['avg_ms']} ms | `{summary['git_sha']}` |\n\n")
            fails = [r for r in graded if not r["ok"]]
            if fails:
                f.write("Câu sai:\n\n" + "\n".join(f"- [{r['topic']}] {r['q']}: {r['judge_reason'] or r['answer'][:120]}"
                                                  for r in fails) + "\n")
    if min_score is not None and score < min_score:
        print(f"Dưới ngưỡng {min_score}% -> FAIL")
        sys.exit(1)
    return summary


if __name__ == "__main__":
    ap = argparse.ArgumentParser()
    ap.add_argument("--min", type=float, default=None, help="ngưỡng %% tối thiểu, dưới thì exit 1")
    ap.add_argument("--judge", choices=["keyword", "gemini"], default="keyword", help="cách chấm")
    ap.add_argument("--history", action="store_true", help="lưu thêm bản ghi vào eval/history/")
    ap.add_argument("--pause", type=float, default=0.0, help="giây nghỉ giữa các lượt gọi (tránh 429)")
    ap.add_argument("--sample", type=int, default=0, help="chỉ chạy N câu chia đều theo chủ đề")
    ap.add_argument("--quiet", action="store_true")
    a = ap.parse_args()
    run(a.min, verbose=not a.quiet, judge=a.judge, history=a.history, pause=a.pause, sample=a.sample)

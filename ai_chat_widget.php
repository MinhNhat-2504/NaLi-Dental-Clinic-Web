<?php
/**
 * ai_chat_widget.php — Widget chatbot AI của NALI (nhúng toàn site).
 *
 * Được gọi trong renderFooter() (includes/components.php) nên xuất hiện ở mọi
 * trang. Widget gọi sang AI service Python tại AI_SERVICE_URL.
 *
 * Nếu đổi cổng/host của service Python, chỉ cần sửa hằng số bên dưới.
 */
if (!defined('AI_SERVICE_URL')) {
    // Ưu tiên biến môi trường AI_SERVICE_URL (khi deploy trỏ tới AI công khai,
    // vd https://<user>-nali-ai.hf.space); mặc định là service local.
    define('AI_SERVICE_URL', getenv('AI_SERVICE_URL') ?: 'http://127.0.0.1:8000');
}
?>
<!-- ===== NALI AI Chat Widget ===== -->
<div id="naliChatWidget" data-endpoint="<?php echo htmlspecialchars(AI_SERVICE_URL); ?>">
    <!-- Nút bong bóng mở chat -->
    <button id="naliChatToggle" aria-label="Mở trợ lý AI">
        <i class="fas fa-robot"></i>
        <span class="nali-chat-badge">AI</span>
    </button>

    <!-- Khung chat -->
    <div id="naliChatBox" class="nali-hidden">
        <div class="nali-chat-header">
            <div class="nali-chat-title">
                <span class="nali-avatar">🦷</span>
                <div>
                    <strong>NALI Trợ Lý</strong>
                    <small id="naliChatStatus">Đang kết nối…</small>
                </div>
            </div>
            <button id="naliChatClose" aria-label="Đóng">&times;</button>
        </div>

        <div id="naliChatBody" class="nali-chat-body"></div>

        <div class="nali-quick" id="naliQuick">
            <button class="nali-chip" data-msg="Cho tôi xem bảng giá dịch vụ">💰 Bảng giá</button>
            <button class="nali-chip" data-msg="Tôi muốn đặt lịch khám">📅 Đặt lịch</button>
            <button class="nali-chip" data-msg="Phòng khám làm việc mấy giờ?">🕗 Giờ mở cửa</button>
        </div>

        <form id="naliChatForm" class="nali-chat-input">
            <input id="naliChatText" type="text" placeholder="Nhập câu hỏi cho NALI…" autocomplete="off" />
            <button type="submit" aria-label="Gửi"><i class="fas fa-paper-plane"></i></button>
        </form>
    </div>
</div>

<style>
#naliChatWidget { position: fixed; right: 22px; bottom: 22px; z-index: 9999; font-family: inherit; }
#naliChatToggle {
    width: 62px; height: 62px; border-radius: 50%; border: none; cursor: pointer;
    background: linear-gradient(135deg, #4da6ff, #3d8fe8); color: #fff; font-size: 1.6rem;
    box-shadow: 0 8px 22px rgba(77,166,255,.45); position: relative; transition: transform .2s;
}
#naliChatToggle:hover { transform: scale(1.08); }
.nali-chat-badge {
    position: absolute; top: -4px; right: -4px; background: #ff6b6b; color: #fff;
    font-size: .62rem; font-weight: 700; padding: 2px 6px; border-radius: 10px;
}
#naliChatBox {
    position: absolute; right: 0; bottom: 78px; width: 360px; max-width: calc(100vw - 32px);
    height: 520px; max-height: calc(100vh - 120px); background: #fff; border-radius: 18px;
    box-shadow: 0 16px 48px rgba(0,0,0,.22); display: flex; flex-direction: column; overflow: hidden;
    animation: naliPop .18s ease-out;
}
@keyframes naliPop { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: none; } }
.nali-hidden { display: none !important; }
.nali-chat-header {
    background: linear-gradient(135deg, #4da6ff, #3d8fe8); color: #fff; padding: 14px 16px;
    display: flex; align-items: center; justify-content: space-between;
}
.nali-chat-title { display: flex; align-items: center; gap: 10px; }
.nali-avatar {
    width: 38px; height: 38px; background: rgba(255,255,255,.2); border-radius: 50%;
    display: flex; align-items: center; justify-content: center; font-size: 1.2rem;
}
.nali-chat-title small { display: block; opacity: .85; font-size: .72rem; }
#naliChatClose { background: none; border: none; color: #fff; font-size: 1.6rem; cursor: pointer; line-height: 1; }
.nali-chat-body { flex: 1; overflow-y: auto; padding: 14px; background: #f5f9ff; }
.nali-msg { margin-bottom: 12px; display: flex; }
.nali-msg .bubble {
    padding: 10px 13px; border-radius: 14px; max-width: 82%; font-size: .9rem; line-height: 1.45;
    white-space: pre-wrap; word-wrap: break-word;
}
.nali-msg time { display:block; font-size:.65rem; opacity:.65; margin-top:5px; }
.nali-msg.bot .bubble { background: #fff; color: #333; border: 1px solid #e3eefc; border-bottom-left-radius: 4px; }
.nali-msg.user { justify-content: flex-end; }
.nali-msg.user .bubble { background: linear-gradient(135deg, #4da6ff, #3d8fe8); color: #fff; border-bottom-right-radius: 4px; }
.nali-typing .bubble { color: #888; font-style: italic; }
.nali-quick { padding: 8px 12px 0; display: flex; gap: 6px; flex-wrap: wrap; background: #f5f9ff; }
.nali-chip {
    border: 1px solid #cfe4ff; background: #fff; color: #3d8fe8; border-radius: 16px;
    padding: 5px 10px; font-size: .78rem; cursor: pointer; transition: background .15s;
}
.nali-chip:hover { background: #e8f4ff; }
.nali-chat-input { display: flex; padding: 10px 12px; gap: 8px; background: #fff; border-top: 1px solid #eef3fa; }
.nali-chat-input input { flex: 1; border: 1px solid #d9e6f7; border-radius: 20px; padding: 10px 14px; font-size: .9rem; outline: none; }
.nali-chat-input input:focus { border-color: #4da6ff; }
.nali-chat-input button {
    border: none; background: #4da6ff; color: #fff; width: 42px; height: 42px; border-radius: 50%;
    cursor: pointer; font-size: 1rem;
}
.nali-chat-input button:disabled { opacity: .5; cursor: default; }
@media (max-width: 480px) { #naliChatBox { width: calc(100vw - 24px); } }
</style>

<script>
(function () {
    const root = document.getElementById('naliChatWidget');
    const ENDPOINT = root.getAttribute('data-endpoint');
    const box = document.getElementById('naliChatBox');
    const body = document.getElementById('naliChatBody');
    const form = document.getElementById('naliChatForm');
    const input = document.getElementById('naliChatText');
    const statusEl = document.getElementById('naliChatStatus');
    let greeted = false;

    // ID phiên chat ổn định giữa các lần tải trang
    let sessionId = localStorage.getItem('nali_chat_sid');
    if (!sessionId) {
        sessionId = 'web-' + Math.random().toString(36).slice(2, 10);
        localStorage.setItem('nali_chat_sid', sessionId);
    }

    // Chuyển **đậm** và xuống dòng thành HTML an toàn
    function render(text) {
        const esc = text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        return esc.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>').replace(/\n/g, '<br>');
    }

    function addMsg(text, who) {
        const wrap = document.createElement('div');
        wrap.className = 'nali-msg ' + who;
        wrap.innerHTML = '<div class="bubble">' + render(text) + '<time>' + new Date().toLocaleTimeString('vi-VN',{hour:'2-digit',minute:'2-digit'}) + '</time></div>';
        body.appendChild(wrap);
        body.scrollTop = body.scrollHeight;
        return wrap;
    }

    async function checkHealth() {
        try {
            const r = await fetch(ENDPOINT + '/health');
            const d = await r.json();
            statusEl.textContent = d.ai_mode === 'gemini' ? 'Trực tuyến • Gemini AI' : 'Trực tuyến • Offline';
        } catch (e) {
            statusEl.textContent = 'Chưa chạy AI service :8000';
        }
    }

    async function send(message) {
        addMsg(message, 'user');
        input.value = '';
        const typing = addMsg('NALI đang soạn trả lời…', 'bot');
        typing.classList.add('nali-typing');
        try {
            const r = await fetch(ENDPOINT + '/chat', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ session_id: sessionId, message: message })
            });
            const d = await r.json();
            typing.remove();
            addMsg(d.reply || 'Xin lỗi, NALI chưa trả lời được ạ.', 'bot');
        } catch (e) {
            typing.remove();
            addMsg('⚠️ Không kết nối được trợ lý AI. Hãy chắc chắn service Python đang chạy ở cổng 8000 nhé ạ.', 'bot');
        }
    }

    function openChat() {
        box.classList.remove('nali-hidden');
        if (!greeted) {
            greeted = true;
            addMsg('Xin chào 👋 NALI là trợ lý AI của Nha khoa NALI. Em có thể tư vấn dịch vụ, báo giá và đặt lịch giúp anh/chị ạ!', 'bot');
            checkHealth();
        }
        input.focus();
    }

    document.getElementById('naliChatToggle').addEventListener('click', function () {
        box.classList.contains('nali-hidden') ? openChat() : box.classList.add('nali-hidden');
    });
    document.getElementById('naliChatClose').addEventListener('click', function () {
        box.classList.add('nali-hidden');
    });
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        const msg = input.value.trim();
        if (msg) send(msg);
    });
    document.getElementById('naliQuick').addEventListener('click', function (e) {
        const chip = e.target.closest('.nali-chip');
        if (chip) send(chip.getAttribute('data-msg'));
    });
})();
</script>
<!-- ===== /NALI AI Chat Widget ===== -->

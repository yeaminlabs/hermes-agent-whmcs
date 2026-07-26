<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
* { box-sizing: border-box; }
.hchat-page { font-family: 'Outfit', sans-serif; margin: 20px 0; display: flex; flex-direction: column; height: calc(100vh - 160px); min-height: 560px; }

/* ── Shell ─────────────────────────────────────────────────────── */
.hchat-shell {
    display: flex; flex-direction: column; flex: 1; overflow: hidden;
    background: #fff; border: 1px solid #e5e7eb; border-radius: 16px;
    box-shadow: 0 4px 24px rgba(0,0,0,.06);
}

/* ── Header ────────────────────────────────────────────────────── */
.hchat-header {
    padding: 16px 20px; border-bottom: 1px solid #f3f4f6;
    display: flex; align-items: center; justify-content: space-between;
    flex-shrink: 0; background: #fff; border-radius: 16px 16px 0 0;
}
.hchat-header-left { display: flex; align-items: center; gap: 12px; }
.hchat-avatar {
    width: 40px; height: 40px; border-radius: 50%;
    background: linear-gradient(135deg, #CC0000 0%, #ff4444 100%);
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: 18px; flex-shrink: 0;
}
.hchat-agent-name { font-size: 16px; font-weight: 700; color: #111827; }
.hchat-agent-sub  { font-size: 12px; color: #6b7280; margin-top: 1px; }
.hchat-online-dot {
    width: 8px; height: 8px; border-radius: 50%; background: #10b981;
    display: inline-block; margin-right: 5px; animation: hchat-pulse 2s infinite;
}
@keyframes hchat-pulse { 0%,100%{opacity:1} 50%{opacity:.4} }

.hchat-actions { display: flex; gap: 8px; align-items: center; }
.hchat-btn {
    padding: 6px 14px; border-radius: 8px; font-size: 12px; font-weight: 700;
    cursor: pointer; border: 1px solid #e5e7eb; background: #f9fafb; color: #374151;
    display: flex; align-items: center; gap: 5px; transition: all .15s;
    font-family: 'Outfit', sans-serif;
}
.hchat-btn:hover { background: #f3f4f6; }

/* ── Messages area ─────────────────────────────────────────────── */
.hchat-messages {
    flex: 1; overflow-y: auto; padding: 24px 20px;
    display: flex; flex-direction: column; gap: 16px;
    scroll-behavior: smooth;
    scrollbar-width: thin; scrollbar-color: #e5e7eb transparent;
}
.hchat-messages::-webkit-scrollbar { width: 5px; }
.hchat-messages::-webkit-scrollbar-thumb { background: #e5e7eb; border-radius: 3px; }

/* ── Message bubbles ───────────────────────────────────────────── */
.hchat-row { display: flex; gap: 10px; align-items: flex-end; max-width: 80%; }
.hchat-row.user { align-self: flex-end; flex-direction: row-reverse; }
.hchat-row.agent { align-self: flex-start; }

.hchat-bubble-avatar {
    width: 30px; height: 30px; border-radius: 50%; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center; font-size: 14px;
}
.hchat-row.user  .hchat-bubble-avatar { background: #dbeafe; color: #1d4ed8; }
.hchat-row.agent .hchat-bubble-avatar {
    background: linear-gradient(135deg, #CC0000, #ff4444); color: #fff;
}

.hchat-bubble {
    padding: 12px 16px; border-radius: 16px; font-size: 14px; line-height: 1.6;
    max-width: 100%; word-break: break-word;
}
.hchat-row.user  .hchat-bubble {
    background: #1d4ed8; color: #fff;
    border-bottom-right-radius: 4px;
}
.hchat-row.agent .hchat-bubble {
    background: #f9fafb; color: #111827; border: 1px solid #f3f4f6;
    border-bottom-left-radius: 4px;
}

/* Markdown inside agent bubble */
.hchat-bubble p  { margin: 0 0 8px; }
.hchat-bubble p:last-child { margin-bottom: 0; }
.hchat-bubble strong { font-weight: 700; }
.hchat-bubble em     { font-style: italic; }
.hchat-bubble code   { background: #e5e7eb; padding: 1px 5px; border-radius: 4px; font-size: 12px; font-family: 'Fira Code', monospace; }
.hchat-row.user .hchat-bubble code { background: rgba(255,255,255,.25); }
.hchat-bubble pre {
    background: #1e293b; color: #e2e8f0; padding: 12px 14px;
    border-radius: 8px; overflow-x: auto; margin: 8px 0;
    font-size: 12px; font-family: 'Fira Code', monospace; line-height: 1.5;
}
.hchat-bubble pre code { background: none; padding: 0; color: inherit; font-size: inherit; }
.hchat-bubble ul, .hchat-bubble ol { padding-left: 20px; margin: 6px 0; }
.hchat-bubble li { margin-bottom: 3px; }
.hchat-bubble h1,.hchat-bubble h2,.hchat-bubble h3 { font-weight: 700; margin: 10px 0 4px; }
.hchat-bubble h1 { font-size: 17px; }
.hchat-bubble h2 { font-size: 15px; }
.hchat-bubble h3 { font-size: 14px; }
.hchat-bubble a  { color: #3b82f6; text-decoration: underline; }
.hchat-row.user .hchat-bubble a { color: #bfdbfe; }
.hchat-bubble blockquote {
    border-left: 3px solid #d1d5db; padding-left: 10px;
    color: #6b7280; margin: 6px 0; font-style: italic;
}

.hchat-time { font-size: 10px; color: #9ca3af; margin-top: 4px; text-align: right; }
.hchat-row.agent .hchat-time { text-align: left; }

/* ── Typing indicator ──────────────────────────────────────────── */
.hchat-typing { display: flex; gap: 4px; padding: 4px 2px; }
.hchat-typing span {
    width: 7px; height: 7px; border-radius: 50%; background: #9ca3af;
    animation: hchat-bounce .8s infinite;
}
.hchat-typing span:nth-child(2) { animation-delay: .15s; }
.hchat-typing span:nth-child(3) { animation-delay: .3s; }
@keyframes hchat-bounce { 0%,60%,100%{transform:translateY(0)} 30%{transform:translateY(-6px)} }

/* ── Empty state ───────────────────────────────────────────────── */
.hchat-empty {
    flex: 1; display: flex; flex-direction: column;
    align-items: center; justify-content: center; gap: 12px; color: #9ca3af;
}
.hchat-empty-icon { font-size: 48px; }
.hchat-empty h3 { font-size: 17px; font-weight: 700; color: #374151; margin: 0; }
.hchat-empty p  { font-size: 13px; margin: 0; text-align: center; max-width: 280px; }
.hchat-starter-pills { display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; margin-top: 4px; }
.hchat-pill {
    padding: 7px 14px; border-radius: 100px; font-size: 12px; font-weight: 600;
    border: 1px solid #e5e7eb; background: #f9fafb; color: #374151; cursor: pointer;
    transition: all .15s;
}
.hchat-pill:hover { background: #CC0000; color: #fff; border-color: #CC0000; }

/* ── Input area ────────────────────────────────────────────────── */
.hchat-input-area {
    flex-shrink: 0; padding: 14px 20px; border-top: 1px solid #f3f4f6;
    background: #fff; border-radius: 0 0 16px 16px;
}
.hchat-input-box {
    display: flex; gap: 10px; align-items: flex-end;
    background: #f9fafb; border: 1.5px solid #e5e7eb; border-radius: 12px;
    padding: 10px 14px; transition: border-color .15s;
}
.hchat-input-box:focus-within { border-color: #CC0000; background: #fff; box-shadow: 0 0 0 3px rgba(204,0,0,.08); }
.hchat-textarea {
    flex: 1; border: none; background: transparent; resize: none; outline: none;
    font-family: 'Outfit', sans-serif; font-size: 14px; color: #111827;
    line-height: 1.5; max-height: 160px; min-height: 22px; overflow-y: auto;
}
.hchat-textarea::placeholder { color: #9ca3af; }
.hchat-send {
    width: 36px; height: 36px; border-radius: 8px; border: none; cursor: pointer;
    background: #CC0000; color: #fff; display: flex; align-items: center; justify-content: center;
    font-size: 14px; flex-shrink: 0; transition: background .15s; align-self: flex-end;
}
.hchat-send:hover:not(:disabled) { background: #aa0000; }
.hchat-send:disabled { background: #d1d5db; cursor: not-allowed; }
.hchat-input-hint { font-size: 11px; color: #9ca3af; margin-top: 6px; text-align: center; }

/* ── Error toast ───────────────────────────────────────────────── */
.hchat-error {
    margin: 0 20px 12px; padding: 10px 14px; border-radius: 8px;
    background: #fef2f2; border: 1px solid #fecaca; color: #991b1b;
    font-size: 13px; font-weight: 600; display: flex; align-items: center; gap: 8px;
}

/* ── Suspended ─────────────────────────────────────────────────── */
.hchat-suspended {
    background: #fef2f2; padding: 16px; border-radius: 12px;
    border: 1px solid #fecaca; color: #991b1b; font-weight: 600; font-size: 14px;
}
</style>

<div class="hchat-page">

{if $deployment_status eq 'Suspended' or $deployment_status eq 'Terminated'}
<div class="hchat-suspended">
    <i class="fas fa-ban"></i> Account {$deployment_status} — Chat is unavailable.
</div>
{else}

<div class="hchat-shell">

    <!-- Header -->
    <div class="hchat-header">
        <div class="hchat-header-left">
            <div class="hchat-avatar"><i class="fas fa-robot"></i></div>
            <div>
                <div class="hchat-agent-name">
                    <span class="hchat-online-dot"></span>{$agent_name}
                </div>
                <div class="hchat-agent-sub">Hermes Agent · hermes-{$serviceid}</div>
            </div>
        </div>
        <div class="hchat-actions">
            <button class="hchat-btn" onclick="hchatNewConversation()" title="Start a new conversation">
                <i class="fas fa-plus"></i> New Chat
            </button>
            <button class="hchat-btn" onclick="hchatExport()" title="Copy conversation">
                <i class="fas fa-copy"></i>
            </button>
            <a href="clientarea.php?action=productdetails&id={$serviceid}"
               style="color:#6b7280;font-size:13px;font-weight:600;text-decoration:none;margin-left:4px;">
                <i class="fas fa-arrow-left"></i>
            </a>
        </div>
    </div>

    <!-- Messages -->
    <div class="hchat-messages" id="hchat-msgs">
        <div class="hchat-empty" id="hchat-empty">
            <div class="hchat-empty-icon">🤖</div>
            <h3>Chat with {$agent_name}</h3>
            <p>Ask your agent anything. It has access to all its configured tools and memory.</p>
            <div class="hchat-starter-pills">
                <button class="hchat-pill" onclick="hchatSendStarter(this)">What can you do?</button>
                <button class="hchat-pill" onclick="hchatSendStarter(this)">What tools do you have?</button>
                <button class="hchat-pill" onclick="hchatSendStarter(this)">Summarize your memory</button>
                <button class="hchat-pill" onclick="hchatSendStarter(this)">Tell me about yourself</button>
            </div>
        </div>
    </div>

    <!-- Error bar (hidden by default) -->
    <div class="hchat-error" id="hchat-err" style="display:none;">
        <i class="fas fa-exclamation-circle"></i>
        <span id="hchat-err-text"></span>
    </div>

    <!-- Input -->
    <div class="hchat-input-area">
        <div class="hchat-input-box">
            <textarea class="hchat-textarea" id="hchat-input" rows="1"
                      placeholder="Message {$agent_name}…"></textarea>
            <button class="hchat-send" id="hchat-send" title="Send (Enter)">
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
        <div class="hchat-input-hint">Press Enter to send · Shift+Enter for new line</div>
    </div>

</div>
{/if}
</div>

<script>
(function () {
    var SVC_ID     = {$serviceid};
    var AJAX       = 'modules/servers/hermesagent/ajax.php';
    var AGENT_NAME = {$agent_name|json_encode};

    var msgsEl  = document.getElementById('hchat-msgs');
    var inputEl = document.getElementById('hchat-input');
    var sendEl  = document.getElementById('hchat-send');
    var errEl   = document.getElementById('hchat-err');
    var errTxt  = document.getElementById('hchat-err-text');
    var emptyEl = document.getElementById('hchat-empty');

    if (!inputEl) return; // suspended

    // Conversation history sent to API
    var history = [
        { role: 'system', content: 'You are ' + AGENT_NAME + ', a helpful AI agent. Be concise, clear, and helpful.' }
    ];
    var busy = false;

    // ── Lightweight markdown renderer ──────────────────────────────
    function renderMarkdown(text) {
        // Escape HTML first
        var esc = text
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');

        // Code blocks (must come before inline code)
        esc = esc.replace(/```(\w*)\n([\s\S]*?)```/g, function(_, lang, code) {
            return '<pre><code>' + code.trimEnd() + '</code></pre>';
        });
        // Inline code
        esc = esc.replace(/`([^`]+)`/g, '<code>$1</code>');
        // Bold
        esc = esc.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
        esc = esc.replace(/__(.+?)__/g, '<strong>$1</strong>');
        // Italic
        esc = esc.replace(/\*(.+?)\*/g, '<em>$1</em>');
        esc = esc.replace(/_(.+?)_/g, '<em>$1</em>');
        // Headers
        esc = esc.replace(/^### (.+)$/gm, '<h3>$1</h3>');
        esc = esc.replace(/^## (.+)$/gm,  '<h2>$1</h2>');
        esc = esc.replace(/^# (.+)$/gm,   '<h1>$1</h1>');
        // Blockquotes
        esc = esc.replace(/^&gt; (.+)$/gm, '<blockquote>$1</blockquote>');
        // Unordered lists
        esc = esc.replace(/^[\*\-] (.+)$/gm, '<li>$1</li>');
        esc = esc.replace(/(<li>.*<\/li>\n?)+/g, function(m) { return '<ul>' + m + '</ul>'; });
        // Ordered lists
        esc = esc.replace(/^\d+\. (.+)$/gm, '<li>$1</li>');
        // Links
        esc = esc.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>');
        // Line breaks → paragraphs
        var paras = esc.split(/\n{2,}/);
        esc = paras.map(function(p) {
            p = p.trim();
            if (!p) return '';
            if (/^<(h[1-3]|ul|ol|pre|blockquote)/.test(p)) return p;
            return '<p>' + p.replace(/\n/g, '<br>') + '</p>';
        }).join('');

        return esc;
    }

    // ── Time label ─────────────────────────────────────────────────
    function timeNow() {
        var d = new Date();
        return d.getHours().toString().padStart(2,'0') + ':' + d.getMinutes().toString().padStart(2,'0');
    }

    // ── Append message bubble ──────────────────────────────────────
    function appendMsg(text, role) {
        if (emptyEl) { emptyEl.remove(); emptyEl = null; }

        var row = document.createElement('div');
        row.className = 'hchat-row ' + (role === 'user' ? 'user' : 'agent');

        var avIcon = role === 'user' ? '<i class="fas fa-user"></i>' : '<i class="fas fa-robot"></i>';
        var bubbleContent = role === 'user'
            ? text.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>')
            : renderMarkdown(text);

        row.innerHTML = [
            '<div class="hchat-bubble-avatar">' + avIcon + '</div>',
            '<div>',
            '  <div class="hchat-bubble">' + bubbleContent + '</div>',
            '  <div class="hchat-time">' + timeNow() + '</div>',
            '</div>',
        ].join('');

        msgsEl.appendChild(row);
        msgsEl.scrollTop = msgsEl.scrollHeight;
        return row;
    }

    // ── Typing indicator ───────────────────────────────────────────
    var typingRow = null;
    function showTyping() {
        if (emptyEl) { emptyEl.remove(); emptyEl = null; }
        typingRow = document.createElement('div');
        typingRow.className = 'hchat-row agent';
        typingRow.innerHTML = [
            '<div class="hchat-bubble-avatar"><i class="fas fa-robot"></i></div>',
            '<div class="hchat-bubble" style="padding:14px 16px;">',
            '  <div class="hchat-typing"><span></span><span></span><span></span></div>',
            '</div>',
        ].join('');
        msgsEl.appendChild(typingRow);
        msgsEl.scrollTop = msgsEl.scrollHeight;
    }
    function hideTyping() {
        if (typingRow) { typingRow.remove(); typingRow = null; }
    }

    // ── Show/hide error ────────────────────────────────────────────
    function showErr(msg) {
        errTxt.textContent = msg;
        errEl.style.display = 'flex';
        setTimeout(function() { errEl.style.display = 'none'; }, 8000);
    }

    // ── Send message ───────────────────────────────────────────────
    function send() {
        var text = inputEl.value.trim();
        if (!text || busy) return;

        busy = true;
        sendEl.disabled = true;
        errEl.style.display = 'none';

        history.push({ role: 'user', content: text });
        appendMsg(text, 'user');
        inputEl.value = '';
        inputEl.style.height = 'auto';
        showTyping();

        var fd = new FormData();
        fd.append('action', 'chat_proxy');
        fd.append('serviceId', SVC_ID);
        fd.append('messages', JSON.stringify(history));

        fetch(AJAX, { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                hideTyping();
                if (!data.success) {
                    showErr(data.error || 'Agent returned an error');
                    history.pop(); // remove failed user message
                } else {
                    history.push({ role: 'assistant', content: data.reply });
                    appendMsg(data.reply, 'agent');
                }
            })
            .catch(function(e) {
                hideTyping();
                showErr('Network error: ' + e.message);
                history.pop();
            })
            .finally(function() {
                busy = false;
                sendEl.disabled = false;
                inputEl.focus();
            });
    }

    // ── Input auto-resize + keyboard handling ──────────────────────
    inputEl.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 160) + 'px';
    });
    inputEl.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            send();
        }
    });
    sendEl.addEventListener('click', send);

    // ── Starter pills ──────────────────────────────────────────────
    window.hchatSendStarter = function(el) {
        inputEl.value = el.textContent;
        send();
    };

    // ── New conversation ───────────────────────────────────────────
    window.hchatNewConversation = function() {
        if (busy) return;
        history = [
            { role: 'system', content: 'You are ' + AGENT_NAME + ', a helpful AI agent. Be concise, clear, and helpful.' }
        ];
        // Remove all messages and re-show empty state
        while (msgsEl.firstChild) msgsEl.removeChild(msgsEl.firstChild);
        var em = document.createElement('div');
        em.className = 'hchat-empty';
        em.id = 'hchat-empty';
        em.innerHTML = [
            '<div class="hchat-empty-icon">🤖</div>',
            '<h3>Chat with ' + AGENT_NAME + '</h3>',
            '<p>Ask your agent anything. It has access to all its configured tools and memory.</p>',
            '<div class="hchat-starter-pills">',
            '  <button class="hchat-pill" onclick="hchatSendStarter(this)">What can you do?</button>',
            '  <button class="hchat-pill" onclick="hchatSendStarter(this)">What tools do you have?</button>',
            '  <button class="hchat-pill" onclick="hchatSendStarter(this)">Summarize your memory</button>',
            '  <button class="hchat-pill" onclick="hchatSendStarter(this)">Tell me about yourself</button>',
            '</div>',
        ].join('');
        msgsEl.appendChild(em);
        emptyEl = em;
        errEl.style.display = 'none';
        inputEl.focus();
    };

    // ── Export conversation ────────────────────────────────────────
    window.hchatExport = function() {
        var lines = history.slice(1).map(function(m) {
            return (m.role === 'user' ? 'You: ' : AGENT_NAME + ': ') + m.content;
        });
        navigator.clipboard.writeText(lines.join('\n\n')).then(function() {
            var btn = document.querySelector('.hchat-btn[onclick="hchatExport()"]');
            var orig = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-check"></i>';
            setTimeout(function() { btn.innerHTML = orig; }, 1500);
        });
    };

    inputEl.focus();
})();
</script>

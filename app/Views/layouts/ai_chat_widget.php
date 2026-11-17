<!-- Floating AI Chat Widget -->
<?php
// Initialize chat configurations and predefined questions based on role
$currentUserRole = auth()['role'] ?? 'anggota';

$chatQuestions = [];
if ($currentUserRole === 'super_user') {
    $chatQuestions = [
        "💰 Berapa saldo kas saat ini?",
        "⚠️ Siapa yang belum bayar?",
        "📥 Berapa pemasukan bulan ini?",
        "📊 Kondisi surplus/defisit?",
    ];
} elseif ($currentUserRole === 'bendahara') {
    $chatQuestions = [
        "💰 Berapa saldo kas saat ini?",
        "⚠️ Siapa yang belum bayar?",
        "📥 Rincian pemasukan bulan ini?",
        "📤 Rincian pengeluaran bulan ini?",
    ];
} else {
    $chatQuestions = [
        "✅ Status pembayaran saya?",
        "⚠️ Berapa tunggakan saya?",
        "💳 Total setoran bulan ini?",
        "🏛️ Berapa saldo kas organisasi?",
    ];
}
?>

<div id="aiChatWidgetContainer">
    <!-- Floating Button -->
    <button id="aiChatWidgetBtn" class="btn btn-primary rounded-circle shadow-lg ai-floating-btn" aria-label="Buka Asisten AI">
        <i class="bi bi-robot fs-3"></i>
        <span class="position-absolute top-0 start-100 translate-middle p-2 bg-success border border-light rounded-circle" id="aiStatusDot">
            <span class="visually-hidden">AI Online</span>
        </span>
    </button>

    <!-- Floating Chat Panel -->
    <div id="aiChatPanel" class="card shadow-lg ai-chat-panel d-none">
        <!-- Chat Header -->
        <div class="card-header bg-gradient-primary text-white d-flex align-items-center justify-content-between p-3 border-0">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-robot fs-4"></i>
                <div>
                    <h6 class="mb-0 fw-bold">Asisten AI Sistem Kas</h6>
                    <small class="opacity-75" style="font-size: 0.75rem;">Mode: <?= e(ucwords(str_replace('_', ' ', $currentUserRole))) ?></small>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-link text-white p-0 opacity-75 hover-opacity-100" id="aiClearChatBtn" title="Bersihkan Chat">
                    <i class="bi bi-trash3 fs-5"></i>
                </button>
                <button type="button" class="btn btn-sm btn-link text-white p-0 opacity-75 hover-opacity-100" id="aiCloseChatBtn" title="Tutup Chat">
                    <i class="bi bi-x-lg fs-5"></i>
                </button>
            </div>
        </div>

        <!-- Chat Body -->
        <div class="card-body p-3 chat-body-scrollable bg-light-subtle" id="aiChatBody">
            <!-- Initial Welcome Message -->
            <div class="chat-msg bot-msg d-flex gap-2 mb-3">
                <div class="avatar-circle bg-primary text-white d-flex align-items-center justify-content-center flex-shrink-0 shadow-sm" style="width: 32px; height: 32px; font-size: 0.9rem;">
                    <i class="bi bi-robot"></i>
                </div>
                <div class="msg-content max-w-85">
                    <div class="bg-white p-2 rounded-3 shadow-sm border border-light-subtle ai-bubble">
                        <p class="mb-2 fs-7 text-dark">Halo! Saya adalah <strong>Asisten AI Sistem Kas</strong>.</p>
                        <p class="mb-2 fs-7 text-dark">Saya siap membantu Anda dengan informasi keuangan kas secara akurat dan transparan.</p>
                        <div class="d-flex flex-wrap gap-1 mt-2">
                            <?php foreach($chatQuestions as $q): ?>
                                <button type="button" class="btn btn-sm btn-outline-primary border-primary-subtle rounded-pill fs-8 ai-quick-question text-start py-1 px-2" data-query="<?= e($q) ?>">
                                    <?= e($q) ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="text-muted mt-1 ms-1 fs-9">Hari ini <?= date('H:i') ?></div>
                </div>
            </div>
        </div>

        <!-- Chat Footer (Input) -->
        <div class="card-footer bg-white p-2 border-top">
            <form id="aiChatForm" class="d-flex gap-2 align-items-center">
                <div class="input-group input-group-sm">
                    <input type="text" id="aiChatInput" class="form-control rounded-pill border-secondary-subtle px-3 py-2" placeholder="Tanyakan seputar kas..." autocomplete="off" required>
                    <button class="btn btn-primary rounded-pill px-3 ms-2 d-flex align-items-center justify-content-center" type="submit" id="aiSendBtn" style="width: 40px; height: 34px;">
                        <i class="bi bi-send-fill"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* CSS Variables & Utilities for Chat */
:root {
    --ai-primary: #0d6efd;
    --ai-primary-grad: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
}

.fs-7 { font-size: 0.9rem; }
.fs-8 { font-size: 0.8rem; }
.fs-9 { font-size: 0.7rem; }
.max-w-85 { max-width: 85%; }
.hover-opacity-100:hover { opacity: 1 !important; }

/* Floating Button */
.ai-floating-btn {
    position: fixed;
    bottom: 24px;
    right: 24px;
    width: 64px;
    height: 64px;
    z-index: 1050;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    background: var(--ai-primary-grad);
    border: none;
}
.ai-floating-btn:hover {
    transform: scale(1.08) translateY(-2px);
    box-shadow: 0 10px 25px rgba(13, 110, 253, 0.4) !important;
}

/* Chat Panel */
.ai-chat-panel {
    position: fixed;
    bottom: 100px;
    right: 24px;
    width: 360px;
    height: 550px;
    max-height: calc(100vh - 120px);
    z-index: 1050;
    border: none;
    border-radius: 1rem;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    box-shadow: 0 15px 35px rgba(0,0,0,0.15) !important;
    transition: opacity 0.3s ease, transform 0.3s ease;
    opacity: 0;
    transform: translateY(20px) scale(0.95);
    pointer-events: none;
}

.ai-chat-panel.show {
    opacity: 1;
    transform: translateY(0) scale(1);
    pointer-events: auto;
}

.bg-gradient-primary {
    background: var(--ai-primary-grad);
}

.chat-body-scrollable {
    flex-grow: 1;
    overflow-y: auto;
    scroll-behavior: smooth;
}

/* Bubbles */
.ai-bubble {
    border-radius: 0 1rem 1rem 1rem;
}
.user-bubble {
    background: var(--ai-primary-grad);
    color: white;
    border-radius: 1rem 0 1rem 1rem;
}

/* Typing Indicator */
.ai-typing-indicator span {
    display: inline-block;
    width: 5px;
    height: 5px;
    background-color: var(--ai-primary);
    border-radius: 50%;
    margin: 0 1px;
    animation: ai-bounce 1.4s infinite ease-in-out both;
}
.ai-typing-indicator span:nth-child(1) { animation-delay: -0.32s; }
.ai-typing-indicator span:nth-child(2) { animation-delay: -0.16s; }

@keyframes ai-bounce {
    0%, 80%, 100% { transform: scale(0); }
    40% { transform: scale(1); }
}

/* Mobile Responsiveness */
@media (max-width: 576px) {
    .ai-chat-panel {
        width: calc(100vw - 32px);
        right: 16px;
        bottom: 96px;
        height: 600px;
        max-height: calc(100vh - 110px);
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const chatBtn = document.getElementById('aiChatWidgetBtn');
    const chatPanel = document.getElementById('aiChatPanel');
    const closeBtn = document.getElementById('aiCloseChatBtn');
    const clearBtn = document.getElementById('aiClearChatBtn');
    const chatBody = document.getElementById('aiChatBody');
    const chatForm = document.getElementById('aiChatForm');
    const chatInput = document.getElementById('aiChatInput');
    const sendBtn = document.getElementById('aiSendBtn');
    
    // API Endpoint for AI Chat
    const chatApiUrl = '<?= url("ai-assistant/chat") ?>';
    
    // Store original welcome message HTML to restore on clear
    const initialWelcomeHTML = chatBody.innerHTML;
    
    // Session Storage Key
    const sessionKey = 'sistem_kas_ai_chat_history_<?= e($currentUserRole) ?>';

    // Toggle Chat Panel
    function toggleChat() {
        if (chatPanel.classList.contains('d-none')) {
            chatPanel.classList.remove('d-none');
            // Small timeout to allow display:block before adding opacity/transform class
            setTimeout(() => {
                chatPanel.classList.add('show');
                chatInput.focus();
                scrollToBottom();
            }, 10);
        } else {
            chatPanel.classList.remove('show');
            setTimeout(() => {
                chatPanel.classList.add('d-none');
            }, 300); // Wait for transition
        }
    }

    chatBtn.addEventListener('click', toggleChat);
    closeBtn.addEventListener('click', toggleChat);

    // Load history from sessionStorage
    function loadHistory() {
        const history = sessionStorage.getItem(sessionKey);
        if (history) {
            chatBody.innerHTML = history;
            attachQuickQuestionListeners(); // Reattach listeners to loaded HTML
            scrollToBottom();
        } else {
            attachQuickQuestionListeners();
        }
    }
    
    // Save history to sessionStorage
    function saveHistory() {
        // Only save if there are user messages (don't just save the welcome message if untouched)
        if (chatBody.querySelectorAll('.user-msg').length > 0) {
            sessionStorage.setItem(sessionKey, chatBody.innerHTML);
        }
    }

    // Clear Chat
    clearBtn.addEventListener('click', function() {
        chatBody.innerHTML = initialWelcomeHTML;
        sessionStorage.removeItem(sessionKey);
        attachQuickQuestionListeners();
    });

    // Attach listeners to quick questions
    function attachQuickQuestionListeners() {
        document.querySelectorAll('.ai-quick-question').forEach(btn => {
            // Remove old listeners to prevent duplicates if history is reloaded
            const newBtn = btn.cloneNode(true);
            btn.parentNode.replaceChild(newBtn, btn);
            
            newBtn.addEventListener('click', function() {
                const query = this.getAttribute('data-query').replace(/^[^\w\s]+/, '').trim(); // Remove emojis at the start for actual query if any, or keep it.
                // Simpler: just use raw data-query value
                const rawQuery = this.getAttribute('data-query');
                const cleanQuery = rawQuery.replace(/^[^\w\s]+/g, '').trim(); // Strip emojis for the input field visually
                
                chatInput.value = cleanQuery;
                submitQuestion(cleanQuery);
            });
        });
    }

    // Form Submit
    chatForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const query = chatInput.value.trim();
        if (query) {
            submitQuestion(query);
        }
    });

    function submitQuestion(text) {
        appendUserMessage(text);
        chatInput.value = '';
        chatInput.disabled = true;
        sendBtn.disabled = true;
        
        const loadingId = appendLoadingIndicator();
        scrollToBottom();
        saveHistory();

        fetch(chatApiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ question: text })
        })
        .then(res => {
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.json();
        })
        .then(data => {
            removeLoadingIndicator(loadingId);
            chatInput.disabled = false;
            sendBtn.disabled = false;
            chatInput.focus();

            if (data.success) {
                appendBotMessage(data.answer, data.provider, data.timestamp);
            } else {
                appendBotErrorMessage(data.error || 'Terjadi kesalahan sistem.');
            }
            scrollToBottom();
            saveHistory();
        })
        .catch(err => {
            removeLoadingIndicator(loadingId);
            chatInput.disabled = false;
            sendBtn.disabled = false;
            chatInput.focus();
            appendBotErrorMessage('Koneksi terputus atau server sedang sibuk. Coba sesaat lagi.');
            scrollToBottom();
            saveHistory();
        });
    }

    function appendUserMessage(text) {
        const timeStr = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        const html = `
            <div class="chat-msg user-msg d-flex justify-content-end mb-3">
                <div class="msg-content max-w-85 text-end">
                    <div class="p-2 user-bubble shadow-sm d-inline-block text-start">
                        <p class="mb-0 fs-7">${escapeHtml(text)}</p>
                    </div>
                    <div class="text-muted mt-1 me-1 fs-9">${timeStr}</div>
                </div>
            </div>
        `;
        chatBody.insertAdjacentHTML('beforeend', html);
    }

    function appendBotMessage(text, provider, timeStr) {
        const formatted = formatMarkdown(text);
        const html = `
            <div class="chat-msg bot-msg d-flex gap-2 mb-3">
                <div class="avatar-circle bg-primary text-white d-flex align-items-center justify-content-center flex-shrink-0 shadow-sm" style="width: 32px; height: 32px; font-size: 0.9rem;">
                    <i class="bi bi-robot"></i>
                </div>
                <div class="msg-content max-w-85">
                    <div class="bg-white p-2 rounded-3 shadow-sm border border-light-subtle ai-bubble">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <strong class="text-primary fs-8">AI Assistant</strong>
                            <span class="badge bg-light text-secondary border fs-9 rounded-pill" title="Provider">${escapeHtml(provider)}</span>
                        </div>
                        <div class="fs-7 text-dark ai-formatted-text">${formatted}</div>
                    </div>
                    <div class="text-muted mt-1 ms-1 fs-9">${timeStr || 'Baru saja'}</div>
                </div>
            </div>
        `;
        chatBody.insertAdjacentHTML('beforeend', html);
    }

    function appendBotErrorMessage(msg) {
        const timeStr = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        const html = `
            <div class="chat-msg bot-msg d-flex gap-2 mb-3">
                <div class="avatar-circle bg-danger text-white d-flex align-items-center justify-content-center flex-shrink-0 shadow-sm" style="width: 32px; height: 32px; font-size: 0.9rem;">
                    <i class="bi bi-exclamation-triangle"></i>
                </div>
                <div class="msg-content max-w-85">
                    <div class="bg-danger-subtle p-2 rounded-3 shadow-sm border border-danger-subtle text-danger ai-bubble">
                        <p class="mb-0 fs-7 fw-medium"><i class="bi bi-x-circle me-1"></i>${escapeHtml(msg)}</p>
                    </div>
                    <div class="text-muted mt-1 ms-1 fs-9">${timeStr}</div>
                </div>
            </div>
        `;
        chatBody.insertAdjacentHTML('beforeend', html);
    }

    function appendLoadingIndicator() {
        const id = 'ai-loading-' + Date.now();
        const html = `
            <div class="chat-msg bot-msg d-flex gap-2 mb-3" id="${id}">
                <div class="avatar-circle bg-primary text-white d-flex align-items-center justify-content-center flex-shrink-0 shadow-sm" style="width: 32px; height: 32px; font-size: 0.9rem;">
                    <i class="bi bi-robot"></i>
                </div>
                <div class="msg-content">
                    <div class="bg-white p-2 rounded-3 shadow-sm border border-light-subtle ai-bubble d-flex align-items-center" style="height: 38px;">
                        <div class="ai-typing-indicator"><span></span><span></span><span></span></div>
                    </div>
                </div>
            </div>
        `;
        chatBody.insertAdjacentHTML('beforeend', html);
        return id;
    }

    function removeLoadingIndicator(id) {
        const el = document.getElementById(id);
        if (el) el.remove();
    }

    function scrollToBottom() {
        chatBody.scrollTop = chatBody.scrollHeight;
    }

    function escapeHtml(str) {
        return (str || '').replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }

    function formatMarkdown(text) {
        let out = escapeHtml(text || '');
        out = out.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        out = out.replace(/\*(.*?)\*/g, '<em>$1</em>');
        out = out.replace(/\n/g, '<br>');
        return out;
    }

    // Initialize
    loadHistory();
});
</script>

<?php
// Layout parent: layouts/main
?>

<div class="container-fluid px-4 py-4">
    <!-- Header Page -->
    <div class="row align-items-center mb-4">
        <div class="col-md-7">
            <div class="d-flex align-items-center gap-3">
                <div class="p-3 bg-info bg-opacity-10 text-info rounded-4 fs-3 d-inline-flex align-items-center justify-content-center" style="width: 56px; height: 56px;">
                    <i class="bi bi-robot"></i>
                </div>
                <div>
                    <h3 class="fw-bold mb-1">Asisten Kas AI</h3>
                    <p class="text-muted small mb-0">Tanyakan status pembayaran kas dan tunggakan Anda secara pribadi.</p>
                </div>
            </div>
        </div>
        <div class="col-md-5 text-md-end mt-3 mt-md-0">
            <button class="btn btn-outline-secondary btn-sm rounded-pill px-3" id="clearChatBtn">
                <i class="bi bi-trash3 me-1"></i> Bersihkan Chat
            </button>
            <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill ms-2 px-3 py-2">
                <i class="bi bi-lock-fill me-1"></i> Data Terisolasi (Anggota)
            </span>
        </div>
    </div>

    <!-- Quick Question Chips -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-3">
            <div class="d-flex align-items-center gap-2 mb-2">
                <i class="bi bi-lightbulb text-warning fs-5"></i>
                <span class="fw-semibold text-secondary small">Contoh Pertanyaan Anggota:</span>
            </div>
            <div class="d-flex flex-wrap gap-2" id="quickQuestions">
                <button type="button" class="btn btn-sm btn-light border rounded-pill px-3 py-1 text-start quick-btn" data-query="Kamu siapa?">
                    🤖 Kamu siapa?
                </button>
                <button type="button" class="btn btn-sm btn-light border rounded-pill px-3 py-1 text-start quick-btn" data-query="Saya siapa?">
                    👤 Saya siapa?
                </button>
                <button type="button" class="btn btn-sm btn-light border rounded-pill px-3 py-1 text-start quick-btn" data-query="Bagaimana status pembayaran kas saya bulan ini?">
                    ✅ Status pembayaran kas saya?
                </button>
                <button type="button" class="btn btn-sm btn-light border rounded-pill px-3 py-1 text-start quick-btn" data-query="Berapa minggu kas yang belum saya bayar?">
                    ⚠️ Berapa minggu tunggakan saya?
                </button>
                <button type="button" class="btn btn-sm btn-light border rounded-pill px-3 py-1 text-start quick-btn" data-query="Berapa total pembayaran kas yang sudah saya bayar bulan ini?">
                    💳 Total pembayaran saya bulan ini?
                </button>
                <button type="button" class="btn btn-sm btn-light border rounded-pill px-3 py-1 text-start quick-btn" data-query="Berapa saldo kas umum organisasi?">
                    🏛️ Berapa saldo kas umum organisasi?
                </button>
            </div>
        </div>
    </div>

    <!-- Main Chat Window -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <!-- Chat Messages Container -->
        <div class="card-body p-4 bg-light-subtle" id="chatContainer" style="height: 520px; overflow-y: auto;">
            <!-- Welcome Bot Message -->
            <div class="d-flex gap-3 mb-4 chat-msg bot-msg">
                <div class="avatar-circle bg-info text-white rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 42px; height: 42px;">
                    <i class="bi bi-robot fs-5"></i>
                </div>
                <div class="flex-grow-1" style="max-width: 85%;">
                    <div class="p-3 bg-white rounded-4 shadow-sm border">
                        <div class="fw-semibold text-info mb-1">
                            Asisten Kas AI <span class="badge bg-info-subtle text-info ms-2 fs-xs">Mode Anggota</span>
                        </div>
                        <p class="mb-2">Halo! Saya adalah Asisten Kas AI pribadi Anda. Anda dapat menanyakan status pembayaran kas, riwayat setoran, serta sisa tunggakan kas Anda sendiri.</p>
                        <p class="mb-0 text-muted small"><i class="bi bi-shield-check me-1"></i> Data Anda dijamin aman dan terisolasi dari anggota lain.</p>
                    </div>
                    <div class="text-muted small mt-1 ms-1">Hari ini <?= date('H:i') ?></div>
                </div>
            </div>
        </div>

        <!-- Chat Input Form -->
        <div class="card-footer bg-white p-3 border-top">
            <form id="chatForm" class="d-flex gap-2 align-items-center">
                <div class="input-group">
                    <input type="text" id="userQuestion" class="form-control rounded-pill border-secondary-subtle px-4 py-2" placeholder="Tanyakan status pembayaran atau tunggakan kas Anda..." autocomplete="off" required>
                    <button class="btn btn-info text-white rounded-pill px-4 ms-2 d-flex align-items-center gap-2" type="submit" id="sendBtn">
                        <span>Kirim</span>
                        <i class="bi bi-send-fill"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.fs-xs { font-size: 0.725rem; }
.quick-btn { transition: all 0.2s ease; }
.quick-btn:hover { background-color: #e9ecef; border-color: #0dcaf0 !important; color: #0dcaf0; transform: translateY(-1px); }
.user-msg-bubble {
    background: linear-gradient(135deg, #0dcaf0 0%, #0aa2c0 100%);
    color: #ffffff;
    border-radius: 1.25rem 1.25rem 0.25rem 1.25rem;
}
.bot-msg-bubble {
    background: #ffffff;
    border-radius: 1.25rem 1.25rem 1.25rem 0.25rem;
    border: 1px solid #e3e6f0;
}
.typing-indicator span {
    display: inline-block;
    width: 6px;
    height: 6px;
    background-color: #0dcaf0;
    border-radius: 50%;
    animation: bounce 1.4s infinite ease-in-out both;
}
.typing-indicator span:nth-child(1) { animation-delay: -0.32s; }
.typing-indicator span:nth-child(2) { animation-delay: -0.16s; }
@keyframes bounce {
    0%, 80%, 100% { transform: scale(0); }
    40% { transform: scale(1); }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const chatForm = document.getElementById('chatForm');
    const questionInput = document.getElementById('userQuestion');
    const sendBtn = document.getElementById('sendBtn');
    const chatContainer = document.getElementById('chatContainer');
    const clearChatBtn = document.getElementById('clearChatBtn');
    const quickQuestions = document.querySelectorAll('.quick-btn');

    const postUrl = '<?= url("anggota/ai-assistant/ask") ?>';

    // Click quick question chip
    quickQuestions.forEach(btn => {
        btn.addEventListener('click', function() {
            const query = this.getAttribute('data-query');
            if (query) {
                questionInput.value = query;
                submitQuestion(query);
            }
        });
    });

    // Submit form
    chatForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const query = questionInput.value.trim();
        if (query) {
            submitQuestion(query);
        }
    });

    // Clear chat
    clearChatBtn.addEventListener('click', function() {
        const botWelcome = chatContainer.querySelector('.bot-msg');
        chatContainer.innerHTML = '';
        if (botWelcome) {
            chatContainer.appendChild(botWelcome);
        }
    });

    function submitQuestion(questionText) {
        // Append User Message
        appendUserMessage(questionText);
        questionInput.value = '';
        questionInput.disabled = true;
        sendBtn.disabled = true;

        // Append Loading Indicator
        const loadingId = appendLoadingIndicator();
        scrollToBottom();

        // AJAX POST Request
        fetch(postUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ question: questionText })
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('HTTP error ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            removeLoadingIndicator(loadingId);
            questionInput.disabled = false;
            sendBtn.disabled = false;
            questionInput.focus();

            if (data.success) {
                appendBotMessage(data.answer, data.provider, data.timestamp);
            } else {
                appendBotErrorMessage(data.error || 'Gagal memproses pertanyaan.');
            }
            scrollToBottom();
        })
        .catch(err => {
            removeLoadingIndicator(loadingId);
            questionInput.disabled = false;
            sendBtn.disabled = false;
            questionInput.focus();
            appendBotErrorMessage('Terjadi kendala jaringan atau server. Silakan coba beberapa saat lagi.');
            scrollToBottom();
        });
    }

    function appendUserMessage(text) {
        const timeStr = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        const html = `
            <div class="d-flex justify-content-end mb-4 chat-msg">
                <div class="flex-grow-1 text-end" style="max-width: 80%;">
                    <div class="p-3 user-msg-bubble shadow-sm d-inline-block text-start">
                        <p class="mb-0 text-white">${escapeHtml(text)}</p>
                    </div>
                    <div class="text-muted small mt-1 me-1">${timeStr}</div>
                </div>
            </div>
        `;
        chatContainer.insertAdjacentHTML('beforeend', html);
    }

    function appendBotMessage(answerText, providerStr, timeStr) {
        const formattedAnswer = formatMarkdownText(answerText);
        const html = `
            <div class="d-flex gap-3 mb-4 chat-msg">
                <div class="avatar-circle bg-info text-white rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 42px; height: 42px;">
                    <i class="bi bi-robot fs-5"></i>
                </div>
                <div class="flex-grow-1" style="max-width: 85%;">
                    <div class="p-3 bot-msg-bubble shadow-sm">
                        <div class="fw-semibold text-info mb-2 d-flex align-items-center justify-content-between">
                            <span>Asisten Kas AI</span>
                            <span class="badge bg-secondary-subtle text-secondary font-monospace fs-xs">${escapeHtml(providerStr)}</span>
                        </div>
                        <div class="ai-content">${formattedAnswer}</div>
                    </div>
                    <div class="text-muted small mt-1 ms-1">${timeStr || 'Baru saja'}</div>
                </div>
            </div>
        `;
        chatContainer.insertAdjacentHTML('beforeend', html);
    }

    function appendBotErrorMessage(errMsg) {
        const html = `
            <div class="d-flex gap-3 mb-4 chat-msg">
                <div class="avatar-circle bg-danger text-white rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 42px; height: 42px;">
                    <i class="bi bi-exclamation-triangle fs-5"></i>
                </div>
                <div class="flex-grow-1" style="max-width: 85%;">
                    <div class="p-3 bg-danger-subtle border border-danger-subtle rounded-4 shadow-sm text-danger">
                        <div class="fw-semibold mb-1"><i class="bi bi-shield-x me-1"></i> Kendala Analisis</div>
                        <p class="mb-0">${escapeHtml(errMsg)}</p>
                    </div>
                </div>
            </div>
        `;
        chatContainer.insertAdjacentHTML('beforeend', html);
    }

    function appendLoadingIndicator() {
        const id = 'loading-' + Date.now();
        const html = `
            <div class="d-flex gap-3 mb-4 chat-msg" id="${id}">
                <div class="avatar-circle bg-info text-white rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 42px; height: 42px;">
                    <i class="bi bi-robot fs-5"></i>
                </div>
                <div class="flex-grow-1">
                    <div class="p-3 bot-msg-bubble shadow-sm d-inline-block">
                        <div class="d-flex align-items-center gap-2 text-muted small">
                            <div class="typing-indicator"><span></span><span></span><span></span></div>
                            <span>AI sedang menganalisis data kas Anda...</span>
                        </div>
                    </div>
                </div>
            </div>
        `;
        chatContainer.insertAdjacentHTML('beforeend', html);
        return id;
    }

    function removeLoadingIndicator(id) {
        const el = document.getElementById(id);
        if (el) el.remove();
    }

    function scrollToBottom() {
        chatContainer.scrollTop = chatContainer.scrollHeight;
    }

    function escapeHtml(str) {
        return str.replace(/&/g, "&amp;")
                  .replace(/</g, "&lt;")
                  .replace(/>/g, "&gt;")
                  .replace(/"/g, "&quot;")
                  .replace(/'/g, "&#039;");
    }

    function formatMarkdownText(text) {
        let clean = escapeHtml(text);
        // Bold: **text**
        clean = clean.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        // Italic: *text*
        clean = clean.replace(/\*(.*?)\*/g, '<em>$1</em>');
        // Newlines to <br>
        clean = clean.replace(/\n/g, '<br>');
        return clean;
    }
});
</script>

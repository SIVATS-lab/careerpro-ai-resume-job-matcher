<!-- ==========================================================================
     CareerPro Suite - Global AI Assistant Widget (CareerBot)
     Include at the bottom of authenticated pages via: include 'chatbot.php';
     Features: Multi-turn memory, Markdown rendering, auto-resize input.
=========================================================================== -->
<style>
    .chat-widget-container { z-index: 1000; font-family: 'Plus Jakarta Sans', sans-serif; }
    .chat-window {
        transform-origin: bottom right;
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    }
    .dark .chat-window { box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.8); }
    .chat-window.closed { transform: scale(0.5); opacity: 0; pointer-events: none; }
    .chat-window.open   { transform: scale(1);   opacity: 1; pointer-events: all; }

    .chat-bubble { animation: popIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards; opacity: 0; transform: translateY(10px) scale(0.95); }
    @keyframes popIn { to { opacity: 1; transform: translateY(0) scale(1); } }

    .typing-dot { animation: chatTyping 1.4s infinite ease-in-out both; }
    .typing-dot:nth-child(1) { animation-delay: -0.32s; }
    .typing-dot:nth-child(2) { animation-delay: -0.16s; }
    @keyframes chatTyping { 0%, 80%, 100% { transform: scale(0); } 40% { transform: scale(1); } }

    #chat-scroll-area::-webkit-scrollbar { width: 4px; }
    #chat-scroll-area::-webkit-scrollbar-track { background: transparent; }
    #chat-scroll-area::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
    .dark #chat-scroll-area::-webkit-scrollbar-thumb { background: #334155; }

    /* Markdown rendered inside bot bubbles */
    .bot-text h3 { font-size: 0.8rem; font-weight: 800; margin: 0.6rem 0 0.25rem; color: inherit; }
    .bot-text p  { margin: 0.3rem 0; }
    .bot-text ul { list-style: disc; padding-left: 1.2rem; margin: 0.3rem 0; }
    .bot-text ol { list-style: decimal; padding-left: 1.2rem; margin: 0.3rem 0; }
    .bot-text li { margin: 0.15rem 0; }
    .bot-text strong { font-weight: 800; }
    .bot-text code { background: rgba(0,0,0,0.08); border-radius: 4px; padding: 1px 5px; font-size: 0.78rem; font-family: monospace; }
    .dark .bot-text code { background: rgba(255,255,255,0.1); }
    .bot-text pre  { background: rgba(0,0,0,0.06); border-radius: 8px; padding: 0.6rem; overflow-x: auto; margin: 0.4rem 0; }
    .dark .bot-text pre { background: rgba(255,255,255,0.05); }
    .bot-text hr   { border: none; border-top: 1px solid rgba(0,0,0,0.1); margin: 0.5rem 0; }
    .dark .bot-text hr { border-color: rgba(255,255,255,0.1); }
</style>

<div class="fixed bottom-6 right-6 chat-widget-container flex flex-col items-end">

    <!-- Chat Window -->
    <div id="careerbot-window" class="chat-window closed w-[360px] max-w-[calc(100vw-3rem)] h-[520px] max-h-[calc(100vh-6rem)] bg-white/95 dark:bg-[#0a0a0a]/95 backdrop-blur-xl rounded-[2rem] border border-slate-200 dark:border-white/10 flex flex-col mb-4 overflow-hidden">

        <!-- Header -->
        <div class="bg-pcte-50 dark:bg-pcte-900/20 border-b border-pcte-100 dark:border-pcte-500/20 p-4 flex items-center justify-between shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-pcte-600 flex items-center justify-center shadow-lg">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                </div>
                <div>
                    <h3 class="text-sm font-black text-slate-900 dark:text-white">CareerBot AI</h3>
                    <div class="flex items-center gap-1.5 mt-0.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span class="text-[9px] font-black uppercase tracking-widest text-emerald-600 dark:text-emerald-400">Gemini Active</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <!-- Clear conversation -->
                <button onclick="clearBotChat()" title="Clear conversation" class="w-8 h-8 rounded-full bg-white dark:bg-dark-800 flex items-center justify-center text-slate-400 hover:text-amber-500 transition-colors shadow-sm cursor-pointer">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                </button>
                <!-- Close -->
                <button onclick="toggleCareerBot()" class="w-8 h-8 rounded-full bg-white dark:bg-dark-800 flex items-center justify-center text-slate-400 hover:text-red-500 transition-colors shadow-sm cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>

        <!-- Suggestion chips (shown only when chat is empty) -->
        <div id="cb-suggestions" class="px-4 pt-3 pb-1 flex flex-wrap gap-2 shrink-0">
            <button onclick="cbQuickAsk('Help me write a strong resume summary')"    class="cb-chip text-[10px] font-bold px-3 py-1.5 rounded-full bg-pcte-50 dark:bg-pcte-900/20 text-pcte-600 dark:text-pcte-400 border border-pcte-200 dark:border-pcte-800 hover:bg-pcte-100 transition-colors cursor-pointer">✍️ Resume Summary</button>
            <button onclick="cbQuickAsk('Give me 5 common HR interview questions')"  class="cb-chip text-[10px] font-bold px-3 py-1.5 rounded-full bg-pcte-50 dark:bg-pcte-900/20 text-pcte-600 dark:text-pcte-400 border border-pcte-200 dark:border-pcte-800 hover:bg-pcte-100 transition-colors cursor-pointer">🎤 Interview Prep</button>
            <button onclick="cbQuickAsk('Write a cover letter for a PHP developer role')" class="cb-chip text-[10px] font-bold px-3 py-1.5 rounded-full bg-pcte-50 dark:bg-pcte-900/20 text-pcte-600 dark:text-pcte-400 border border-pcte-200 dark:border-pcte-800 hover:bg-pcte-100 transition-colors cursor-pointer">📄 Cover Letter</button>
            <button onclick="cbQuickAsk('What skills should a fresh CS graduate focus on?')" class="cb-chip text-[10px] font-bold px-3 py-1.5 rounded-full bg-pcte-50 dark:bg-pcte-900/20 text-pcte-600 dark:text-pcte-400 border border-pcte-200 dark:border-pcte-800 hover:bg-pcte-100 transition-colors cursor-pointer">🚀 Skill Roadmap</button>
        </div>

        <!-- Chat History -->
        <div id="chat-scroll-area" class="flex-1 overflow-y-auto p-4 space-y-4 bg-slate-50/50 dark:bg-[#050505]/50">

            <!-- Greeting Message -->
            <div class="flex items-start gap-3 chat-bubble">
                <div class="w-8 h-8 rounded-full bg-pcte-100 dark:bg-pcte-900/30 shrink-0 flex items-center justify-center shadow-inner mt-1">
                    <svg class="w-4 h-4 text-pcte-600 dark:text-pcte-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                </div>
                <div class="bg-white dark:bg-dark-800 border border-slate-200 dark:border-white/5 p-3.5 rounded-2xl rounded-tl-none shadow-sm max-w-[85%]">
                    <p class="text-[13px] font-medium text-slate-700 dark:text-gray-300 leading-relaxed">
                        Hi! I'm <strong>CareerBot</strong> — your AI career assistant. I can help you craft resumes, write cover letters, ace interviews, or plan your career path. What can I do for you today?
                    </p>
                </div>
            </div>

        </div>

        <!-- Input Area -->
        <div class="p-4 border-t border-slate-200 dark:border-white/10 bg-white dark:bg-dark-900 shrink-0">
            <div class="relative">
                <textarea id="careerbot-input" rows="1"
                    class="w-full bg-slate-100 dark:bg-dark-950 border border-slate-200 dark:border-white/10 rounded-2xl pl-4 pr-12 py-3 text-sm font-medium text-slate-900 dark:text-white resize-none focus:outline-none focus:border-pcte-500 shadow-inner"
                    placeholder="Ask CareerBot…"
                    onkeydown="handleBotEnter(event)"></textarea>
                <button id="cb-send-btn" onclick="sendBotMessage()" class="absolute right-2 bottom-2 w-8 h-8 rounded-xl bg-pcte-600 hover:bg-pcte-700 flex items-center justify-center text-white transition-transform active:scale-90 shadow-md cursor-pointer">
                    <svg class="w-3.5 h-3.5 transform -rotate-45 mb-0.5 ml-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                </button>
            </div>
            <div class="text-center mt-2">
                <span class="text-[8px] font-black uppercase tracking-widest text-slate-400 dark:text-gray-600">Powered by Google Gemini 1.5 Flash</span>
            </div>
        </div>
    </div>

    <!-- Floating Toggle Button -->
    <button onclick="toggleCareerBot()" class="w-16 h-16 rounded-[1.5rem] bg-gradient-to-br from-pcte-500 to-pcte-700 text-white shadow-[0_10px_30px_rgba(223,60,60,0.4)] flex items-center justify-center hover:scale-105 hover:-translate-y-1 transition-all duration-300 group cursor-pointer relative z-50">
        <svg class="w-7 h-7 group-hover:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
        <svg class="w-7 h-7 hidden group-hover:block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
    </button>
</div>

<script>
(function () {
    const cbWindow   = document.getElementById('careerbot-window');
    const cbInput    = document.getElementById('careerbot-input');
    const cbScroll   = document.getElementById('chat-scroll-area');
    const cbSendBtn  = document.getElementById('cb-send-btn');
    const cbSuggestions = document.getElementById('cb-suggestions');

    // In-memory conversation history for multi-turn context
    // Each entry: { role: 'user'|'model', text: '...' }
    let cbHistory = [];
    let cbBusy    = false;

    // Auto-resize textarea
    cbInput.addEventListener('input', function () {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 100) + 'px';
    });

    window.toggleCareerBot = function () {
        if (cbWindow.classList.contains('closed')) {
            cbWindow.classList.replace('closed', 'open');
            setTimeout(() => cbInput.focus(), 300);
        } else {
            cbWindow.classList.replace('open', 'closed');
        }
    };

    window.handleBotEnter = function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendBotMessage();
        }
    };

    window.cbQuickAsk = function (text) {
        cbInput.value = text;
        sendBotMessage();
    };

    window.clearBotChat = function () {
        cbHistory = [];
        // Keep only the greeting bubble
        const bubbles = cbScroll.querySelectorAll('.chat-bubble');
        bubbles.forEach((b, i) => { if (i > 0) b.remove(); });
        if (cbSuggestions) cbSuggestions.style.display = '';
    };

    window.sendBotMessage = async function () {
        if (cbBusy) return;
        const msg = cbInput.value.trim();
        if (!msg) return;

        // Lock UI
        cbBusy = true;
        cbSendBtn.disabled = true;
        cbSendBtn.classList.add('opacity-50');

        // Hide suggestion chips once a message is sent
        if (cbSuggestions) cbSuggestions.style.display = 'none';

        // Reset input
        cbInput.value = '';
        cbInput.style.height = 'auto';

        // Append user bubble
        appendBubble('user', escHtml(msg));
        cbScroll.scrollTop = cbScroll.scrollHeight;

        // Typing indicator
        const typingId = 'typing-' + Date.now();
        cbScroll.insertAdjacentHTML('beforeend', `
            <div id="${typingId}" class="flex items-start gap-3 chat-bubble">
                <div class="w-8 h-8 rounded-full bg-pcte-100 dark:bg-pcte-900/30 shrink-0 flex items-center justify-center shadow-inner mt-1">
                    <svg class="w-4 h-4 text-pcte-600 dark:text-pcte-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                </div>
                <div class="bg-white dark:bg-dark-800 border border-slate-200 dark:border-white/5 p-4 rounded-2xl rounded-tl-none shadow-sm flex items-center gap-1.5 h-[46px]">
                    <div class="w-1.5 h-1.5 bg-pcte-400 rounded-full typing-dot"></div>
                    <div class="w-1.5 h-1.5 bg-pcte-400 rounded-full typing-dot"></div>
                    <div class="w-1.5 h-1.5 bg-pcte-400 rounded-full typing-dot"></div>
                </div>
            </div>
        `);
        cbScroll.scrollTop = cbScroll.scrollHeight;

        try {
            const resp = await fetch('<?php
                $proto    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $basePath = defined('APP_BASE_URL') ? APP_BASE_URL : ($proto . '://' . $host . '/resume');
                echo rtrim($basePath, '/') . '/api/chat-handler.php';
            ?>', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ message: msg, history: cbHistory }),
            });

            // Handle auth failure gracefully
            if (resp.status === 401) {
                removeTyping(typingId);
                appendError('Please log in to use CareerBot.');
                return;
            }

            const rawText = await resp.text();
            let result;
            try {
                result = JSON.parse(rawText);
            } catch {
                console.error('CareerBot non-JSON response:', rawText.substring(0, 500));
                removeTyping(typingId);
                appendError('Server error — check PHP logs. Response: ' + rawText.substring(0, 100));
                return;
            }
            removeTyping(typingId);

            if (result.status === 'success') {
                const aiText = result.data;

                // Save to history for next turn
                cbHistory.push({ role: 'user',  text: msg    });
                cbHistory.push({ role: 'model', text: aiText });

                // Keep history at ≤ 10 turns (20 entries) to stay within token limits
                if (cbHistory.length > 20) cbHistory.splice(0, 2);

                appendBubble('bot', renderMarkdown(aiText));
            } else {
                appendError(result.message || 'Something went wrong. Please try again.');
            }
        } catch (err) {
            removeTyping(typingId);
            appendError('Could not reach CareerBot. Check your connection and try again.');
            console.error('CareerBot fetch error:', err);
        } finally {
            cbBusy = false;
            cbSendBtn.disabled = false;
            cbSendBtn.classList.remove('opacity-50');
            cbScroll.scrollTop = cbScroll.scrollHeight;
            cbInput.focus();
        }
    };

    // -------------------------------------------------------
    // Helpers
    // -------------------------------------------------------
    function removeTyping(id) {
        const el = document.getElementById(id);
        if (el) el.remove();
    }

    function appendBubble(role, htmlContent) {
        if (role === 'user') {
            cbScroll.insertAdjacentHTML('beforeend', `
                <div class="flex items-start justify-end gap-3 chat-bubble">
                    <div class="bg-pcte-600 text-white p-3.5 rounded-2xl rounded-tr-none shadow-md max-w-[85%]">
                        <p class="text-[13px] font-medium leading-relaxed whitespace-pre-wrap">${htmlContent}</p>
                    </div>
                </div>
            `);
        } else {
            cbScroll.insertAdjacentHTML('beforeend', `
                <div class="flex items-start gap-3 chat-bubble">
                    <div class="w-8 h-8 rounded-full bg-pcte-100 dark:bg-pcte-900/30 shrink-0 flex items-center justify-center shadow-inner mt-1">
                        <svg class="w-4 h-4 text-pcte-600 dark:text-pcte-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    </div>
                    <div class="bg-white dark:bg-dark-800 border border-slate-200 dark:border-white/5 p-3.5 rounded-2xl rounded-tl-none shadow-sm max-w-[85%] bot-text">
                        <div class="text-[13px] font-medium text-slate-700 dark:text-gray-300 leading-relaxed">${htmlContent}</div>
                    </div>
                </div>
            `);
        }
        cbScroll.scrollTop = cbScroll.scrollHeight;
    }

    function appendError(message) {
        cbScroll.insertAdjacentHTML('beforeend', `
            <div class="flex items-start gap-3 chat-bubble">
                <div class="w-8 h-8 rounded-full bg-red-100 dark:bg-red-900/30 shrink-0 flex items-center justify-center shadow-inner mt-1">
                    <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div class="bg-red-50 dark:bg-red-900/10 border border-red-200 dark:border-red-900/30 p-3.5 rounded-2xl rounded-tl-none shadow-sm max-w-[85%]">
                    <p class="text-[13px] font-bold text-red-600 dark:text-red-400 leading-relaxed">${escHtml(message)}</p>
                </div>
            </div>
        `);
        cbBusy = false;
        cbSendBtn.disabled = false;
        cbSendBtn.classList.remove('opacity-50');
        cbScroll.scrollTop = cbScroll.scrollHeight;
    }

    function escHtml(str) {
        return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    /**
     * Lightweight Markdown → HTML converter for bot responses.
     * Handles: ### headers, **bold**, `code`, ```blocks```, - bullets, numbered lists, horizontal rules.
     */
    function renderMarkdown(text) {
        // Escape HTML first to prevent XSS, then apply markdown transforms
        let s = escHtml(text);

        // Code blocks (``` ... ```) — must come before inline code
        s = s.replace(/```[\w]*\n?([\s\S]*?)```/g, '<pre><code>$1</code></pre>');

        // Inline code
        s = s.replace(/`([^`\n]+)`/g, '<code>$1</code>');

        // Headers ### ## #
        s = s.replace(/^### (.+)$/gm, '<h3>$1</h3>');
        s = s.replace(/^## (.+)$/gm,  '<h3>$1</h3>');
        s = s.replace(/^# (.+)$/gm,   '<h3>$1</h3>');

        // Bold **text**
        s = s.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');

        // Italic *text*
        s = s.replace(/\*([^*\n]+)\*/g, '<em>$1</em>');

        // Horizontal rule ---
        s = s.replace(/^---+$/gm, '<hr>');

        // Unordered lists  (lines starting with - or *)
        s = s.replace(/^[\-\*] (.+)$/gm, '<li>$1</li>');
        s = s.replace(/(<li>[\s\S]+?<\/li>)/g, '<ul>$1</ul>');
        // Collapse consecutive </ul><ul> into nothing
        s = s.replace(/<\/ul>\s*<ul>/g, '');

        // Ordered lists (lines starting with 1. 2. etc.)
        s = s.replace(/^\d+\. (.+)$/gm, '<li>$1</li>');
        // wrap orphan <li> not already in a list
        s = s.replace(/(?<!<\/?[uo]l>)\s*(<li>[\s\S]+?<\/li>)\s*(?!<\/[uo]l>)/g,
            '<ol>$1</ol>');
        s = s.replace(/<\/ol>\s*<ol>/g, '');

        // Paragraphs — wrap double-newline-separated blocks
        s = s.split(/\n{2,}/).map(block => {
            block = block.trim();
            if (!block) return '';
            // Don't wrap blocks that are already block-level elements
            if (/^<(h[1-6]|ul|ol|pre|hr|li)/.test(block)) return block;
            return '<p>' + block.replace(/\n/g, '<br>') + '</p>';
        }).join('\n');

        return s;
    }
})();
</script>

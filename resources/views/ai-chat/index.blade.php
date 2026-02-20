<x-app-layout>
<style>
    @import url('https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap');

    :root {
        --bg:        #0c0e14;
        --surface:   #13161f;
        --surface2:  #1a1e2b;
        --border:    #252a3a;
        --accent:    #4fffb0;
        --accent2:   #7b61ff;
        --text:      #e8eaf2;
        --muted:     #6b7194;
        --user-bg:   #1e2236;
        --ai-bg:     #131920;
        --danger:    #ff4f6b;
        --radius:    14px;
        --font-main: 'Syne', sans-serif;
        --font-mono: 'JetBrains Mono', monospace;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }

    .chat-layout {
        display: grid;
        grid-template-columns: 300px 1fr;
        height: 80vh;
        border-radius: 16px;
        overflow: hidden;
        font-family: var(--font-main);
        color: var(--text);
        background: var(--bg);
    }

    .sidebar {
        background: var(--surface);
        border-right: 1px solid var(--border);
        display: flex;
        flex-direction: column;
        padding: 24px 18px;
        gap: 20px;
        overflow-y: auto;
    }

    .sidebar-logo {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 1rem;
        font-weight: 800;
        letter-spacing: -0.02em;
    }

    .logo-dot {
        width: 10px; height: 10px;
        border-radius: 50%;
        background: var(--accent);
        box-shadow: 0 0 10px var(--accent);
        animation: pulse 2s ease-in-out infinite;
    }

    @keyframes pulse {
        0%, 100% { opacity: 1; transform: scale(1); }
        50%       { opacity: 0.6; transform: scale(0.85); }
    }

    .upload-zone {
        border: 2px dashed var(--border);
        border-radius: var(--radius);
        padding: 20px 14px;
        text-align: center;
        cursor: pointer;
        transition: border-color 0.2s, background 0.2s;
        position: relative;
    }
    .upload-zone:hover, .upload-zone.drag-over {
        border-color: var(--accent);
        background: rgba(79,255,176,0.04);
    }
    .upload-zone input[type="file"] {
        position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%;
    }
    .upload-icon { font-size: 1.8rem; margin-bottom: 6px; }
    .upload-label { font-size: 0.78rem; color: var(--muted); line-height: 1.5; }
    .upload-label strong { color: var(--accent); display: block; font-size: 0.85rem; margin-bottom: 3px; }

    .file-badge {
        background: rgba(79,255,176,0.08);
        border: 1px solid rgba(79,255,176,0.25);
        border-radius: 8px;
        padding: 10px 12px;
        font-size: 0.78rem;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .file-badge .fname { color: var(--accent); font-weight: 600; flex: 1; word-break: break-all; }
    .file-badge .fmeta { color: var(--muted); font-size: 0.7rem; }

    .preview-section { flex: 1; overflow: auto; }
    .preview-section h3 { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.1em; color: var(--muted); margin-bottom: 8px; }
    .preview-table { width: 100%; border-collapse: collapse; font-family: var(--font-mono); font-size: 0.67rem; }
    .preview-table th {
        background: var(--surface2); color: var(--accent);
        padding: 5px 7px; text-align: left; white-space: nowrap;
        border-bottom: 1px solid var(--border);
    }
    .preview-table td {
        padding: 4px 7px; border-bottom: 1px solid var(--border);
        color: var(--muted); white-space: nowrap;
        max-width: 90px; overflow: hidden; text-overflow: ellipsis;
    }

    .sidebar-footer { margin-top: auto; }
    .btn-ghost {
        width: 100%; background: transparent;
        border: 1px solid var(--border); color: var(--muted);
        padding: 8px 12px; border-radius: 8px;
        font-family: var(--font-main); font-size: 0.8rem;
        cursor: pointer; transition: all 0.2s;
        display: flex; align-items: center; gap: 8px;
    }
    .btn-ghost:hover { border-color: var(--danger); color: var(--danger); }

    .chat-main { display: flex; flex-direction: column; background: var(--bg); }

    .chat-header {
        padding: 16px 24px;
        border-bottom: 1px solid var(--border);
        display: flex; align-items: center; gap: 12px;
        background: var(--surface);
    }
    .chat-header-icon {
        width: 36px; height: 36px; border-radius: 9px;
        background: linear-gradient(135deg, var(--accent2), var(--accent));
        display: flex; align-items: center; justify-content: center; font-size: 1.1rem;
    }
    .chat-header-title { font-size: 0.95rem; font-weight: 700; }
    .chat-header-sub   { font-size: 0.73rem; color: var(--muted); margin-top: 1px; }
    .status-badge {
        margin-left: auto; font-size: 0.7rem;
        padding: 3px 9px; border-radius: 20px;
        background: rgba(79,255,176,0.1); color: var(--accent);
        border: 1px solid rgba(79,255,176,0.2);
    }

    .messages-container {
        flex: 1; overflow-y: auto;
        padding: 20px 24px;
        display: flex; flex-direction: column; gap: 16px;
        scroll-behavior: smooth;
    }
    .messages-container::-webkit-scrollbar { width: 5px; }
    .messages-container::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }

    .empty-state {
        flex: 1; display: flex; flex-direction: column;
        align-items: center; justify-content: center;
        gap: 16px; padding: 30px; text-align: center;
    }
    .empty-state-icon { font-size: 2.8rem; }
    .empty-state h2   { font-size: 1.2rem; font-weight: 800; }
    .empty-state p    { font-size: 0.83rem; color: var(--muted); max-width: 340px; line-height: 1.6; }

    .suggestion-chips { display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; max-width: 480px; }
    .chip {
        background: var(--surface2); border: 1px solid var(--border);
        border-radius: 20px; padding: 6px 14px;
        font-size: 0.78rem; cursor: pointer; transition: all 0.2s; color: var(--text);
        font-family: var(--font-main);
    }
    .chip:hover { border-color: var(--accent); color: var(--accent); background: rgba(79,255,176,0.06); }

    .message { display: flex; gap: 10px; animation: fadeSlideUp 0.25s ease; }
    @keyframes fadeSlideUp {
        from { opacity: 0; transform: translateY(8px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    .message.user { flex-direction: row-reverse; }

    .avatar {
        width: 32px; height: 32px; border-radius: 9px;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.9rem; flex-shrink: 0;
    }
    .avatar.ai   { background: linear-gradient(135deg, var(--accent2), var(--accent)); }
    .avatar.user { background: var(--surface2); border: 1px solid var(--border); }

    .bubble {
        max-width: 72%; padding: 12px 16px; border-radius: 14px;
        font-size: 0.875rem; line-height: 1.65;
    }
    .bubble.ai {
        background: var(--ai-bg); border: 1px solid var(--border); border-top-left-radius: 4px;
    }
    .bubble.user {
        background: var(--user-bg); border: 1px solid rgba(123,97,255,0.25); border-top-right-radius: 4px;
    }
    .bubble.ai strong { color: var(--accent); }
    .bubble.ai code {
        background: var(--surface2); padding: 1px 5px; border-radius: 4px;
        font-family: var(--font-mono); font-size: 0.8rem; color: var(--accent2);
    }
    .bubble.ai pre {
        background: var(--surface2); border: 1px solid var(--border);
        border-radius: 8px; padding: 12px; overflow-x: auto; margin-top: 8px;
        font-family: var(--font-mono); font-size: 0.78rem;
    }
    .bubble.ai ul, .bubble.ai ol { padding-left: 1.3em; margin-top: 5px; }
    .bubble.ai li { margin-bottom: 3px; }

    .msg-meta { font-size: 0.65rem; color: var(--muted); margin-top: 3px; }
    .message.user .msg-meta { text-align: right; }

    .typing-dots { display: flex; gap: 5px; padding: 3px 0; }
    .typing-dots span {
        width: 6px; height: 6px; background: var(--accent);
        border-radius: 50%; animation: bounce 1.2s ease-in-out infinite;
    }
    .typing-dots span:nth-child(2) { animation-delay: 0.2s; }
    .typing-dots span:nth-child(3) { animation-delay: 0.4s; }
    @keyframes bounce {
        0%, 80%, 100% { transform: translateY(0); opacity: 0.4; }
        40%            { transform: translateY(-5px); opacity: 1; }
    }

    .input-bar { padding: 16px 24px; border-top: 1px solid var(--border); background: var(--surface); }
    .input-wrapper {
        display: flex; align-items: flex-end; gap: 10px;
        background: var(--surface2); border: 1px solid var(--border);
        border-radius: var(--radius); padding: 10px 14px;
        transition: border-color 0.2s;
    }
    .input-wrapper:focus-within { border-color: var(--accent2); }
    .input-wrapper textarea {
        flex: 1; background: transparent; border: none; outline: none;
        color: var(--text); font-family: var(--font-main); font-size: 0.88rem;
        resize: none; line-height: 1.5; max-height: 120px; overflow-y: auto;
    }
    .input-wrapper textarea::placeholder { color: var(--muted); }
    .send-btn {
        width: 36px; height: 36px; border-radius: 9px;
        background: linear-gradient(135deg, var(--accent2), var(--accent));
        border: none; cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.95rem; transition: opacity 0.2s, transform 0.1s; flex-shrink: 0;
    }
    .send-btn:hover:not(:disabled) { opacity: 0.88; transform: scale(1.05); }
    .send-btn:disabled { opacity: 0.35; cursor: not-allowed; }
    .input-hint { font-size: 0.68rem; color: var(--muted); margin-top: 6px; text-align: center; }

    .error-toast {
        background: rgba(255,79,107,0.12); border: 1px solid rgba(255,79,107,0.3);
        color: var(--danger); border-radius: 8px; padding: 9px 13px; font-size: 0.8rem;
    }
</style>

<div class="chat-layout" x-data="aiChat()" x-init="init()">
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('AI Data Analyst') }}
        </h2>
    </x-slot>

    {{-- ═══ SIDEBAR ═══ --}}
    <aside class="sidebar">
        <div class="sidebar-logo">
            <span class="logo-dot"></span>
            DataAI Assistant
        </div>

        <div
            class="upload-zone"
            :class="{ 'drag-over': isDragging }"
            @dragover.prevent="isDragging = true"
            @dragleave="isDragging = false"
            @drop.prevent="handleDrop($event)"
        >
            <input type="file" accept=".csv" @change="handleFileSelect($event)" />
            <div class="upload-icon">📂</div>
            <div class="upload-label">
                <strong>Glissez votre CSV ici</strong>
                ou cliquez pour parcourir
            </div>
        </div>

        <template x-if="csvFile">
            <div class="file-badge">
                <span>📊</span>
                <div>
                    <div class="fname" x-text="csvFile.name"></div>
                    <div class="fmeta" x-text="`${csvFile.rows} lignes · ${csvFile.cols} colonnes`"></div>
                </div>
            </div>
        </template>

        <template x-if="previewData">
            <div class="preview-section">
                <h3>Aperçu des données</h3>
                <div style="overflow-x:auto;">
                    <table class="preview-table">
                        <thead>
                            <tr>
                                <template x-for="h in previewData.headers" :key="h">
                                    <th x-text="h"></th>
                                </template>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(row, i) in previewData.rows" :key="i">
                                <tr>
                                    <template x-for="h in previewData.headers" :key="h">
                                        <td x-text="row[h] ?? '—'"></td>
                                    </template>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>

        <div class="sidebar-footer">
            <button class="btn-ghost" @click="clearHistory()" :disabled="messages.length === 0">
                🗑️ Effacer la conversation
            </button>
        </div>
    </aside>

    {{-- ═══ MAIN CHAT ═══ --}}
    <main class="chat-main">
        <div class="chat-header">
            <div class="chat-header-icon">🤖</div>
            <div>
                <div class="chat-header-title">AI Data Analyst</div>
                <div class="chat-header-sub">Posez vos questions sur vos données CSV</div>
            </div>
            <div class="status-badge" x-text="csvFile ? '● CSV chargé' : '○ En attente'"></div>
        </div>

        <div class="messages-container" id="messages-container">
            <template x-if="messages.length === 0">
                <div class="empty-state">
                    <div class="empty-state-icon">🧠</div>
                    <h2>Bienvenue dans DataAI</h2>
                    <p>Uploadez un fichier CSV dans le panneau de gauche, puis posez vos questions en langage naturel.</p>
                    <div class="suggestion-chips">
                        <span class="chip" @click="setQuestion('Combien de lignes contient ce fichier ?')">📊 Combien de lignes ?</span>
                        <span class="chip" @click="setQuestion('Quelles sont les colonnes disponibles ?')">📋 Colonnes</span>
                        <span class="chip" @click="setQuestion('Quels sont les 5 éléments les plus importants ?')">🏆 Top 5</span>
                        <span class="chip" @click="setQuestion('Y a-t-il des valeurs manquantes ?')">🔍 Anomalies</span>
                        <span class="chip" @click="setQuestion('Fais-moi un résumé statistique')">📈 Résumé stats</span>
                    </div>
                </div>
            </template>

            <template x-for="(msg, idx) in messages" :key="idx">
                <div class="message" :class="msg.role">
                    <div class="avatar" :class="msg.role">
                        <span x-text="msg.role === 'user' ? '👤' : '🤖'"></span>
                    </div>
                    <div>
                        <div class="bubble" :class="msg.role" x-html="msg.role === 'ai' ? renderMarkdown(msg.content) : escapeHtml(msg.content)"></div>
                        <div class="msg-meta" x-text="msg.time"></div>
                    </div>
                </div>
            </template>

            <template x-if="isTyping">
                <div class="message ai">
                    <div class="avatar ai">🤖</div>
                    <div>
                        <div class="bubble ai">
                            <div class="typing-dots"><span></span><span></span><span></span></div>
                        </div>
                    </div>
                </div>
            </template>

            <template x-if="errorMsg">
                <div class="error-toast" x-text="'⚠️ ' + errorMsg"></div>
            </template>
        </div>

        <div class="input-bar">
            <div class="input-wrapper">
                <textarea
                    x-model="userInput"
                    placeholder="Ex: Quel produit a le plus de ventes ?"
                    rows="1"
                    @keydown.enter.prevent="handleEnter($event)"
                    @input="autoResize($event)"
                    :disabled="isTyping || !csvFile"
                ></textarea>
                <button
                    class="send-btn"
                    @click="sendMessage()"
                    :disabled="isTyping || !userInput.trim() || !csvFile"
                    title="Envoyer"
                >➤</button>
            </div>
            <div class="input-hint"
                x-text="csvFile ? 'Enter pour envoyer · Shift+Enter pour nouvelle ligne' : '⬅️ Uploadez d\'abord un fichier CSV'">
            </div>
        </div>
    </main>
    
</div>



<script src="https://cdnjs.cloudflare.com/ajax/libs/marked/9.1.6/marked.min.js"></script>
<script>
function aiChat() {
    return {
        userInput:   '',
        messages:    [],
        isTyping:    false,
        errorMsg:    null,
        csvFile:     null,
        csvPath:     null,
        previewData: null,
        isDragging:  false,

        init() {
            marked.setOptions({ breaks: true, gfm: true });
        },

        handleFileSelect(event) {
            const file = event.target.files[0];
            if (file) this.uploadFile(file);
        },

        handleDrop(event) {
            this.isDragging = false;
            const file = event.dataTransfer.files[0];
            if (file && file.name.endsWith('.csv')) this.uploadFile(file);
        },

        async uploadFile(file) {
            this.errorMsg = null;
            this.previewData = null;
            this.csvFile = null;

            const formData = new FormData();
            formData.append('csv_file', file);
            formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);

            try {
                const res  = await fetch('/ai-chat/upload', { method: 'POST', body: formData });
                const data = await res.json();

                if (data.success) {
                    this.csvPath     = data.csv_path;
                    this.previewData = data.preview;
                    this.csvFile     = {
                        name: file.name,
                        rows: data.preview?.rows?.length ?? '?',
                        cols: data.preview?.headers?.length ?? '?',
                    };
                    this.messages = [];
                } else {
                    this.errorMsg = data.message ?? 'Erreur lors de l\'upload.';
                }
            } catch (e) {
                this.errorMsg = 'Erreur réseau lors de l\'upload.';
            }
        },

        setQuestion(q) { this.userInput = q; },

        handleEnter(event) {
            if (!event.shiftKey) this.sendMessage();
        },

        async sendMessage() {
            const message = this.userInput.trim();
            if (!message || this.isTyping || !this.csvFile) return;

            this.userInput = '';
            this.errorMsg  = null;
            this.messages.push({ role: 'user', content: message, time: this.now() });
            this.isTyping = true;
            this.$nextTick(() => this.scrollToBottom());

            try {
                const res  = await fetch('/ai-chat/chat', {
                    method:  'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ message, csv_path: this.csvPath }),
                });
                const data = await res.json();

                if (data.success) {
                    this.messages.push({ role: 'ai', content: data.reply, time: this.now() });
                } else {
                    this.errorMsg = data.error ?? 'Une erreur est survenue.';
                }
            } catch (e) {
                this.errorMsg = 'Erreur réseau.';
            } finally {
                this.isTyping = false;
                this.$nextTick(() => this.scrollToBottom());
            }
        },

        async clearHistory() {
            await fetch('/ai-chat/clear', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            });
            this.messages = [];
            this.errorMsg = null;
        },

        scrollToBottom() {
            const c = document.getElementById('messages-container');
            if (c) c.scrollTop = c.scrollHeight;
        },

        autoResize(event) {
            const el = event.target;
            el.style.height = 'auto';
            el.style.height = Math.min(el.scrollHeight, 120) + 'px';
        },

        renderMarkdown(text) { return marked.parse(text || ''); },

        escapeHtml(text) {
            const d = document.createElement('div');
            d.textContent = text;
            return d.innerHTML;
        },

        now() {
            return new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
        },
    };
}
</script>
</x-app-layout>
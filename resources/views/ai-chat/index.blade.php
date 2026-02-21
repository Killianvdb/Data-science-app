<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('AI Chat') }}
        </h2>
    </x-slot>
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
        height: 82vh;
        border-radius: 16px;
        overflow: hidden;
        font-family: var(--font-main);
        color: var(--text);
        background: var(--bg);
    }

    /* ── Sidebar ── */
    .sidebar {
        background: var(--surface);
        border-right: 1px solid var(--border);
        display: flex;
        flex-direction: column;
        padding: 20px 16px;
        gap: 16px;
        overflow-y: auto;
    }
    .sidebar::-webkit-scrollbar { width: 4px; }
    .sidebar::-webkit-scrollbar-thumb { background: var(--border); border-radius: 2px; }

    .sidebar-logo {
        display: flex; align-items: center; gap: 10px;
        font-size: 1rem; font-weight: 800; letter-spacing: -0.02em;
    }
    .logo-dot {
        width: 10px; height: 10px; border-radius: 50%;
        background: var(--accent); box-shadow: 0 0 10px var(--accent);
        animation: pulse 2s ease-in-out infinite;
    }
    @keyframes pulse {
        0%, 100% { opacity: 1; transform: scale(1); }
        50%       { opacity: 0.6; transform: scale(0.85); }
    }

    /* ── Upload zone ── */
    .upload-zone {
        border: 2px dashed var(--border); border-radius: var(--radius);
        padding: 18px 12px; text-align: center; cursor: pointer;
        transition: border-color 0.2s, background 0.2s; position: relative;
    }
    .upload-zone:hover, .upload-zone.drag-over {
        border-color: var(--accent); background: rgba(79,255,176,0.04);
    }
    .upload-zone input[type="file"] {
        position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%;
    }
    .upload-icon { font-size: 1.6rem; margin-bottom: 5px; }
    .upload-label { font-size: 0.75rem; color: var(--muted); line-height: 1.5; }
    .upload-label strong { color: var(--accent); display: block; font-size: 0.82rem; margin-bottom: 2px; }
    .upload-limit { font-size: 0.68rem; color: var(--muted); margin-top: 4px; }

    /* ── Files list ── */
    .files-section h3 {
        font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.1em;
        color: var(--muted); margin-bottom: 8px;
        display: flex; align-items: center; justify-content: space-between;
    }
    .files-count {
        background: rgba(79,255,176,0.15); color: var(--accent);
        border-radius: 10px; padding: 1px 7px; font-size: 0.65rem;
    }
    .file-item {
        background: var(--surface2); border: 1px solid var(--border);
        border-radius: 8px; padding: 8px 10px; margin-bottom: 6px;
        display: flex; align-items: center; gap: 8px;
        animation: fadeSlideUp 0.2s ease;
    }
    .file-item-info { flex: 1; min-width: 0; }
    .file-item-name {
        font-size: 0.78rem; color: var(--accent); font-weight: 600;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .file-item-meta { font-size: 0.68rem; color: var(--muted); margin-top: 1px; }
    .file-remove {
        width: 20px; height: 20px; border-radius: 5px; border: none;
        background: transparent; color: var(--muted); cursor: pointer;
        font-size: 0.75rem; display: flex; align-items: center; justify-content: center;
        transition: all 0.15s; flex-shrink: 0;
    }
    .file-remove:hover { background: rgba(255,79,107,0.15); color: var(--danger); }

    /* ── Preview ── */
    .preview-section { flex: 1; overflow: auto; min-height: 0; }
    .preview-section h3 { font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.1em; color: var(--muted); margin-bottom: 8px; }
    .preview-table { width: 100%; border-collapse: collapse; font-family: var(--font-mono); font-size: 0.65rem; }
    .preview-table th {
        background: var(--surface2); color: var(--accent);
        padding: 4px 6px; text-align: left; white-space: nowrap;
        border-bottom: 1px solid var(--border);
    }
    .preview-table td {
        padding: 3px 6px; border-bottom: 1px solid var(--border);
        color: var(--muted); white-space: nowrap; max-width: 80px;
        overflow: hidden; text-overflow: ellipsis;
    }

    /* ── Sidebar footer ── */
    .sidebar-footer { margin-top: auto; display: flex; flex-direction: column; gap: 6px; }
    .btn-ghost {
        width: 100%; background: transparent; border: 1px solid var(--border);
        color: var(--muted); padding: 7px 12px; border-radius: 8px;
        font-family: var(--font-main); font-size: 0.78rem; cursor: pointer;
        transition: all 0.2s; display: flex; align-items: center; gap: 7px;
    }
    .btn-ghost:hover:not(:disabled) { border-color: var(--danger); color: var(--danger); }
    .btn-ghost:disabled { opacity: 0.35; cursor: not-allowed; }

    /* ── Main chat ── */
    .chat-main { display: flex; flex-direction: column; background: var(--bg); }

    .chat-header {
        padding: 14px 22px; border-bottom: 1px solid var(--border);
        display: flex; align-items: center; gap: 12px; background: var(--surface);
    }
    .chat-header-icon {
        width: 34px; height: 34px; border-radius: 9px;
        background: linear-gradient(135deg, var(--accent2), var(--accent));
        display: flex; align-items: center; justify-content: center; font-size: 1rem;
    }
    .chat-header-title { font-size: 0.92rem; font-weight: 700; }
    .chat-header-sub   { font-size: 0.71rem; color: var(--muted); margin-top: 1px; }
    .status-badge {
        margin-left: auto; font-size: 0.68rem; padding: 3px 9px; border-radius: 20px;
        background: rgba(79,255,176,0.1); color: var(--accent);
        border: 1px solid rgba(79,255,176,0.2); white-space: nowrap;
    }

    /* ── Messages ── */
    .messages-container {
        flex: 1; overflow-y: auto; padding: 18px 22px;
        display: flex; flex-direction: column; gap: 14px; scroll-behavior: smooth;
    }
    .messages-container::-webkit-scrollbar { width: 5px; }
    .messages-container::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }

    .empty-state {
        flex: 1; display: flex; flex-direction: column;
        align-items: center; justify-content: center;
        gap: 14px; padding: 30px; text-align: center;
    }
    .empty-state-icon { font-size: 2.6rem; }
    .empty-state h2   { font-size: 1.15rem; font-weight: 800; }
    .empty-state p    { font-size: 0.82rem; color: var(--muted); max-width: 320px; line-height: 1.6; }

    .suggestion-chips { display: flex; flex-wrap: wrap; gap: 7px; justify-content: center; max-width: 460px; }
    .chip {
        background: var(--surface2); border: 1px solid var(--border);
        border-radius: 20px; padding: 5px 13px; font-size: 0.76rem;
        cursor: pointer; transition: all 0.2s; color: var(--text); font-family: var(--font-main);
    }
    .chip:hover { border-color: var(--accent); color: var(--accent); background: rgba(79,255,176,0.06); }

    .message { display: flex; gap: 9px; animation: fadeSlideUp 0.25s ease; }
    @keyframes fadeSlideUp {
        from { opacity: 0; transform: translateY(8px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    .message.user { flex-direction: row-reverse; }

    .avatar {
        width: 30px; height: 30px; border-radius: 8px;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.85rem; flex-shrink: 0;
    }
    .avatar.ai   { background: linear-gradient(135deg, var(--accent2), var(--accent)); }
    .avatar.user { background: var(--surface2); border: 1px solid var(--border); }

    .bubble {
        max-width: 74%; padding: 11px 15px; border-radius: 13px;
        font-size: 0.875rem; line-height: 1.65;
    }
    .bubble.ai   { background: var(--ai-bg); border: 1px solid var(--border); border-top-left-radius: 4px; }
    .bubble.user { background: var(--user-bg); border: 1px solid rgba(123,97,255,0.25); border-top-right-radius: 4px; }
    .bubble.ai strong { color: var(--accent); }
    .bubble.ai code {
        background: var(--surface2); padding: 1px 5px; border-radius: 4px;
        font-family: var(--font-mono); font-size: 0.78rem; color: var(--accent2);
    }
    .bubble.ai pre {
        background: var(--surface2); border: 1px solid var(--border);
        border-radius: 8px; padding: 11px; overflow-x: auto; margin-top: 8px;
        font-family: var(--font-mono); font-size: 0.76rem;
    }
    .bubble.ai ul, .bubble.ai ol { padding-left: 1.3em; margin-top: 4px; }
    .bubble.ai li { margin-bottom: 3px; }
    .bubble.ai table { border-collapse: collapse; width: 100%; margin-top: 8px; font-size: 0.8rem; }
    .bubble.ai th { background: var(--surface2); color: var(--accent); padding: 5px 8px; text-align: left; border: 1px solid var(--border); }
    .bubble.ai td { padding: 4px 8px; border: 1px solid var(--border); color: var(--muted); }

    .msg-meta { font-size: 0.63rem; color: var(--muted); margin-top: 3px; }
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

    /* ── Input bar ── */
    .input-bar { padding: 14px 22px; border-top: 1px solid var(--border); background: var(--surface); }
    .input-wrapper {
        display: flex; align-items: flex-end; gap: 10px;
        background: var(--surface2); border: 1px solid var(--border);
        border-radius: var(--radius); padding: 10px 14px; transition: border-color 0.2s;
    }
    .input-wrapper:focus-within { border-color: var(--accent2); }
    .input-wrapper textarea {
        flex: 1; background: transparent; border: none; outline: none;
        color: var(--text); font-family: var(--font-main); font-size: 0.88rem;
        resize: none; line-height: 1.5; max-height: 120px; overflow-y: auto;
    }
    .input-wrapper textarea::placeholder { color: var(--muted); }
    .send-btn {
        width: 34px; height: 34px; border-radius: 8px;
        background: linear-gradient(135deg, var(--accent2), var(--accent));
        border: none; cursor: pointer; display: flex; align-items: center;
        justify-content: center; font-size: 0.9rem;
        transition: opacity 0.2s, transform 0.1s; flex-shrink: 0;
    }
    .send-btn:hover:not(:disabled) { opacity: 0.88; transform: scale(1.05); }
    .send-btn:disabled { opacity: 0.35; cursor: not-allowed; }
    .input-hint { font-size: 0.67rem; color: var(--muted); margin-top: 6px; text-align: center; }

    .error-toast {
        background: rgba(255,79,107,0.12); border: 1px solid rgba(255,79,107,0.3);
        color: var(--danger); border-radius: 8px; padding: 8px 12px; font-size: 0.78rem;
    }

    .file-pills { display: flex; gap: 6px; flex-wrap: wrap; margin-left: auto; }
    .file-pill {
        font-size: 0.65rem; padding: 2px 8px; border-radius: 10px;
        background: rgba(123,97,255,0.15); border: 1px solid rgba(123,97,255,0.3);
        color: #a78bfa; white-space: nowrap;
    }
</style>

<div class="chat-layout" x-data="aiChat()" x-init="init()">

    {{-- ═══ SIDEBAR ═══ --}}
    <aside class="sidebar">
        <div class="sidebar-logo">
            <span class="logo-dot"></span>
            DataAI Assistant
        </div>

        {{-- Upload zone --}}
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
                <strong>Drop your CSV here</strong>
                or click to browse
            </div>
            <div class="upload-limit" x-text="`${csvFiles.length}/5 files loaded`"></div>
        </div>

        {{-- Files list --}}
        <template x-if="csvFiles.length > 0">
            <div class="files-section">
                <h3>
                    Loaded files
                    <span class="files-count" x-text="csvFiles.length"></span>
                </h3>
                <template x-for="(file, idx) in csvFiles" :key="file.path">
                    <div class="file-item">
                        <span style="font-size:1rem;">📊</span>
                        <div class="file-item-info">
                            <div class="file-item-name" x-text="file.name"></div>
                            <div class="file-item-meta" x-text="`${file.cols} columns · ${file.uploaded}`"></div>
                        </div>
                        <button class="file-remove" @click="removeFile(file)" title="Remove">✕</button>
                    </div>
                </template>
            </div>
        </template>

        {{-- Preview of last uploaded file --}}
        <template x-if="previewData">
            <div class="preview-section">
                <h3>Preview — <span x-text="previewFileName" style="color:var(--accent);text-transform:none;letter-spacing:0;font-size:0.7rem;"></span></h3>
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

        {{-- Sidebar footer --}}
        <div class="sidebar-footer">
            <button class="btn-ghost" @click="clearHistory()" :disabled="messages.length === 0">
                🗑️ Clear conversation
            </button>
            <button class="btn-ghost" @click="clearAll()" :disabled="csvFiles.length === 0">
                🗂️ Reset everything
            </button>
        </div>
    </aside>

    {{-- ═══ MAIN CHAT ═══ --}}
    <main class="chat-main">
        <div class="chat-header">
            <div class="chat-header-icon">🤖</div>
            <div>
                <div class="chat-header-title">AI Data Analyst</div>
                <div class="chat-header-sub" x-text="csvFiles.length > 1 ? `Analyzing ${csvFiles.length} files simultaneously` : 'Ask questions about your CSV data'"></div>
            </div>
            <template x-if="csvFiles.length > 0">
                <div class="file-pills">
                    <template x-for="f in csvFiles.slice(0,3)" :key="f.path">
                        <span class="file-pill" x-text="f.name.length > 12 ? f.name.slice(0,12)+'…' : f.name"></span>
                    </template>
                    <template x-if="csvFiles.length > 3">
                        <span class="file-pill" x-text="`+${csvFiles.length - 3} more`"></span>
                    </template>
                </div>
            </template>
            <template x-if="csvFiles.length === 0">
                <div class="status-badge">○ Waiting for file</div>
            </template>
        </div>

        <div class="messages-container" id="messages-container">
            <template x-if="messages.length === 0">
                <div class="empty-state">
                    <div class="empty-state-icon">🧠</div>
                    <h2>Welcome to DataAI</h2>
                    <p>Upload up to <strong style="color:var(--accent)">5 CSV files</strong> in the left panel. The AI will analyze them together and answer your questions.</p>
                    <div class="suggestion-chips">
                        <span class="chip" @click="setQuestion('How many rows does each file have?')">📊 File summary</span>
                        <span class="chip" @click="setQuestion('Compare the data across all files')">🔄 Compare files</span>
                        <span class="chip" @click="setQuestion('What are the top 5 most important items?')">🏆 Top 5</span>
                        <span class="chip" @click="setQuestion('Are there any missing or anomalous values?')">🔍 Find anomalies</span>
                        <span class="chip" @click="setQuestion('Give me a statistical summary of all files')">📈 Global stats</span>
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
                    :placeholder="csvFiles.length > 1 ? 'e.g. Compare sales between both files...' : 'e.g. Which product has the highest sales?'"
                    rows="1"
                    @keydown.enter.prevent="handleEnter($event)"
                    @input="autoResize($event)"
                    :disabled="isTyping || csvFiles.length === 0"
                ></textarea>
                <button
                    class="send-btn"
                    @click="sendMessage()"
                    :disabled="isTyping || !userInput.trim() || csvFiles.length === 0"
                    title="Send"
                >➤</button>
            </div>
            <div class="input-hint"
                 x-text="csvFiles.length === 0 ? '⬅️ Upload a CSV file to get started' : 'Enter to send · Shift+Enter for new line'">
            </div>
        </div>
    </main>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/marked/9.1.6/marked.min.js"></script>
<script>
function aiChat() {
    return {
        userInput:       '',
        messages:        [],
        isTyping:        false,
        errorMsg:        null,
        csvFiles:        [],
        previewData:     null,
        previewFileName: '',
        isDragging:      false,

        init() {
            marked.setOptions({ breaks: true, gfm: true });
        },

        handleFileSelect(event) {
            const file = event.target.files[0];
            if (file) this.uploadFile(file);
            event.target.value = '';
        },

        handleDrop(event) {
            this.isDragging = false;
            const file = event.dataTransfer.files[0];
            if (file && file.name.endsWith('.csv')) this.uploadFile(file);
        },

        async uploadFile(file) {
            if (this.csvFiles.length >= 5) {
                this.errorMsg = 'Maximum 5 files reached. Remove one first.';
                return;
            }
            this.errorMsg = null;

            const formData = new FormData();
            formData.append('csv_file', file);
            formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);

            try {
                const res  = await fetch('/ai-chat/upload', { method: 'POST', body: formData });
                const data = await res.json();

                if (data.success) {
                    this.csvFiles = this.csvFiles.filter(f => f.name !== data.file.name);
                    this.csvFiles.push(data.file);
                    this.previewData     = data.preview;
                    this.previewFileName = data.file.name;
                } else {
                    this.errorMsg = data.message ?? 'Upload failed.';
                }
            } catch (e) {
                this.errorMsg = 'Network error during upload.';
            }
        },

        async removeFile(file) {
            try {
                await fetch('/ai-chat/remove-file', {
                    method:  'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ path: file.path }),
                });
                this.csvFiles = this.csvFiles.filter(f => f.path !== file.path);
                if (this.previewFileName === file.name) {
                    this.previewData     = null;
                    this.previewFileName = '';
                }
                if (this.csvFiles.length === 0) this.messages = [];
            } catch (e) {
                this.errorMsg = 'Error removing file.';
            }
        },

        setQuestion(q) { this.userInput = q; },

        handleEnter(event) {
            if (!event.shiftKey) this.sendMessage();
        },

        async sendMessage() {
            const message = this.userInput.trim();
            if (!message || this.isTyping || this.csvFiles.length === 0) return;

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
                    body: JSON.stringify({ message }),
                });
                const data = await res.json();

                if (data.success) {
                    this.messages.push({ role: 'ai', content: data.reply, time: this.now() });
                } else {
                    this.errorMsg = data.error ?? 'Something went wrong.';
                }
            } catch (e) {
                this.errorMsg = 'Network error. Please try again.';
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

        async clearAll() {
            await fetch('/ai-chat/clear-all', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            });
            this.messages        = [];
            this.csvFiles        = [];
            this.previewData     = null;
            this.previewFileName = '';
            this.errorMsg        = null;
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
            return new Date().toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
        },
    };
}
</script>
</x-app-layout>
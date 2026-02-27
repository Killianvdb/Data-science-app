<x-app-layout>


<style>
    /*
     * ESCAPE THE LAYOUT CARD
     * The app layout wraps $slot in:
     *   <main class="flex-1">
     *     <div class="max-w-7xl mx-auto px-6 py-6">        ← remove padding
     *       <div class="relative bg-white/80 ... p-8">     ← remove padding + card styles
     *
     * We crawl up and reset all three layers.
     */

    /* 1. Kill the outer <main> padding/constraints */
    body > .flex-1,
    main.flex-1 {
        padding: 0 !important;
        overflow: hidden;
    }

    /* 2. Kill the max-w-7xl wrapper */
    main.flex-1 > div {
        max-width: 100% !important;
        padding: 0 !important;
        margin: 0 !important;
        height: 100%;
    }

    /* 3. Kill the white card box */
    main.flex-1 > div > div {
        background: transparent !important;
        backdrop-filter: none !important;
        border-radius: 0 !important;
        box-shadow: none !important;
        border: none !important;
        padding: 0 !important;
        height: 100%;
    }
    /* Remove the card's top gradient bar pseudo-element */
    main.flex-1 > div > div::before {
        display: none !important;
    }


    /* Hide the page header and footer on the chat page — we want full screen */
    body > header,
    body > footer {
        display: none !important;
    }

    /* 4. Make the full body stack correctly */
    html, body {
        height: 100%;
        overflow: hidden;
    }
    body {
        display: flex;
        flex-direction: column;
    }
    main.flex-1 {
        flex: 1;
        min-height: 0;
        display: flex;
        flex-direction: column;
    }
    main.flex-1 > div,
    main.flex-1 > div > div {
        flex: 1;
        min-height: 0;
        display: flex;
        flex-direction: column;
    }

    @import url('https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&family=DM+Mono:wght@400;500&display=swap');

    :root {
        --bg:           #0f0f0f;
        --surface:      #181818;
        --surface2:     #222222;
        --surface3:     #2a2a2a;
        --border:       #2e2e2e;
        --border-light: #383838;
        --accent:       #c8a96e;
        --accent-dim:   rgba(200,169,110,0.12);
        --accent-glow:  rgba(200,169,110,0.06);
        --blue:         #6b9fff;
        --blue-dim:     rgba(107,159,255,0.12);
        --text:         #e8e6e1;
        --text-muted:   #888580;
        --text-dim:     #555250;
        --user-bg:      #1e1e1e;
        --ai-bg:        transparent;
        --danger:       #ff6b6b;
        --success:      #6bcb77;
        --radius-sm:    8px;
        --radius:       12px;
        --radius-lg:    16px;
        --font-main:    'DM Sans', sans-serif;
        --font-serif:   'Instrument Serif', serif;
        --font-mono:    'DM Mono', monospace;
        --sidebar-w:    280px;
        --transition:   0.18s cubic-bezier(0.4, 0, 0.2, 1);
        --nav-h:        64px;  /* adjust to match your app nav height */
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    /* ───────────────── Layout Shell ───────────────── */
    .chat-layout {
        display: grid;
        grid-template-columns: var(--sidebar-w) 1fr;
        flex: 1;
        min-height: 0;
        width: 100%;
        overflow: hidden;
        font-family: var(--font-main);
        color: var(--text);
        background: var(--bg);
    }

    /* ───────────────── Sidebar ───────────────── */
    .sidebar {
        background: var(--surface);
        border-right: 1px solid var(--border);
        display: flex;
        flex-direction: column;
        overflow-y: auto;
        overflow-x: hidden;
    }
    .sidebar::-webkit-scrollbar { width: 0px; }

    .sidebar-header {
        padding: 18px 16px 14px;
        border-bottom: 1px solid var(--border);
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .brand-mark {
        width: 28px; height: 28px;
        background: linear-gradient(135deg, var(--accent) 0%, #e8c87a 100%);
        border-radius: 7px;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
        box-shadow: 0 2px 10px rgba(200,169,110,0.3);
    }
    .brand-mark svg { width: 14px; height: 14px; }
    .brand-name {
        font-size: 0.875rem;
        font-weight: 600;
        letter-spacing: -0.01em;
        color: var(--text);
    }
    .brand-name span { color: var(--accent); }

    .sidebar-body {
        flex: 1;
        padding: 14px 12px;
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .section-label {
        font-size: 0.68rem;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: var(--text-dim);
        padding: 0 6px;
        margin-bottom: 4px;
        margin-top: 8px;
    }
    .section-label:first-child { margin-top: 0; }

    /* Upload zone */
    .upload-zone {
        border: 1.5px dashed var(--border-light);
        border-radius: var(--radius);
        padding: 16px 12px;
        text-align: center;
        cursor: pointer;
        transition: border-color var(--transition), background var(--transition);
        position: relative;
        background: var(--accent-glow);
    }
    .upload-zone:hover, .upload-zone.drag-over {
        border-color: var(--accent);
        background: var(--accent-dim);
    }
    .upload-zone input[type="file"] {
        position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%;
    }
    .upload-zone-icon {
        font-size: 1.25rem; margin-bottom: 6px;
        filter: saturate(0) brightness(1.5);
    }
    .upload-zone-title {
        font-size: 0.8rem; font-weight: 500; color: var(--text);
        margin-bottom: 2px;
    }
    .upload-zone-sub {
        font-size: 0.7rem; color: var(--text-muted);
    }
    .upload-zone-limit {
        margin-top: 8px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 0.67rem;
        color: var(--text-dim);
        background: var(--surface3);
        padding: 2px 8px;
        border-radius: 20px;
        border: 1px solid var(--border);
    }

    /* File items */
    .file-item {
        display: flex;
        align-items: center;
        gap: 9px;
        padding: 8px 10px;
        border-radius: var(--radius-sm);
        border: 1px solid var(--border);
        background: var(--surface2);
        transition: border-color var(--transition), background var(--transition);
        animation: itemIn 0.2s ease;
    }
    @keyframes itemIn {
        from { opacity: 0; transform: translateY(-4px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    .file-item:hover { border-color: var(--border-light); background: var(--surface3); }
    .file-icon {
        width: 28px; height: 28px; border-radius: 6px;
        background: rgba(107,159,255,0.12);
        border: 1px solid rgba(107,159,255,0.2);
        display: flex; align-items: center; justify-content: center;
        font-size: 0.75rem; flex-shrink: 0;
    }
    .file-info { flex: 1; min-width: 0; }
    .file-name {
        font-size: 0.77rem; font-weight: 500; color: var(--text);
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .file-meta { font-size: 0.67rem; color: var(--text-muted); margin-top: 1px; }
    .file-remove {
        width: 22px; height: 22px;
        border: none; border-radius: 5px;
        background: transparent; color: var(--text-dim);
        cursor: pointer; font-size: 0.8rem;
        display: flex; align-items: center; justify-content: center;
        transition: all var(--transition); flex-shrink: 0;
        line-height: 1;
    }
    .file-remove:hover { background: rgba(255,107,107,0.12); color: var(--danger); }

    /* Preview */
    .preview-wrap {
        border: 1px solid var(--border);
        border-radius: var(--radius);
        overflow: hidden;
    }
    .preview-header {
        padding: 7px 10px;
        background: var(--surface2);
        border-bottom: 1px solid var(--border);
        font-size: 0.69rem;
        font-weight: 500;
        color: var(--text-muted);
        display: flex; align-items: center; gap: 6px;
    }
    .preview-filename {
        color: var(--accent);
        font-family: var(--font-mono);
        font-size: 0.67rem;
    }
    .preview-scroll { overflow-x: auto; max-height: 160px; }
    .preview-scroll::-webkit-scrollbar { height: 4px; width: 4px; }
    .preview-scroll::-webkit-scrollbar-thumb { background: var(--border); border-radius: 2px; }
    .preview-table { width: 100%; border-collapse: collapse; font-family: var(--font-mono); font-size: 0.63rem; white-space: nowrap; }
    .preview-table th {
        background: var(--surface2); color: var(--text-muted);
        padding: 5px 8px; text-align: left;
        border-bottom: 1px solid var(--border);
        font-weight: 500; position: sticky; top: 0;
    }
    .preview-table td {
        padding: 4px 8px; border-bottom: 1px solid var(--border);
        color: var(--text-dim); max-width: 90px; overflow: hidden; text-overflow: ellipsis;
    }
    .preview-table tr:last-child td { border-bottom: none; }
    .preview-table tr:hover td { background: var(--surface2); }

    /* Sidebar footer */
    .sidebar-footer {
        padding: 12px;
        border-top: 1px solid var(--border);
        display: flex; flex-direction: column; gap: 4px;
    }
    .sidebar-btn {
        width: 100%; padding: 7px 10px;
        background: transparent;
        border: 1px solid transparent;
        border-radius: var(--radius-sm);
        color: var(--text-muted);
        font-family: var(--font-main); font-size: 0.77rem;
        cursor: pointer; text-align: left;
        display: flex; align-items: center; gap: 8px;
        transition: all var(--transition);
    }
    .sidebar-btn:hover:not(:disabled) {
        background: var(--surface2);
        border-color: var(--border);
        color: var(--text);
    }
    .sidebar-btn:hover:not(:disabled).danger { color: var(--danger); border-color: rgba(255,107,107,0.2); }
    .sidebar-btn:disabled { opacity: 0.3; cursor: not-allowed; }
    .sidebar-btn-icon { font-size: 0.85rem; flex-shrink: 0; }

    /* ───────────────── Main Chat ───────────────── */
    .chat-main {
        display: flex;
        flex-direction: column;
        background: var(--bg);
        overflow: hidden;
        min-height: 0;
        position: relative;
    }

    /* Top bar */
    .chat-topbar {
        height: 52px;
        padding: 0 20px;
        border-bottom: 1px solid var(--border);
        display: flex;
        align-items: center;
        gap: 12px;
        background: rgba(15,15,15,0.9);
        backdrop-filter: blur(12px);
        flex-shrink: 0;
        position: relative;
        z-index: 10;
    }
    .topbar-title {
        font-size: 0.875rem; font-weight: 500; color: var(--text);
        display: flex; align-items: center; gap: 8px;
    }
    .online-dot {
        width: 7px; height: 7px; border-radius: 50%;
        background: var(--success);
        box-shadow: 0 0 6px var(--success);
        animation: blink 2.5s ease infinite;
    }
    @keyframes blink {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.4; }
    }
    .topbar-sub { font-size: 0.72rem; color: var(--text-muted); }
    .topbar-right { margin-left: auto; display: flex; align-items: center; gap: 8px; }
    .file-chips { display: flex; gap: 5px; }
    .file-chip {
        font-size: 0.67rem; padding: 3px 9px;
        border-radius: 20px;
        background: var(--surface2);
        border: 1px solid var(--border);
        color: var(--text-muted);
        white-space: nowrap;
        font-family: var(--font-mono);
    }
    .file-chip.active {
        background: var(--blue-dim);
        border-color: rgba(107,159,255,0.25);
        color: var(--blue);
    }
    .no-file-badge {
        font-size: 0.7rem; padding: 3px 10px;
        border-radius: 20px;
        background: var(--surface2);
        border: 1px solid var(--border);
        color: var(--text-dim);
    }

    /* Messages */
    .messages-container {
        flex: 1;
        overflow-y: scroll;
        scroll-behavior: smooth;
        min-height: 0;
    }
    .messages-container::-webkit-scrollbar { width: 6px; }
    .messages-container::-webkit-scrollbar-track { background: transparent; }
    .messages-container::-webkit-scrollbar-thumb { background: var(--surface3); border-radius: 3px; }
    .messages-container::-webkit-scrollbar-thumb:hover { background: var(--border-light); }

    .messages-inner {
        max-width: 780px;
        margin: 0 auto;
        padding: 28px 28px 12px;
        display: flex;
        flex-direction: column;
        gap: 0;
    }

    /* Empty state */
    .empty-state {
        max-width: 580px;
        margin: 0 auto;
        padding: 60px 28px 40px;
        text-align: center;
    }
    .empty-hero {
        width: 52px; height: 52px;
        margin: 0 auto 20px;
        background: linear-gradient(135deg, var(--accent) 0%, #e8c87a 100%);
        border-radius: 14px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.4rem;
        box-shadow: 0 4px 24px rgba(200,169,110,0.25);
    }
    .empty-title {
        font-family: var(--font-serif);
        font-size: 1.6rem;
        color: var(--text);
        margin-bottom: 10px;
        letter-spacing: -0.01em;
    }
    .empty-sub {
        font-size: 0.85rem;
        color: var(--text-muted);
        line-height: 1.65;
        margin-bottom: 28px;
    }
    .suggestion-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
        text-align: left;
    }
    .suggestion-card {
        padding: 12px 14px;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        cursor: pointer;
        transition: all var(--transition);
    }
    .suggestion-card:hover {
        border-color: var(--accent);
        background: var(--accent-dim);
    }
    .suggestion-card-icon { font-size: 0.9rem; margin-bottom: 5px; }
    .suggestion-card-label { font-size: 0.78rem; font-weight: 500; color: var(--text); line-height: 1.4; }
    .suggestion-card-sub { font-size: 0.7rem; color: var(--text-muted); margin-top: 2px; }

    /* Message rows */
    .message-row {
        padding: 16px 0;
        animation: msgIn 0.22s ease;
        border-bottom: 1px solid transparent;
    }
    @keyframes msgIn {
        from { opacity: 0; transform: translateY(6px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    .message-row.user { }
    .message-row.ai   { }

    .message-header {
        display: flex; align-items: center; gap: 9px; margin-bottom: 8px;
    }
    .msg-avatar {
        width: 26px; height: 26px; border-radius: 7px;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.75rem; flex-shrink: 0; font-family: var(--font-mono);
        font-weight: 500;
    }
    .msg-avatar.user {
        background: var(--surface3);
        border: 1px solid var(--border-light);
        color: var(--text-muted);
        font-size: 0.65rem;
    }
    .msg-avatar.ai {
        background: linear-gradient(135deg, var(--accent) 0%, #e8c87a 100%);
        box-shadow: 0 1px 6px rgba(200,169,110,0.3);
        color: #0f0f0f;
        font-size: 0.65rem;
        font-weight: 700;
    }
    .msg-name { font-size: 0.78rem; font-weight: 600; color: var(--text); }
    .msg-time { font-size: 0.67rem; color: var(--text-dim); margin-left: 4px; }

    .message-body {
        padding-left: 35px;
        font-size: 0.9rem;
        line-height: 1.72;
        color: var(--text);
    }
    .message-body.user-body {
        color: var(--text);
        white-space: pre-wrap;
    }
    /* AI markdown rendering */
    .message-body h1, .message-body h2, .message-body h3 {
        font-family: var(--font-serif);
        color: var(--text);
        margin: 1em 0 0.4em;
        font-weight: 400;
    }
    .message-body h1 { font-size: 1.35rem; }
    .message-body h2 { font-size: 1.15rem; }
    .message-body h3 { font-size: 1rem; }
    .message-body p { margin-bottom: 0.7em; }
    .message-body p:last-child { margin-bottom: 0; }
    .message-body ul, .message-body ol { padding-left: 1.4em; margin-bottom: 0.7em; }
    .message-body li { margin-bottom: 0.25em; }
    .message-body strong { color: var(--text); font-weight: 600; }
    .message-body em { color: var(--text-muted); }
    .message-body a { color: var(--blue); text-decoration: underline; text-underline-offset: 2px; }
    .message-body code {
        font-family: var(--font-mono);
        font-size: 0.82rem;
        background: var(--surface2);
        border: 1px solid var(--border);
        padding: 1px 5px;
        border-radius: 4px;
        color: var(--accent);
    }
    .message-body pre {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 14px 16px;
        overflow-x: auto;
        margin: 10px 0;
        font-family: var(--font-mono);
        font-size: 0.8rem;
        line-height: 1.6;
        color: var(--text);
    }
    .message-body pre code {
        background: none; border: none; padding: 0; color: inherit; font-size: inherit;
    }
    .message-body table {
        border-collapse: collapse; width: 100%; margin: 10px 0; font-size: 0.83rem;
        border-radius: var(--radius); overflow: hidden;
        border: 1px solid var(--border);
    }
    .message-body th {
        background: var(--surface2); color: var(--text-muted); font-weight: 500;
        padding: 8px 12px; text-align: left; border-bottom: 1px solid var(--border);
    }
    .message-body td { padding: 7px 12px; border-bottom: 1px solid var(--border); color: var(--text-muted); }
    .message-body tr:last-child td { border-bottom: none; }
    .message-body tr:hover td { background: var(--surface2); }
    .message-body blockquote {
        border-left: 3px solid var(--accent);
        padding-left: 14px; margin: 10px 0;
        color: var(--text-muted); font-style: italic;
    }

    /* Typing indicator */
    .typing-row { padding: 12px 0; }
    .typing-inner { padding-left: 35px; display: flex; align-items: center; gap: 5px; }
    .typing-dot {
        width: 5px; height: 5px; border-radius: 50%;
        background: var(--text-dim);
        animation: typingBounce 1.4s ease infinite;
    }
    .typing-dot:nth-child(2) { animation-delay: 0.18s; }
    .typing-dot:nth-child(3) { animation-delay: 0.36s; }
    @keyframes typingBounce {
        0%, 80%, 100% { transform: translateY(0); opacity: 0.3; }
        40% { transform: translateY(-4px); opacity: 1; }
    }

    /* Error */
    .error-row {
        padding: 12px 0;
        padding-left: 35px;
    }
    .error-inline {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        background: rgba(255,107,107,0.08);
        border: 1px solid rgba(255,107,107,0.2);
        color: var(--danger);
        border-radius: var(--radius-sm);
        padding: 7px 12px;
        font-size: 0.8rem;
    }

    /* ───────────────── Input Bar ───────────────── */
    .input-area {
        padding: 16px 20px 18px;
        background: var(--bg);
        flex-shrink: 0;
    }
    .input-area-inner {
        max-width: 780px;
        margin: 0 auto;
    }
    .input-box {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        padding: 14px 14px 10px;
        transition: border-color var(--transition), box-shadow var(--transition);
        position: relative;
    }
    .input-box:focus-within {
        border-color: var(--border-light);
        box-shadow: 0 0 0 3px rgba(200,169,110,0.06), 0 2px 12px rgba(0,0,0,0.3);
    }
    .input-box textarea {
        width: 100%;
        background: transparent;
        border: none; outline: none;
        color: var(--text);
        font-family: var(--font-main); font-size: 0.9rem;
        resize: none; line-height: 1.55;
        max-height: 140px; overflow-y: auto;
        display: block;
        min-height: 22px;
    }
    .input-box textarea::placeholder { color: var(--text-dim); }
    .input-box textarea::-webkit-scrollbar { width: 4px; }
    .input-box textarea::-webkit-scrollbar-thumb { background: var(--border); }

    .input-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-top: 10px;
    }
    .input-hint {
        font-size: 0.67rem;
        color: var(--text-dim);
        display: flex; align-items: center; gap: 5px;
    }
    .send-row { display: flex; align-items: center; gap: 8px; }
    .char-count { font-size: 0.67rem; color: var(--text-dim); font-family: var(--font-mono); }
    .send-btn {
        display: flex; align-items: center; gap: 6px;
        background: var(--accent);
        color: #0f0f0f;
        border: none;
        border-radius: var(--radius-sm);
        padding: 7px 14px;
        font-family: var(--font-main); font-size: 0.8rem; font-weight: 600;
        cursor: pointer;
        transition: all var(--transition);
        box-shadow: 0 1px 8px rgba(200,169,110,0.25);
    }
    .send-btn:hover:not(:disabled) {
        background: #d9b87a;
        box-shadow: 0 2px 16px rgba(200,169,110,0.4);
        transform: translateY(-1px);
    }
    .send-btn:active:not(:disabled) { transform: translateY(0); }
    .send-btn:disabled { opacity: 0.3; cursor: not-allowed; box-shadow: none; transform: none; }
    .send-btn svg { width: 13px; height: 13px; }

    /* Divider between days/sections */
    .conversation-divider {
        display: flex; align-items: center; gap: 10px;
        padding: 6px 0; margin: 4px 0;
    }
    .divider-line { flex: 1; height: 1px; background: var(--border); }
    .divider-label { font-size: 0.66rem; color: var(--text-dim); white-space: nowrap; font-family: var(--font-mono); }

    /* Noise grain overlay */
    .chat-main::before {
        content: '';
        position: absolute;
        inset: 0;
        pointer-events: none;
        z-index: 0;
        background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.03'/%3E%3C/svg%3E");
        opacity: 0.4;
    }
</style>

<script>
(function() {
    var nav = document.getElementById('app-nav');
    if (nav) {
        document.documentElement.style.setProperty('--nav-h', nav.offsetHeight + 'px');
    }
})();
</script>

<div class="chat-layout" x-data="aiChat()" x-init="init()">

    {{-- ═══ SIDEBAR ═══ --}}
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="brand-mark">
                <svg viewBox="0 0 24 24" fill="none" stroke="#0f0f0f" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18"/>
                </svg>
            </div>
            <div class="brand-name">Data<span>AI</span></div>
        </div>

        <div class="sidebar-body">
            <div class="section-label">Upload Data</div>

            <div
                class="upload-zone"
                :class="{ 'drag-over': isDragging }"
                @dragover.prevent="isDragging = true"
                @dragleave="isDragging = false"
                @drop.prevent="handleDrop($event)"
            >
                <input type="file" accept=".csv" @change="handleFileSelect($event)" />
                <div class="upload-zone-icon">📂</div>
                <div class="upload-zone-title">Drop a CSV file</div>
                <div class="upload-zone-sub">or click to browse</div>
                <div class="upload-zone-limit">
                    <span x-text="csvFiles.length"></span>/5 files
                </div>
            </div>

            <template x-if="csvFiles.length > 0">
                <div>
                    <div class="section-label" style="margin-top:16px;">
                        Loaded files
                    </div>
                    <div style="display:flex;flex-direction:column;gap:5px;">
                        <template x-for="(file, idx) in csvFiles" :key="file.path">
                            <div class="file-item">
                                <div class="file-icon">📊</div>
                                <div class="file-info">
                                    <div class="file-name" x-text="file.name"></div>
                                    <div class="file-meta" x-text="`${file.cols} cols · ${file.uploaded}`"></div>
                                </div>
                                <button class="file-remove" @click="removeFile(file)" title="Remove">✕</button>
                            </div>
                        </template>
                    </div>
                </div>
            </template>

            <template x-if="previewData">
                <div style="margin-top:12px;">
                    <div class="section-label">Preview</div>
                    <div class="preview-wrap">
                        <div class="preview-header">
                            <span>📋</span>
                            <span class="preview-filename" x-text="previewFileName"></span>
                        </div>
                        <div class="preview-scroll">
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
                </div>
            </template>
        </div>

        <div class="sidebar-footer">
            <button class="sidebar-btn" @click="clearHistory()" :disabled="messages.length === 0">
                <span class="sidebar-btn-icon">🗑</span> Clear conversation
            </button>
            <button class="sidebar-btn danger" @click="clearAll()" :disabled="csvFiles.length === 0">
                <span class="sidebar-btn-icon">↺</span> Reset everything
            </button>
        </div>
    </aside>

    {{-- ═══ MAIN CHAT ═══ --}}
    <main class="chat-main">

        {{-- Top bar --}}
        <div class="chat-topbar">
            <div>
                <div class="topbar-title">
                    <span class="online-dot"></span>
                    AI Data Analyst
                </div>
                <div class="topbar-sub" x-text="csvFiles.length > 1 ? `Analyzing ${csvFiles.length} files` : 'Ask anything about your data'"></div>
            </div>
            <div class="topbar-right">
                <template x-if="csvFiles.length > 0">
                    <div class="file-chips">
                        <template x-for="f in csvFiles.slice(0,3)" :key="f.path">
                            <span class="file-chip active" x-text="f.name.length > 14 ? f.name.slice(0,14)+'…' : f.name"></span>
                        </template>
                        <template x-if="csvFiles.length > 3">
                            <span class="file-chip" x-text="`+${csvFiles.length - 3}`"></span>
                        </template>
                    </div>
                </template>
                <template x-if="csvFiles.length === 0">
                    <span class="no-file-badge">No file loaded</span>
                </template>
            </div>
        </div>

        {{-- Messages --}}
        <div class="messages-container" id="messages-container">
            <div class="messages-inner">

                <template x-if="messages.length === 0">
                    <div class="empty-state">
                        <div class="empty-hero">🧠</div>
                        <h2 class="empty-title">What would you like to know?</h2>
                        <p class="empty-sub">
                            Upload up to <strong style="color:var(--accent)">5 CSV files</strong> in the sidebar, then ask anything — summaries, comparisons, statistics, anomalies, and more.
                        </p>
                        <div class="suggestion-grid">
                            <div class="suggestion-card" @click="setQuestion('How many rows does each file have?')">
                                <div class="suggestion-card-icon">📊</div>
                                <div class="suggestion-card-label">File summary</div>
                                <div class="suggestion-card-sub">Row counts & column overview</div>
                            </div>
                            <div class="suggestion-card" @click="setQuestion('Compare the data across all files')">
                                <div class="suggestion-card-icon">🔄</div>
                                <div class="suggestion-card-label">Compare files</div>
                                <div class="suggestion-card-sub">Find patterns across datasets</div>
                            </div>
                            <div class="suggestion-card" @click="setQuestion('What are the top 5 most important items?')">
                                <div class="suggestion-card-icon">🏆</div>
                                <div class="suggestion-card-label">Top 5 items</div>
                                <div class="suggestion-card-sub">Ranked by importance</div>
                            </div>
                            <div class="suggestion-card" @click="setQuestion('Are there any missing or anomalous values?')">
                                <div class="suggestion-card-icon">🔍</div>
                                <div class="suggestion-card-label">Find anomalies</div>
                                <div class="suggestion-card-sub">Missing & outlier detection</div>
                            </div>
                        </div>
                    </div>
                </template>

                <template x-if="messages.length > 0">
                    <div class="conversation-divider">
                        <div class="divider-line"></div>
                        <div class="divider-label">Today</div>
                        <div class="divider-line"></div>
                    </div>
                </template>

                <template x-for="(msg, idx) in messages" :key="idx">
                    <div class="message-row" :class="msg.role">
                        <div class="message-header">
                            <div class="msg-avatar" :class="msg.role">
                                <span x-text="msg.role === 'user' ? 'You' : 'AI'"></span>
                            </div>
                            <span class="msg-name" x-text="msg.role === 'user' ? 'You' : 'AI Analyst'"></span>
                            <span class="msg-time" x-text="msg.time"></span>
                        </div>
                        <div
                            class="message-body"
                            :class="msg.role === 'user' ? 'user-body' : ''"
                            x-html="msg.role === 'ai' ? renderMarkdown(msg.content) : escapeHtml(msg.content)"
                        ></div>
                    </div>
                </template>

                <template x-if="isTyping">
                    <div class="message-row ai typing-row">
                        <div class="message-header">
                            <div class="msg-avatar ai">AI</div>
                            <span class="msg-name">AI Analyst</span>
                            <span class="msg-time">typing…</span>
                        </div>
                        <div class="typing-inner" style="padding-left:35px;">
                            <div class="typing-dot"></div>
                            <div class="typing-dot"></div>
                            <div class="typing-dot"></div>
                        </div>
                    </div>
                </template>

                <template x-if="errorMsg">
                    <div class="error-row">
                        <div class="error-inline">
                            <span>⚠</span>
                            <span x-text="errorMsg"></span>
                        </div>
                    </div>
                </template>

            </div>
        </div>

        {{-- Input bar --}}
        <div class="input-area">
            <div class="input-area-inner">
                <div class="input-box">
                    <textarea
                        x-model="userInput"
                        :placeholder="csvFiles.length === 0 ? 'Upload a CSV file first…' : csvFiles.length > 1 ? 'e.g. Compare the trends across all files…' : 'e.g. What is the average value in column B?'"
                        rows="1"
                        @keydown.enter.prevent="handleEnter($event)"
                        @input="autoResize($event)"
                        :disabled="isTyping || csvFiles.length === 0"
                        id="chat-input"
                    ></textarea>
                    <div class="input-footer">
                        <div class="input-hint">
                            <template x-if="csvFiles.length === 0">
                                <span>⬅ Upload a CSV to start</span>
                            </template>
                            <template x-if="csvFiles.length > 0">
                                <span>↵ Send &nbsp;·&nbsp; ⇧↵ New line</span>
                            </template>
                        </div>
                        <div class="send-row">
                            <span class="char-count" x-show="userInput.length > 0" x-text="`${userInput.length}`"></span>
                            <button
                                class="send-btn"
                                @click="sendMessage()"
                                :disabled="isTyping || !userInput.trim() || csvFiles.length === 0"
                            >
                                Send
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
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

        setQuestion(q) {
            this.userInput = q;
            this.$nextTick(() => {
                const el = document.getElementById('chat-input');
                if (el) { el.style.height = 'auto'; el.style.height = Math.min(el.scrollHeight, 140) + 'px'; }
            });
        },

        handleEnter(event) {
            if (!event.shiftKey) this.sendMessage();
        },

        async sendMessage() {
            const message = this.userInput.trim();
            if (!message || this.isTyping || this.csvFiles.length === 0) return;

            this.userInput = '';
            this.errorMsg  = null;

            const el = document.getElementById('chat-input');
            if (el) el.style.height = 'auto';

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
            el.style.height = Math.min(el.scrollHeight, 140) + 'px';
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
<?php
/**
 * FACTA — AI Chat Placeholder
 * Supports Ollama, OpenRouter, OpenAI via configurable API
 */
require_once __DIR__ . '/../includes/functions.php';

if (!is_logged_in()) {
    echo '<div style="max-width:480px;margin:60px auto;text-align:center">';
    echo '<h2>🔐 Login Diperlukan</h2>';
    echo '<p style="color:var(--text-muted)">Silakan login untuk menggunakan AI Chat dan menyimpan sesi percakapan.</p>';
    echo '<br>';
    echo '<a href="api/auth.php?action=login" class="kurator-btn">🔑 Login dengan Google</a>';
    echo '</div>';
    return;
}

// Get chat sessions
$sessions = Database::query("SELECT * FROM chat_sessions ORDER BY created_at DESC LIMIT 10");
?>

<div class="fade-in">
  <div class="chat-container">
    <!-- Chat Header -->
    <div class="chat-header">
      <div style="display:flex;align-items:center;gap:10px">
        <span style="font-size:24px">🤖</span>
        <div>
          <div style="font-weight:700"><?=t('ai_chat')?></div>
          <div style="font-size:12px;color:var(--text-muted)">Powered by AI — Ask anything about Quran</div>
        </div>
      </div>

      <div style="display:flex;align-items:center;gap:8px">
        <span style="font-size:11px;color:var(--text-muted)">Provider:</span>
        <select class="model-select" id="aiProvider">
          <option value="ollama">🦙 Ollama (Local)</option>
          <option value="openrouter">🌐 OpenRouter</option>
          <option value="openai">🧠 OpenAI</option>
        </select>
      </div>
    </div>

    <!-- Messages -->
    <div class="chat-messages" id="chatMessages">
      <!-- Welcome message -->
      <div class="chat-msg assistant">
        <div class="meta">🤖 Assistant</div>
        Assalamualaikum! Saya adalah asisten AI Qurani Anda. Anda bisa bertanya tentang:
        <ul style="margin:8px 0;padding-left:20px">
          <li>Tafsir ayat tertentu</li>
          <li>Penjelasan topik Qurani</li>
          <li>Perbandingan ayat</li>
          <li>Hukum-hukum Islam berdasarkan Quran</li>
        </ul>
        <small style="color:var(--text-muted)">
          💡 <strong>Catatan:</strong> Fitur ini memerlukan backend AI yang terkonfigurasi.
          Pilih provider di atas dan pastikan API key / server tersedia.
        </small>
      </div>
    </div>

    <!-- Input -->
    <div class="chat-input-area">
      <input type="text" class="chat-input" id="chatInput" placeholder="Tanyakan sesuatu tentang Quran..." maxlength="2000">
      <button class="chat-send" id="chatSend" onclick="sendMessage()">➤</button>
    </div>
  </div>
</div>

<script>
const chatMessages = document.getElementById('chatMessages');
const chatInput = document.getElementById('chatInput');
const chatSend = document.getElementById('chatSend');
const aiProvider = document.getElementById('aiProvider');

let isLoading = false;

chatInput.addEventListener('keydown', e => { if (e.key === 'Enter') sendMessage(); });

async function sendMessage() {
  const text = chatInput.value.trim();
  if (!text || isLoading) return;

  // Add user message
  addMessage(text, 'user');
  chatInput.value = '';

  // Show loading
  isLoading = true;
  chatSend.style.opacity = '0.5';
  const loadingId = addLoading();

  try {
    const provider = aiProvider.value;
    const res = await fetch('api/chat.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({message: text, provider: provider})
    });
    const data = await res.json();

    removeLoading(loadingId);

    if (data.error) {
      addMessage(`⚠️ ${data.error}\n\nPastikan:\n• Ollama berjalan di localhost:11434 (untuk mode lokal)\n• Atau API key OpenRouter/OpenAI telah dikonfigurasi.`, 'assistant');
    } else {
      addMessage(data.reply || 'Tidak ada jawaban.', 'assistant');
    }
  } catch (e) {
    removeLoading(loadingId);
    addMessage('❌ Gagal terhubung ke AI. Periksa koneksi dan konfigurasi backend.', 'assistant');
  }

  isLoading = false;
  chatSend.style.opacity = '1';
}

function addMessage(text, role) {
  const div = document.createElement('div');
  div.className = `chat-msg ${role}`;
  const meta = role === 'user' ? '👤 Anda' : '🤖 Assistant';
  div.innerHTML = `<div class="meta">${meta}</div>${escapeHtml(text).replace(/\n/g, '<br>')}`;
  chatMessages.appendChild(div);
  chatMessages.scrollTop = chatMessages.scrollHeight;
}

function addLoading() {
  const id = 'loading-' + Date.now();
  const div = document.createElement('div');
  div.id = id;
  div.className = 'chat-msg assistant';
  div.innerHTML = `<div class="meta">🤖 Assistant</div><div class="loading-spinner" style="width:20px;height:20px;margin:0"></div>`;
  chatMessages.appendChild(div);
  chatMessages.scrollTop = chatMessages.scrollHeight;
  return id;
}

function removeLoading(id) {
  const el = document.getElementById(id);
  if (el) el.remove();
}

function escapeHtml(text) {
  return text.replace(/[&<>"']/g, m => ({&:'&amp;',<:'<',>:'>','"':'&quot;',"'":'\u0026#39;'
  }[m]));
}
</script>

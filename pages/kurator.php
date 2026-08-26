<?php
/**
 * FACTA — Kurator (Admin-only)
 *
 * Tabs:
 *   1. Root Words — AI curation untuk root_words yang belum ada arti.
 *   2. Sarf Patterns — monitoring progress klasifikasi bentuk kata.
 *
 * Hanya tampil jika is_admin() = true (diberi cookie admin_secret).
 */
require_once __DIR__ . '/../includes/functions.php';

$isReadOnly = false;

if (!is_admin() && !is_curator()) {
    echo '<div style="max-width:480px;margin:60px auto;text-align:center">';
    echo '<h2>🔐 Akses Terbatas</h2>';
    echo '<p style="color:var(--text-muted)">Kurator hanya dapat diakses oleh admin dan curator.</p>';
    echo '<br>';
    echo '<a href="index.php" class="kurator-btn">⬅ Kembali ke Beranda</a>';
    if (!is_logged_in()) {
        echo '<a href="api/auth.php?action=login" class="kurator-btn" style="margin-left:8px">🔑 Login</a>';
    }
    echo '</div>';
    exit;
}

if (!is_admin() && is_curator()) {
    $isReadOnly = true;
}

$tab = $_GET['tab'] ?? 'roots';
$uiLang = current_lang();

// Stats root words
$rootStats = Database::queryOne(
    "SELECT COUNT(*) total,
            SUM(CASE WHEN source='imported' AND (meaning_id IS NULL OR meaning_id='') THEN 1 ELSE 0 END) pending,
            SUM(CASE WHEN source='imported' AND (meaning_id IS NOT NULL AND meaning_id!='') THEN 1 ELSE 0 END) curated
     FROM root_words"
) ?? ['total'=>0,'pending'=>0,'curated'=>0];

// Stats sarf patterns
$sarfTotalPatterns = (int)(Database::queryOne("SELECT COUNT(*) c FROM sarf_patterns")['c'] ?? 0);
$sarfClassified    = (int)(Database::queryOne("SELECT COUNT(DISTINCT ayah_root_word_id) c FROM word_form_patterns")['c'] ?? 0);
$sarfPending       = (int)(Database::queryOne("SELECT COUNT(*) c FROM ayah_root_words WHERE word_form IS NOT NULL AND word_form!='' AND id NOT IN (SELECT ayah_root_word_id FROM word_form_patterns)")['c'] ?? 0);

// Load sarf patterns list
$sarfPatterns = Database::query("SELECT id, pattern_ar, pattern_type, form_number, bab, description_id, example_root, example_word, is_active FROM sarf_patterns ORDER BY sort_order, id");

$pageTitle = t('kurator') . ' — ' . APP_NAME;
?>
<style>
/* Kurator-specific scoped styles */
.kurator-tabs{display:flex;gap:4px;margin-bottom:4px;border-bottom:1px solid var(--border-color);padding-bottom:1px}
.kurator-tab{padding:3px 8px;border-radius:var(--radius-sm);font-size:12px;font-weight:600;background:transparent;border:1px solid transparent;color:var(--text-secondary);cursor:pointer;transition:background var(--transition),color var(--transition)}
.kurator-tab:hover{color:var(--text-primary)}
.kurator-tab.active{background:var(--bg-glass);border-color:var(--border-color);color:var(--text-primary)}

.kurator-bar{display:flex;gap:5px;align-items:center;flex-wrap:wrap;background:var(--bg-card);border:1px solid var(--border-color);border-radius:var(--radius);padding:4px 6px;margin-bottom:4px}
.kurator-stat{font-size:13px;color:var(--text-muted)}
.kurator-stat b{color:var(--text-primary)}

.kurator-table{width:100%;border-collapse:collapse;background:var(--bg-card);border:1px solid var(--border-color);border-radius:var(--radius);overflow:hidden}
.kurator-table th,.kurator-table td{padding:3px 4px;border-bottom:1px solid var(--border-color);text-align:left;vertical-align:top;font-size:12px}
.kurator-table th{background:var(--bg-secondary);color:var(--text-muted);font-weight:600;font-size:10px;text-transform:uppercase}
.kurator-table tbody tr:last-child td{border-bottom:none}
.kurator-table tbody tr:hover{background:var(--bg-glass-hover)}
.kurator-table .ar{font-family:var(--font-arabic);font-size:16px;direction:rtl}
.kurator-table .ctx{font-size:11px;color:var(--text-muted);max-width:260px}
.kurator-table .ctx b{color:var(--text-primary)}
.kurator-table textarea{width:100%;min-height:44px;resize:vertical;background:var(--bg-secondary);color:var(--text-primary);border:1px solid var(--border-color);border-radius:6px;padding:6px 8px;font:inherit}
.kurator-table textarea:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(99,102,241,0.2);outline:none}

.row-status{font-size:11px}
.row-status.ok{color:var(--accent-green)}
.row-status.err{color:var(--accent-red)}
.row-status.pending{color:var(--text-muted)}

#kuratorLog{margin-top:4px;background:#000;border-radius:8px;padding:4px;font:11px/1.4 monospace;max-height:200px;overflow:auto;color:#9ca3af}

.kurator-pill{display:inline-block;padding:2px 8px;border-radius:99px;font-size:11px;background:var(--bg-secondary);border:1px solid var(--border-color);color:var(--text-secondary)}
.kurator-btn{background:var(--primary);border:1px solid var(--primary);color:#fff;border-radius:6px;padding:6px 10px;font-weight:600;cursor:pointer;font-size:12px;transition:opacity var(--transition)}
.kurator-btn:hover{opacity:.9}
.kurator-btn:disabled{opacity:.5;cursor:not-allowed}
.kurator-btn.secondary{background:var(--bg-secondary);border-color:var(--border-color);color:var(--text-primary)}
.kurator-btn.secondary:hover{background:var(--bg-tertiary)}

.sarf-pattern-type{font-size:11px;color:var(--primary-light);font-weight:600}
.sarf-bab{font-size:11px;color:var(--text-muted)}

.table-wrap{overflow-x:auto;margin-bottom:4px}

/* Progress card */
.progress-card{background:var(--bg-card);border:1px solid var(--border-color);border-radius:var(--radius);padding:4px 6px;margin-bottom:4px}
.progress-label{display:flex;justify-content:space-between;font-size:12px;color:var(--text-muted);margin-bottom:2px}
.progress-pct{font-weight:700;color:var(--primary)}
.progress-track{height:8px;background:var(--bg-secondary);border-radius:99px;overflow:hidden;margin-bottom:2px}
.progress-fill{height:100%;background:linear-gradient(90deg,var(--primary),var(--primary-light));border-radius:99px;transition:width .4s ease}
.progress-meta{display:flex;gap:8px;font-size:11px;color:var(--text-muted)}
.progress-meta b{color:var(--text-primary)}

/* Pattern breakdown bars */
#sarfPatternBars{display:flex;flex-direction:column;gap:1px;margin-bottom:3px}
.pattern-bar-row{display:flex;align-items:center;gap:10px;padding:4px 6px;border-radius:8px;background:var(--bg-card);border:1px solid var(--border-color)}
.pattern-bar-row:hover{border-color:var(--border-hover)}
.pattern-bar-info{display:flex;align-items:center;gap:10px;min-width:200px}
.pattern-bar-name{font-family:var(--font-arabic);font-size:16px}
.pattern-bar-type{font-size:10px;padding:2px 8px;border-radius:99px;background:var(--bg-secondary);color:var(--text-secondary)}
.pattern-bar-track{flex:1;height:10px;background:var(--bg-secondary);border-radius:99px;position:relative;overflow:hidden}
.pattern-bar-fill{position:absolute;inset:0;background:var(--bg-tertiary);border-radius:99px}
.pattern-bar-ver{position:absolute;inset:0;background:linear-gradient(90deg,var(--accent-green),#34d399);border-radius:99px;opacity:.85}
.pattern-bar-meta{font-size:11px;color:var(--text-muted);min-width:120px;text-align:right}

/* Confidence badges */
.conf-badge{font-size:11px;padding:2px 8px;border-radius:99px;font-weight:600}
.conf-high{background:rgba(52,211,153,.12);color:#34d399}
.conf-mid {background:rgba(250,204,21,.12);color:#fbbf24}
.conf-low {background:rgba(248,113,113,.12);color:#f87171}

/* Verify toggle */
.verify-toggle{font-size:11px;padding:4px 10px;border-radius:6px;border:1px solid var(--border-color);background:var(--bg-secondary);color:var(--text-secondary);cursor:pointer;transition:all .2s}
.verify-toggle.verified{background:rgba(52,211,153,.12);border-color:#34d399;color:#34d399;font-weight:600}
.verify-toggle:hover{opacity:.9}
</style>

<h2 style="margin-bottom:2px">🔐 <?=t('kurator')?></h2>

<div class="kurator-tabs">
  <a href="index.php?page=kurator&tab=roots" class="kurator-tab <?= $tab==='roots'?'active':'' ?>">
    🕋 <?=t('root_words')?>
  </a>
  <a href="index.php?page=kurator&tab=sarf" class="kurator-tab <?= $tab==='sarf'?'active':'' ?>">
    🧬 <?=t('sarf_patterns')?>
  </a>
</div>

<?php if ($tab === 'roots'): ?>

<div class="kurator-bar">
  <div class="kurator-stat">Total: <b><?=(int)$rootStats['total']?></b></div>
  <div class="kurator-stat">Pending: <b><?=(int)$rootStats['pending']?></b></div>
  <div class="kurator-stat">Curated: <b><?=(int)$rootStats['curated']?></b></div>
  <label>Provider
    <select id="kuratorProvider" class="kurator-select" style="background:var(--bg-secondary);color:var(--text-primary);border:1px solid var(--border-color);border-radius:6px;padding:6px 8px;font:inherit"></select>
  </label>
  <label>Batch
    <select id="kuratorBatchSize" style="background:var(--bg-secondary);color:var(--text-primary);border:1px solid var(--border-color);border-radius:6px;padding:6px 8px;font:inherit">
      <option value="5">5</option>
      <option value="10" selected>10</option>
      <option value="20">20</option>
      <option value="50">50</option>
    </select>
  </label>
  <button class="kurator-btn" id="btnKuratorLoad">📥 <?=t('generate')?></button>
  <?php if (!$isReadOnly): ?>
    <button class="kurator-btn" id="btnKuratorGenerateAll" disabled>✨ <?=t('generate')?> AI</button>
    <button class="kurator-btn secondary" id="btnKuratorApply" disabled>💾 <?=t('apply')?></button>
  <?php else: ?>
    <span style="font-size:13px;color:var(--text-muted)">👁️ Read-only (curator)</span>
  <?php endif; ?>
  <span class="kurator-stat" id="kuratorProgressLabel"></span>
</div>

<table class="kurator-table" id="tblKuratorRoots">
  <thead>
    <tr>
      <th style="width:24px"><input type="checkbox" id="chkKuratorAll" checked></th>
      <th><?=t('root_word')?></th>
      <th>Freq</th>
      <th>Konteks</th>
      <th>Arab</th>
      <th>English</th>
      <th>Indonesia</th>
      <th style="width:70px">AI</th>
    </tr>
  </thead>
  <tbody id="tbodyKuratorRoots"></tbody>
</table>

<div id="kuratorLog"></div>

<?php else: // tab=sarf ?>

<!-- Sarf Stats Bar -->
<div class="kurator-bar">
  <div class="kurator-stat"><?=t('sarf_patterns')?>: <b><?=$sarfTotalPatterns?></b></div>
  <div class="kurator-stat"><?=t('verified')?>: <b id="sarfStatVerified"><?=(int)$sarfClassified?></b></div>
  <div class="kurator-stat"><?=t('pending')?>: <b id="sarfStatPending"><?=(int)$sarfPending?></b></div>
  <button class="kurator-btn secondary" onclick="sarfLoadStats()">🔄 Refresh Stats</button>
</div>

<!-- Overall Progress -->
<div class="progress-card" id="sarfOverallProgress">
  <div class="progress-label">Overall Progress <span class="progress-pct" id="sarfPct">0%</span></div>
  <div class="progress-track"><div class="progress-fill" id="sarfFill" style="width:0%"></div></div>
  <div class="progress-meta">
    <span>Verified: <b id="sarfVerified">0</b></span>
    <span>Unverified: <b id="sarfUnverified">0</b></span>
    <span>Pending: <b id="sarfPendingMeta">0</b></span>
  </div>
</div>

<!-- Per-pattern Mini Bars -->
<h4 style="margin:6px 0 3px">Per-Pola Breakdown</h4>
<div id="sarfPatternBars">
  <p style="color:var(--text-muted);font-size:13px">Klik Refresh Stats untuk memuat breakdown per pattern.</p>
</div>

<!-- Patterns Reference Table -->
<h4 style="margin:6px 0 3px">Daftar Pola Sarf</h4>
<div class="table-wrap">
<table class="kurator-table" id="tblSarfPatterns">
  <thead>
    <tr>
      <th style="width:40px">#</th>
      <th>Pola (AR)</th>
      <th>Tipe</th>
      <th>Bab / Form</th>
      <th>Deskripsi</th>
      <th>Contoh Root</th>
      <th>Contoh Kata</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($sarfPatterns as $p): ?>
    <tr data-id="<?=(int)$p['id']?>">
      <td><?=(int)$p['id']?></td>
      <td class="ar"><?=htmlspecialchars($p['pattern_ar'])?></td>
      <td><span class="kurator-pill"><?=htmlspecialchars($p['pattern_type'])?></span></td>
      <td class="sarf-bab"><?=htmlspecialchars($p['bab'] ?? $p['form_number'] ?? '-')?></td>
      <td><?=htmlspecialchars($p['description_id'] ?? '')?></td>
      <td class="ar"><?=htmlspecialchars($p['example_root'] ?? '')?></td>
      <td class="ar"><?=htmlspecialchars($p['example_word'] ?? '')?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>

<!-- Classified Forms Browser -->
<h4 style="margin:6px 0 3px">Klasifikasi Word Forms</h4>
<div class="kurator-bar" style="gap:6px">
  <label>Filter Pola
    <select id="sarfFilterPattern" style="background:var(--bg-secondary);color:var(--text-primary);border:1px solid var(--border-color);border-radius:6px;padding:6px 8px;font:inherit">
      <option value="">Semua Pola</option>
      <?php foreach ($sarfPatterns as $p): ?>
      <option value="<?=(int)$p['id']?>"><?=(int)$p['id']?> — <?=htmlspecialchars($p['pattern_ar'])?> (<?=htmlspecialchars($p['pattern_type'])?>)</option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Status
    <select id="sarfFilterVerified" style="background:var(--bg-secondary);color:var(--text-primary);border:1px solid var(--border-color);border-radius:6px;padding:6px 8px;font:inherit">
      <option value="">Semua</option>
      <option value="1">Verified</option>
      <option value="0">Unverified</option>
    </select>
  </label>
  <button class="kurator-btn" id="btnSarfLoad">📥 Muat</button>
  <span class="kurator-stat" id="sarfFormsLabel"></span>
</div>

<div class="table-wrap">
<table class="kurator-table" id="tblSarfForms">
  <thead>
    <tr>
      <th>Word Form</th>
      <th>Root</th>
      <th>Pattern</th>
      <th>Type</th>
      <th>Confidence</th>
      <th>Verified</th>
      <th>Reasoning</th>
    </tr>
  </thead>
  <tbody id="tbodySarfForms"></tbody>
</table>
</div>

<div class="kurator-bar" id="sarfPagination" style="display:none">
  <button class="kurator-btn secondary" id="btnSarfPrev">← Sebelumnya</button>
  <span class="kurator-stat" id="sarfPageInfo"></span>
  <button class="kurator-btn secondary" id="btnSarfNext">Berikutnya →</button>
</div>

<h4 style="margin:6px 0 3px">Klasifikasi Otomatis (CLI)</h4>
<p style="color:var(--text-muted);font-size:13px">
  Script <code>data/words-kurator-by-ai/run_auto_sarf.php</code> belum dijalankan.
  Jalankan dari terminal / PowerShell:</p>
<pre style="background:#0b0f19;border:1px solid var(--border-color);border-radius:8px;padding:6px;font:12px/1.5 monospace;color:#9ca3af;overflow:auto">
  cd d:\hendi.wibowo\CoreAI-CPanel\src\aiquran
  php data/words-kurator-by-ai/run_auto_sarf.php
</pre>

<script> window.KURATOR_READONLY = <?=$isReadOnly ? 'true' : 'false'?>; </script>

<script>
(function() {
  const SARF_API = 'api/kurator_sarf.php';
  let sarfRows = [];
  let sarfTotal = 0;
  let sarfLimit = 50;
  let sarfOffset = 0;

  function log(msg) {
    // reuse existing log if present; otherwise console
    const el = document.getElementById('kuratorLog');
    if (el) {
      const line = document.createElement('div');
      line.textContent = '[' + new Date().toLocaleTimeString() + '] [Sarf] ' + msg;
      el.appendChild(line);
      el.scrollTop = el.scrollHeight;
    } else {
      console.log('[Sarf]', msg);
    }
  }

  function escapeHtml(s) {
    return (s || '').replace(/[&<>"']/g, function(c) { return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]; });
  }

  async function sarfLoadStats() {
    try {
      const res = await fetch(SARF_API + '?action=stats');
      const d = await res.json();
      if (!res.ok) { log('Stats error: ' + (d.error || res.status)); return; }

      // Top bar numbers
      document.getElementById('sarfStatVerified').textContent = d.total_classified;
      document.getElementById('sarfStatPending').textContent = d.total_pending;

      // Overall progress
      const totalWords = d.total_classified + d.total_pending;
      const pct = totalWords > 0 ? Math.round((d.total_classified / totalWords) * 100) : 0;
      document.getElementById('sarfPct').textContent = pct + '%';
      document.getElementById('sarfFill').style.width = pct + '%';
      document.getElementById('sarfVerified').textContent = d.total_verified;
      document.getElementById('sarfUnverified').textContent = d.total_unverified;
      document.getElementById('sarfPendingMeta').textContent = d.total_pending;

      // Pattern breakdown bars
      const barsEl = document.getElementById('sarfPatternBars');
      if (barsEl && d.patterns) {
        const maxCount = Math.max(...d.patterns.map(p => p.count || 0), 1);
        barsEl.innerHTML = d.patterns.map(function(p) {
          const count = p.count || 0;
          const ver = p.verified_count || 0;
          const w = Math.round((count / maxCount) * 100);
          const wVer = count > 0 ? Math.round((ver / count) * 100) : 0;
          return '<div class="pattern-bar-row" data-pattern-id="' + p.id + '" title="Klik untuk filter">' +
            '<div class="pattern-bar-info">' +
              '<span class="pattern-bar-name">' + escapeHtml(p.pattern_ar) + '</span>' +
              '<span class="pattern-bar-type">' + escapeHtml(p.pattern_type) + '</span>' +
            '</div>' +
            '<div class="pattern-bar-track">' +
              '<div class="pattern-bar-fill" style="width:' + w + '%"></div>' +
              '<div class="pattern-bar-ver" style="width:' + wVer + '%"></div>' +
            '</div>' +
            '<div class="pattern-bar-meta">' + count + ' / ' + ver + ' verified</div>' +
          '</div>';
        }).join('');
        barsEl.querySelectorAll('.pattern-bar-row').forEach(function(row) {
          row.style.cursor = 'pointer';
          row.addEventListener('click', function() {
            const pid = row.dataset.patternId;
            const sel = document.getElementById('sarfFilterPattern');
            if (sel) { sel.value = pid; sarfLoadForms(0); }
          });
        });
      }
    } catch(e) {
      log('Stats load failed: ' + e.message);
    }
  }

  async function sarfLoadForms(offset) {
    sarfOffset = offset;
    const patternId = document.getElementById('sarfFilterPattern').value;
    const verified  = document.getElementById('sarfFilterVerified').value;
    let url = SARF_API + '?action=classified&limit=' + sarfLimit + '&offset=' + offset;
    if (patternId) url += '&pattern_id=' + encodeURIComponent(patternId);
    if (verified !== '') url += '&verified=' + encodeURIComponent(verified);
    try {
      const res = await fetch(url);
      const d = await res.json();
      if (!res.ok) { log('Classified error: ' + (d.error || res.status)); return; }
      sarfRows = d.rows || [];
      sarfTotal = d.total || 0;
      renderSarfForms();
      updateSarfPagination();
    } catch(e) {
      log('Forms load failed: ' + e.message);
    }
  }

  function renderSarfForms() {
    const tbody = document.getElementById('tbodySarfForms');
    if (!tbody) return;
    if (!sarfRows.length) {
      tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:20px">Tidak ada data.</td></tr>';
      document.getElementById('sarfFormsLabel').textContent = '';
      return;
    }
    tbody.innerHTML = sarfRows.map(function(r) {
      const conf = parseFloat(r.confidence || 0);
      const confCls = conf >= 0.9 ? 'conf-high' : (conf >= 0.7 ? 'conf-mid' : 'conf-low');
      const ver = (r.verified == 1);
      return '<tr data-id="' + r.id + '">' +
        '<td class="ar">' + escapeHtml(r.word_form) + '</td>' +
        '<td class="ar">' + escapeHtml(r.root_ar) + '</td>' +
        '<td>' + escapeHtml(r.pattern_ar) + '</td>' +
        '<td><span class="kurator-pill">' + escapeHtml(r.pattern_type) + '</span></td>' +
        '<td><span class="conf-badge ' + confCls + '">' + (conf * 100).toFixed(0) + '%</span></td>' +
        '<td><button class="verify-toggle ' + (ver ? 'verified' : '') + '">' + (ver ? '✓ Verified' : '○ Unverified') + '</button></td>' +
        '<td class="ctx">' + escapeHtml((r.ai_reasoning || '').slice(0, 120)) + '</td>' +
      '</tr>';
    }).join('');

    tbody.querySelectorAll('tr').forEach(function(tr) {
      const btn = tr.querySelector('.verify-toggle');
      if (!btn) return;

      if (window.KURATOR_READONLY) {
        btn.disabled = true;
        btn.style.cursor = 'not-allowed';
        btn.title = 'Verifikasi hanya untuk admin';
        return;
      }

      btn.addEventListener('click', async function() {
        const id = tr.dataset.id;
        btn.disabled = true;
        try {
          const res = await fetch(SARF_API + '?action=toggle_verify', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: parseInt(id, 10) }),
          });
          const d = await res.json();
          if (!res.ok) { log('Toggle error: ' + (d.error || res.status)); return; }
          const now = d.verified == 1;
          btn.classList.toggle('verified', now);
          btn.textContent = now ? '✓ Verified' : '○ Unverified';
        } catch(e) {
          log('Toggle failed: ' + e.message);
        } finally {
          btn.disabled = false;
        }
      });
    });

    const start = sarfOffset + 1;
    const end = Math.min(sarfOffset + sarfLimit, sarfTotal);
    document.getElementById('sarfFormsLabel').textContent = 'Menampilkan ' + start + '–' + end + ' dari ' + sarfTotal;
  }

  function updateSarfPagination() {
    const wrap = document.getElementById('sarfPagination');
    const prev = document.getElementById('btnSarfPrev');
    const next = document.getElementById('btnSarfNext');
    const info = document.getElementById('sarfPageInfo');
    if (!wrap) return;
    if (sarfTotal === 0) { wrap.style.display = 'none'; return; }
    wrap.style.display = '';
    const currentPage = Math.floor(sarfOffset / sarfLimit) + 1;
    const totalPages = Math.ceil(sarfTotal / sarfLimit);
    if (info) info.textContent = 'Halaman ' + currentPage + ' / ' + totalPages;
    if (prev) prev.disabled = sarfOffset <= 0;
    if (next) next.disabled = (sarfOffset + sarfLimit) >= sarfTotal;
  }

  document.getElementById('btnSarfLoad')?.addEventListener('click', function() { sarfLoadForms(0); });
  document.getElementById('btnSarfPrev')?.addEventListener('click', function() { if (sarfOffset >= sarfLimit) sarfLoadForms(sarfOffset - sarfLimit); });
  document.getElementById('btnSarfNext')?.addEventListener('click', function() { sarfLoadForms(sarfOffset + sarfLimit); });

  // Auto-load stats on page load
  if (document.getElementById('sarfOverallProgress')) {
    (async function init() {
      await sarfLoadStats();
      log('Sarf monitoring siap. Klik Muat untuk melihat klasifikasi word forms.');
    })();
  }

  // Expose for inline onclick usage
  window.sarfLoadStats = sarfLoadStats;
})();</script>

<?php endif; ?>

<script>
(function() {
  const ENDPOINT = 'data/words-kurator-by-ai/';

  // ---- Prompts (mirroring standalone kurator) ----
  const SYSTEM_PROMPT = [
    'Anda adalah pakar linguistik Arab klasik dan tafsir Al-Quran.',
    'Tugas: berikan makna akar kata (kata dasar) 3 huruf Arab yang diberikan,',
    'HANYA dalam format JSON valid, tanpa teks lain di luar JSON, persis skema ini:',
    '{"meaning_ar": "...", "meaning_en": "...", "meaning_id": "..."}',
    '- meaning_ar: definisi singkat (1 kalimat) dalam bahasa Arab.',
    '- meaning_en: definisi singkat (1 kalimat) dalam bahasa Inggris.',
    '- meaning_id: definisi singkat (1 kalimat) dalam bahasa Indonesia.',
    'Dasarkan jawaban pada konteks kemunculan nyata di Al-Quran yang diberikan,',
    'bukan tebakan generik. Jangan sertakan tanda kutip markdown/backtick.'
  ].join(' ');

  function buildUserPrompt(root) {
    const lines = [
      'Akar kata: ' + root.root_ar,
      'Frekuensi kemunculan di Al-Quran: ' + root.frequency + 'x',
      'Contoh bentuk kata turunan: ' + (root.word_forms.join(', ') || '(tidak ada)'),
      'Contoh ayat yang mengandung kata dari akar ini:'
    ];
    root.sample_ayahs.forEach(function(a, i) {
      lines.push((i+1) + '. QS ' + a.surah + ':' + a.ayah + ' — kata "' + a.word_form + '" — terjemahan: "' + (a.translation_id || '(tidak ada terjemahan)') + '"');
    });
    lines.push('Berdasarkan konteks di atas, berikan makna akar kata ini sesuai skema JSON.');
    return lines.join('\n');
  }

  function log(msg) {
    const el = document.getElementById('kuratorLog');
    if (!el) return;
    const line = document.createElement('div');
    line.textContent = '[' + new Date().toLocaleTimeString() + '] ' + msg;
    el.appendChild(line);
    el.scrollTop = el.scrollHeight;
  }

  function escapeHtml(s) {
    return (s || '').replace(/[&<>"']/g, function(c) { return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]; });
  }

  let ROOTS = [];
  let PROVIDERS_STATUS = null;

  // ---- Provider status ----
  async function loadStatus() {
    try {
      const res = await fetch(ENDPOINT + 'status.php');
      const data = await res.json();
      PROVIDERS_STATUS = data;
      const sel = document.getElementById('kuratorProvider');
      if (sel && data.allProviders) {
        sel.innerHTML = data.allProviders.map(function(p) {
          const ok = (data.configured || []).includes(p);
          return '<option value="' + p + '" ' + (!ok ? 'disabled' : '') + ' ' + (p === data.activeProvider ? 'selected' : '') + '>' + p + (ok ? '' : ' (belum ada key)') + '</option>';
        }).join('');
      }
      const genBtn = document.getElementById('btnKuratorGenerateAll');
      if (genBtn && data.configured && data.configured.length === 0) genBtn.disabled = true;
    } catch(e) {
      log('Gagal load status provider: ' + e.message);
    }
  }

  async function loadPending() {
    try {
      const res = await fetch(ENDPOINT + 'list_roots.php?limit=0&offset=0');
      const data = await res.json();
      const stat = document.querySelector('.kurator-bar .kurator-stat b');
      if (stat && data.total_pending !== undefined) stat.textContent = data.total_pending;
    } catch(e) {
      log('Gagal load pending: ' + e.message);
    }
  }

  async function loadBatch() {
    const limit = document.getElementById('kuratorBatchSize').value;
    try {
      const res = await fetch(ENDPOINT + 'list_roots.php?limit=' + limit + '&offset=0');
      const data = await res.json();
      ROOTS = (data.roots || []).map(function(r) {
        return { ...r, meaning_ar: '', meaning_en: '', meaning_id: '', status: 'pending', selected: true };
      });
      renderTable();
      const genBtn = document.getElementById('btnKuratorGenerateAll');
      const applyBtn = document.getElementById('btnKuratorApply');
      if (genBtn) genBtn.disabled = !(PROVIDERS_STATUS && PROVIDERS_STATUS.configured && PROVIDERS_STATUS.configured.length > 0) || ROOTS.length === 0;
      if (applyBtn) applyBtn.disabled = true;
      log('Memuat ' + ROOTS.length + ' root (dari ' + data.total_pending + ' yang belum dikurasi).');
    } catch(e) {
      log('Gagal muat batch: ' + e.message);
    }
  }

  function renderTable() {
    const tbody = document.getElementById('tbodyKuratorRoots');
    if (!tbody) return;
    tbody.innerHTML = ROOTS.map(function(r, i) {
      const ctx = r.sample_ayahs.map(function(a) {
        return '<b>' + a.surah + ':' + a.ayah + '</b> "' + escapeHtml(a.word_form) + '" — ' + escapeHtml((a.translation_id || '').slice(0, 60)) + ((a.translation_id || '').length > 60 ? '…' : '');
      }).join('<br>');
      const statusClass = r.status === 'ok' ? 'ok' : (r.status === 'err' ? 'err' : 'pending');
      const statusText = r.status === 'ok' ? '✓ OK' : (r.status === 'err' ? '✕ Error' : '…');
      const ro = window.KURATOR_READONLY ? ' readonly' : '';
      const dis = window.KURATOR_READONLY ? ' disabled' : '';
      return '<tr data-idx="' + i + '"' + (window.KURATOR_READONLY ? ' style="opacity:.92"' : '') + '>' +
        '<td><input type="checkbox" class="rowChk" ' + (r.selected ? 'checked' : '') + dis + '></td>' +
        '<td class="ar">' + escapeHtml(r.root_ar) + '</td>' +
        '<td>' + r.frequency + '</td>' +
        '<td class="ctx">' + ctx + '</td>' +
        '<td><textarea class="m-ar" dir="rtl"' + ro + '>' + escapeHtml(r.meaning_ar) + '</textarea></td>' +
        '<td><textarea class="m-en"' + ro + '>' + escapeHtml(r.meaning_en) + '</textarea></td>' +
        '<td><textarea class="m-id"' + ro + '>' + escapeHtml(r.meaning_id) + '</textarea></td>' +
        '<td class="row-status ' + statusClass + '"' + (window.KURATOR_READONLY ? ' title="Hanya admin yang dapat mengedit"' : '') + '>' + statusText + '</td>' +
      '</tr>';
    }).join('');

    tbody.querySelectorAll('tr').forEach(function(tr) {
      const idx = +tr.dataset.idx;
      const chk = tr.querySelector('.rowChk');
      const mar = tr.querySelector('.m-ar');
      const men = tr.querySelector('.m-en');
      const mid = tr.querySelector('.m-id');
      if (chk) chk.onchange = function(e) { ROOTS[idx].selected = e.target.checked; };
      if (mar && !window.KURATOR_READONLY) mar.oninput = function(e) { ROOTS[idx].meaning_ar = e.target.value; };
      if (men && !window.KURATOR_READONLY) men.oninput = function(e) { ROOTS[idx].meaning_en = e.target.value; };
      if (mid && !window.KURATOR_READONLY) mid.oninput = function(e) { ROOTS[idx].meaning_id = e.target.value; };
    });
  }

  function parseAiJson(text) {
    let t = text.trim().replace(/^```(?:json)?\s*/i, '').replace(/\s*```$/, '').trim();
    const a = t.indexOf('{'), b = t.lastIndexOf('}');
    if (a >= 0 && b > a) t = t.slice(a, b + 1);
    return JSON.parse(t);
  }

  async function generateOne(root, provider) {
    const res = await fetch(ENDPOINT + 'proxy.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ provider, system: SYSTEM_PROMPT, user: buildUserPrompt(root) }),
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || 'HTTP ' + res.status);
    return parseAiJson(data.text);
  }

  async function generateAll() {
    const provider = document.getElementById('kuratorProvider').value;
    const btn = document.getElementById('btnKuratorGenerateAll');
    const prog = document.getElementById('kuratorProgressLabel');
    btn.disabled = true;
    let ok = 0, err = 0;

    for (let i = 0; i < ROOTS.length; i++) {
      const r = ROOTS[i];
      if (prog) prog.textContent = 'Generating ' + (i + 1) + '/' + ROOTS.length + ' (' + r.root_ar + ')…';
      try {
        const parsed = await generateOne(r, provider);
        r.meaning_ar = parsed.meaning_ar || '';
        r.meaning_en = parsed.meaning_en || '';
        r.meaning_id = parsed.meaning_id || '';
        r.status = 'ok';
        ok++;
      } catch (e) {
        r.status = 'err';
        log('✕ ' + r.root_ar + ': ' + e.message);
        err++;
      }
      renderTable();
    }
    if (prog) prog.textContent = 'Selesai: ' + ok + ' sukses, ' + err + ' gagal.';
    log('Generate batch selesai: ' + ok + ' sukses, ' + err + ' gagal.');
    btn.disabled = false;
    const applyBtn = document.getElementById('btnKuratorApply');
    if (applyBtn) applyBtn.disabled = ok === 0;
  }

  async function applyAll() {
    const items = ROOTS.filter(function(r) {
      return r.selected && (r.meaning_id || r.meaning_en || r.meaning_ar);
    }).map(function(r) {
      return { root_ar: r.root_ar, meaning_ar: r.meaning_ar, meaning_en: r.meaning_en, meaning_id: r.meaning_id };
    });
    if (!items.length) { log('Tidak ada baris terisi/terpilih untuk diterapkan.'); return; }

    try {
      const res = await fetch(ENDPOINT + 'apply.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ items }),
      });
      const data = await res.json();
      if (!res.ok) { log('✕ Gagal menerapkan: ' + (data.error || res.status)); return; }
      log('✓ ' + data.applied + ' root diterapkan ke database lokal + ditambahkan ke ' + data.sql_file);
      await loadPending();
    } catch(e) {
      log('✕ Apply error: ' + e.message);
    }
  }

  // Wire events
  const btnLoad = document.getElementById('btnKuratorLoad');
  const btnGen = document.getElementById('btnKuratorGenerateAll');
  const btnApp = document.getElementById('btnKuratorApply');
  const chkAll = document.getElementById('chkKuratorAll');

  if (btnLoad) btnLoad.onclick = loadBatch;
  if (btnGen && !window.KURATOR_READONLY) btnGen.onclick = generateAll;
  if (btnApp && !window.KURATOR_READONLY) btnApp.onclick = applyAll;
  if (chkAll) chkAll.onchange = function(e) { ROOTS.forEach(function(r) { r.selected = e.target.checked; }); renderTable(); };

  if (document.getElementById('btnKuratorGenerateAll')) {
    (async function init() {
      await loadStatus();
      await loadPending();
      log('Siap. Klik "' + document.getElementById('btnKuratorLoad').textContent.trim() + '" untuk mengambil root yang belum dikurasi.');
    })();
  }
})();
</script>

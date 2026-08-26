/**
 * FACTA — Main Frontend Application
 * Vanilla JS, no frameworks, no build step
 */

// ============================================================
// Theme & UI Helpers
// ============================================================
const html = document.documentElement;
const menuToggle = document.getElementById('menuToggle');
const sidebar = document.getElementById('sidebar');
const overlay = document.getElementById('overlay');
const themeToggle = document.getElementById('themeToggle');
const langSelect = document.getElementById('langSelect');
const toastContainer = document.getElementById('toastContainer');

function initUI() {
    // Theme
    const savedTheme = getCookie('theme') || 'dark';
    // always start dark, saved override only for explicit light
    const effective = savedTheme === 'light' ? 'light' : 'dark';
    html.setAttribute('data-theme', effective);
    updateThemeIcon(effective);

    // Sidebar toggle (logo icon). Mobile CSS slides the sidebar in via
    // .open (+ overlay); desktop hides it via .collapsed — the old code
    // only toggled .collapsed, so the icon did nothing on mobile.
    if (menuToggle) {
        menuToggle.addEventListener('click', () => {
            if (window.matchMedia('(max-width: 768px)').matches) {
                const open = sidebar.classList.toggle('open');
                overlay.classList.toggle('active', open);
            } else {
                sidebar.classList.toggle('collapsed');
                document.getElementById('main').classList.toggle('full');
            }
        });
    }

    // Overlay (mobile)
    if (overlay) {
        overlay.addEventListener('click', () => {
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
        });
    }

    // Theme toggle
    if (themeToggle) {
        themeToggle.addEventListener('click', () => {
            const current = html.getAttribute('data-theme') || 'dark';
            const next = current === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', next);
            setCookie('theme', next, 365);
            updateThemeIcon(next);
        });
    }

    // Language selector
    if (langSelect) {
        langSelect.addEventListener('change', (e) => {
            setCookie('ui_lang', e.target.value, 365);
            location.reload();
        });
    }

    // Update bookmark count
    updateBookmarkCount();

    initInstantSearch();
    initWordInfo();
    initMobile();
}

// ============================================================
// Mobile: Bottom Tab Bar + Swipe Gestures + Touch Optimisations
// ============================================================
function initMobile() {
    // Bottom tab bar active state
    const tabBar = document.getElementById('mobileTabBar');
    if (tabBar) {
        const page = new URLSearchParams(location.search).get('page') || 'home';
        tabBar.querySelectorAll('.tab-item').forEach(el => {
            el.classList.toggle('active', el.dataset.page === page);
        });
        // Menu tab opens sidebar
        const menuTab = document.getElementById('tabMenu');
        if (menuTab) {
            menuTab.addEventListener('click', (e) => {
                e.preventDefault();
                const sidebar = document.getElementById('sidebar');
                const overlay = document.getElementById('overlay');
                if (sidebar) {
                    const open = sidebar.classList.toggle('open');
                    if (overlay) overlay.classList.toggle('active', open);
                }
            });
        }
    }

    // Swipe gestures on surah page
    const isSurah = document.querySelector('.surah-header');
    if (isSurah) {
        let sx = 0, sy = 0, ex = 0, ey = 0;
        const minSwipe = 60, maxVertical = 80;
        document.addEventListener('touchstart', (e) => {
            sx = e.touches[0].clientX; sy = e.touches[0].clientY;
        }, { passive: true });
        document.addEventListener('touchend', (e) => {
            ex = e.changedTouches[0].clientX; ey = e.changedTouches[0].clientY;
            const dx = ex - sx, dy = ey - sy;
            if (Math.abs(dy) > maxVertical) return;
            if (dx > minSwipe) {
                // swipe right = prev surah
                const prev = document.querySelector('a[href*="page=surah&id="]');
                if (prev && prev.textContent.includes('←')) prev.click();
            } else if (dx < -minSwipe) {
                // swipe left = next surah
                const next = document.querySelector('a[href*="page=surah&id="]');
                // Find the one with arrow on the right side (next)
                const all = Array.from(document.querySelectorAll('a[href*="page=surah&id="]'));
                const nextLink = all.find(a => a.textContent.includes('→'));
                if (nextLink) nextLink.click();
            }
        }, { passive: true });
    }

    // Swipe sidebar open/close
    let sbx = 0;
    document.addEventListener('touchstart', (e) => { sbx = e.touches[0].clientX; }, { passive: true });
    document.addEventListener('touchend', (e) => {
        if (window.innerWidth > 768) return;
        const dx = e.changedTouches[0].clientX - sbx;
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');
        if (!sidebar) return;
        if (sidebar.classList.contains('open')) {
            if (dx < -60) { sidebar.classList.remove('open'); overlay?.classList.remove('active'); }
        } else {
            if (dx > 60 && sbx < 40) { sidebar.classList.add('open'); overlay?.classList.add('active'); }
        }
    }, { passive: true });
}

function updateThemeIcon(theme) {
    if (themeToggle) themeToggle.textContent = theme === 'dark' ? '☀️' : '🌙';
}

// ============================================================
// Toast Notifications
// ============================================================
function toast(message, type = 'info') {
    if (!toastContainer) return;
    const div = document.createElement('div');
    div.className = `toast ${type}`;
    const icon = type === 'success' ? '✓' : type === 'error' ? '✕' : 'ℹ';
    div.innerHTML = `<span style="font-size:16px">${icon}</span> ${escapeHtml(message)}`;
    toastContainer.appendChild(div);
    setTimeout(() => {
        div.style.opacity = '0';
        div.style.transform = 'translateX(100%)';
        setTimeout(() => div.remove(), 300);
    }, 4000);
}

// ============================================================
// Bookmarks
// ============================================================
async function updateBookmarkCount() {
    try {
        const res = await fetch('api/bookmark.php');
        const data = await res.json();
        const count = data.bookmarks?.length || 0;
        const badge = document.getElementById('bookmarkCount');
        if (badge) {
            badge.textContent = count;
            badge.style.display = count > 0 ? 'flex' : 'none';
        }
    } catch (e) {}
}

// ============================================================
// Instant Search (header search-as-you-type dropdown)
// Lightweight preview only — search.php (text/root/derived modes)
// remains the authoritative, full-featured search.
// ============================================================
function initInstantSearch() {
    const input = document.getElementById('globalSearch');
    const panel = document.getElementById('instantSearchPanel');
    if (!input || !panel) return;

    let debounceTimer = null;
    let items = [];
    let activeIndex = -1;

    function closePanel() {
        panel.classList.remove('active');
        panel.innerHTML = '';
        items = [];
        activeIndex = -1;
    }

    function setActive(i) {
        activeIndex = i;
        items.forEach((it, idx) => it.classList.toggle('active', idx === activeIndex));
        items[activeIndex]?.scrollIntoView({ block: 'nearest' });
    }

    function renderResults(data) {
        if (!data.results.length) {
            panel.innerHTML = `<div class="instant-search-empty">${escapeHtml(input.dataset.noResults || 'No results')}</div>`;
            panel.classList.add('active');
            items = [];
            activeIndex = -1;
            return;
        }
        const rows = data.results.map(r => `
      <a class="instant-search-item" href="index.php?page=surah&id=${r.surah_id}&ayah=${r.ayah_number}">
        <div class="location">${escapeHtml(r.surah_name)} (${escapeHtml(r.surah_name_en)}) — Ayat ${r.ayah_number}</div>
        <div class="arabic">${r.text_ar}</div>
        ${r.translation ? `<div class="translation">${r.translation}</div>` : ''}
      </a>`).join('');
        const totalLabel = data.total >= 50 ? '50+' : data.total;
        const seeAllUrl = `index.php?page=search&mode=text&q=${encodeURIComponent(data.query)}&lang=${data.lang}`;
        panel.innerHTML = rows + `<a class="instant-search-footer" href="${seeAllUrl}">${escapeHtml(input.dataset.seeAll || 'See all results')} (${totalLabel}) →</a>`;
        panel.classList.add('active');
        items = Array.from(panel.querySelectorAll('.instant-search-item'));
        activeIndex = -1;
    }

    input.addEventListener('input', () => {
        const q = input.value.trim();
        clearTimeout(debounceTimer);
        if (q.length < 2) { closePanel(); return; }
        debounceTimer = setTimeout(async () => {
            try {
                const res = await fetch(`api/instant_search.php?q=${encodeURIComponent(q)}`);
                const data = await res.json();
                if (input.value.trim() === q) renderResults(data);
            } catch (e) { closePanel(); }
        }, 300);
    });

    input.addEventListener('keydown', (e) => {
        if (!panel.classList.contains('active')) return;
        if (e.key === 'Escape') { closePanel(); input.blur(); return; }
        if (!items.length) return;
        if (e.key === 'ArrowDown') { e.preventDefault(); setActive(Math.min(activeIndex + 1, items.length - 1)); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); setActive(Math.max(activeIndex - 1, 0)); }
        else if (e.key === 'Enter' && activeIndex >= 0) { e.preventDefault(); items[activeIndex].click(); }
    });

    document.addEventListener('click', (e) => {
        if (!panel.contains(e.target) && e.target !== input) closePanel();
    });
}

// ============================================================
// Word Info popup — clicking any highlighted <mark> (search results,
// instant-search dropdown) opens a dialog showing the word's curated
// Arabic root(s) with meanings, Quranic derived forms (dynamic
// morphology matching), and curated synonym/antonym/related roots.
// ============================================================
// Per-card drill-down state for the "Sering Muncul Bersama" tree
// (backlog-1.01.Alpha.016.md). Keyed by card index (a word can match
// up to 3 roots, each gets its own independent breadcrumb/tree state).
// levels[i] = { data: <api root response for that level> }; level 0
// is always the card's own initial fetch, no extra request needed.
let ASSOC_STATE = {};

// Which card (if any) the "Muncul Bersama" expanded modal (#assocExpandModal)
// is currently showing — lets assocRerender() keep both surfaces in sync
// since they share the same ASSOC_STATE entry.
let ASSOC_EXPAND_CARD = null;

// Floating gloss tooltip for touch devices (assoc-chip breadcrumb mode —
// see the .assoc-chip click handler in initWordInfo). Lives on
// document.body, positioned via getBoundingClientRect() on demand, so
// it's never clipped by the chip list's own overflow-y:auto scroll box
// the way a CSS ::after anchored to the chip itself would be.
let CHIP_TOOLTIP_EL = null;
let TOOLTIP_ARMED_CHIP = null;

function showChipTooltip(chip) {
    if (!CHIP_TOOLTIP_EL) {
        CHIP_TOOLTIP_EL = document.createElement('div');
        CHIP_TOOLTIP_EL.className = 'chip-tooltip';
        document.body.appendChild(CHIP_TOOLTIP_EL);
    }
    CHIP_TOOLTIP_EL.textContent = chip.dataset.gloss || '';
    const rect = chip.getBoundingClientRect();
    // Center on the chip, then clamp so it can't run off a narrow phone
    // screen when the chip sits near the left/right edge — offsetWidth
    // is measurable even while opacity:0 (see .chip-tooltip CSS), since
    // that's not the same as display:none.
    const margin = 8;
    const half = CHIP_TOOLTIP_EL.offsetWidth / 2;
    let left = rect.left + rect.width / 2;
    left = Math.min(Math.max(left, half + margin), window.innerWidth - half - margin);
    CHIP_TOOLTIP_EL.style.left = left + 'px';
    CHIP_TOOLTIP_EL.style.top = Math.max(rect.top, margin) + 'px';
    CHIP_TOOLTIP_EL.classList.add('visible');
    TOOLTIP_ARMED_CHIP = chip;
}

function hideChipTooltip() {
    if (CHIP_TOOLTIP_EL) CHIP_TOOLTIP_EL.classList.remove('visible');
    TOOLTIP_ARMED_CHIP = null;
}

function initWordInfo() {
    // Capture phase so we beat the parent <a>'s navigation
    document.addEventListener('click', (e) => {
        const mark = e.target.closest('mark');
        if (!mark) return;
        const word = mark.textContent.trim();
        if (!word) return;
        e.preventDefault();
        e.stopPropagation();
        openWordInfo(word);
    }, true);

    // Delegated handlers for the assoc tree — registered once, survive
    // every innerHTML re-render of the modal body / assoc sections.
    document.addEventListener('click', async (e) => {
        // These handlers serve BOTH the compact Info Kata modal and the
        // expanded "Muncul Bersama" modal it can spawn — both render the
        // same assoc markup (data-card/-level/-root-id) against the same
        // shared ASSOC_STATE, so one delegated set of handlers covers both.
        if (!e.target.closest('#wordInfoModal.active') && !e.target.closest('#assocExpandModal.active')) return;

        // Explorer-style tree row: click an unselected row to select+drill
        // (same as a chip); click the currently-expanded row to collapse
        // back up to this level (same as clicking its parent breadcrumb).
        const treeRow = e.target.closest('.tree-row');
        if (treeRow) {
            e.preventDefault();
            const cardIdx = +treeRow.dataset.card, level = +treeRow.dataset.level, rootId = +treeRow.dataset.rootId;
            if (treeRow.classList.contains('expanded')) {
                const st = ASSOC_STATE[cardIdx];
                if (st) { st.levels = st.levels.slice(0, level + 1); assocRerender(cardIdx); }
            } else {
                await assocDrill(cardIdx, level, rootId);
            }
            return;
        }
        const chip = e.target.closest('.assoc-chip');
        if (chip) {
            e.preventDefault();
            // Touch devices never fire :hover, so the title="" tooltip is
            // unreachable there — first tap shows a floating tooltip
            // instead of drilling; a second tap on the same (now-armed)
            // chip proceeds as usual. The tooltip is a single body-level
            // element (not CSS anchored to the chip) so it isn't clipped
            // by the chip list's own overflow-y:auto scroll box.
            const isTouch = window.matchMedia('(hover: none)').matches;
            if (isTouch && chip.dataset.gloss && TOOLTIP_ARMED_CHIP !== chip) {
                showChipTooltip(chip);
                return;
            }
            hideChipTooltip();
            await assocDrill(+chip.dataset.card, +chip.dataset.level, +chip.dataset.rootId);
            return;
        }
        const searchItem = e.target.closest('.assoc-search-item');
        if (searchItem) {
            e.preventDefault();
            await assocDrill(+searchItem.dataset.card, +searchItem.dataset.level, +searchItem.dataset.rootId);
            closeAssocSearchDropdowns();
            return;
        }
        const crumb = e.target.closest('.assoc-crumb:not(.current)');
        if (crumb) {
            e.preventDefault();
            const st = ASSOC_STATE[+crumb.dataset.card];
            if (st) {
                st.levels = st.levels.slice(0, +crumb.dataset.level + 1);
                assocRerender(+crumb.dataset.card);
            }
            return;
        }
        // Checked before the generic .assoc-layout-btn branch below —
        // this button shares that class for sizing but has no
        // data-layout, so falling through would set st.layout=undefined.
        const expandBtn = e.target.closest('.assoc-expand-btn');
        if (expandBtn) {
            e.preventDefault();
            openAssocExpand(+expandBtn.dataset.card);
            return;
        }
        const layoutBtn = e.target.closest('.assoc-layout-btn');
        if (layoutBtn) {
            e.preventDefault();
            const st = ASSOC_STATE[+layoutBtn.dataset.card];
            if (st) {
                st.layout = layoutBtn.dataset.layout;
                assocRerender(+layoutBtn.dataset.card);
            }
            return;
        }
        // Click outside a search box closes its dropdown.
        if (!e.target.closest('.assoc-search')) closeAssocSearchDropdowns();
        // Click outside any chip dismisses a touch-revealed tooltip.
        if (!e.target.closest('.assoc-chip')) hideChipTooltip();
    });

    // A stale-positioned tooltip is worse than none — drop it as soon as
    // anything scrolls (capture:true also catches the chip list's own
    // nested overflow-y:auto scrolling, which doesn't bubble).
    document.addEventListener('scroll', hideChipTooltip, true);

    // Typeahead filter for "Muncul Bersama" — filters the CURRENT
    // level's already-loaded candidate list client-side (no fetch),
    // matching Arabic root or ID/EN meaning text.
    document.addEventListener('input', (e) => {
        const input = e.target.closest('.assoc-search-input');
        if (!input) return;
        const cardIdx = +input.dataset.card;
        const dropdown = document.getElementById(`assocSearchDropdown-${cardIdx}`);
        const st = ASSOC_STATE[cardIdx];
        if (!dropdown || !st) return;
        const q = input.value.trim();
        if (!q) { dropdown.classList.remove('active'); dropdown.innerHTML = ''; return; }
        const lastIdx = st.levels.length - 1;
        const qLower = q.toLowerCase();
        const items = st.levels[lastIdx].data.associations.filter(it =>
            it.text_ar.includes(q) ||
            (it.gloss_id || '').toLowerCase().includes(qLower) ||
            (it.gloss_en || '').toLowerCase().includes(qLower)
        );
        dropdown.innerHTML = renderAssocSearchDropdown(cardIdx, lastIdx, items);
        dropdown.classList.add('active');
    });
}

function closeAssocSearchDropdowns() {
    document.querySelectorAll('.assoc-search-dropdown.active').forEach(dd => {
        dd.classList.remove('active');
        dd.innerHTML = '';
    });
}

function renderAssocSearchDropdown(cardIdx, level, items) {
    if (!items.length) {
        return `<div class="assoc-search-empty">${escapeHtml(I18N_JS.no_results)}</div>`;
    }
    const CAP = 30;
    const rows = items.slice(0, CAP).map(it => {
        const gloss = escapeHtml((UI_LANG === 'en' ? it.gloss_en : it.gloss_id) || '');
        return `<div class="assoc-search-item" data-card="${cardIdx}" data-level="${level}" data-root-id="${it.root_id}">
          <span class="ar">${escapeHtml(it.text_ar)}</span><span class="cnt">×${it.count}</span><span class="gloss">${gloss}</span>
        </div>`;
    }).join('');
    const more = items.length > CAP
        ? `<div class="assoc-search-more">+${items.length - CAP} — ${escapeHtml(I18N_JS.keep_typing)}</div>`
        : '';
    return rows + more;
}

function assocRerender(cardIdx) {
    hideChipTooltip(); // any chip a tooltip was anchored to is about to be replaced
    const el = document.getElementById(`assocSection-${cardIdx}`);
    if (el) el.innerHTML = renderAssocSection(cardIdx);
    // The expanded modal (if open on this same card) shares ASSOC_STATE —
    // keep it in lockstep so drilling in either surface updates both.
    if (ASSOC_EXPAND_CARD === cardIdx) {
        const elx = document.getElementById(`assocSectionExpanded-${cardIdx}`);
        if (elx) elx.innerHTML = renderAssocSection(cardIdx, true);
    }
}

// Fetch the next drill-down level. Truncates to the clicked level first
// (picking a different branch than previously explored discards the
// old sub-path — always a fresh path from the click point).
async function assocDrill(cardIdx, level, rootId) {
    const st = ASSOC_STATE[cardIdx];
    if (!st) return;
    st.levels = st.levels.slice(0, level + 1);
    const path = st.levels.map(l => l.data.root_id);
    path.push(rootId);

    const el = document.getElementById(`assocSection-${cardIdx}`);
    if (el) el.innerHTML = '<div class="loading-spinner"></div>';
    try {
        const qs = path.map(id => `context[]=${id}`).join('&');
        const res = await fetch(`api/word_info.php?${qs}`);
        const data = await res.json();
        const rootData = data.roots && data.roots[0];
        if (!rootData) throw new Error('empty');
        st.levels.push({ data: rootData });
    } catch (err) {
        // leave st.levels as-is (truncated, pre-push) — re-render just
        // shows the last successfully loaded level again
    }
    assocRerender(cardIdx);
}

// "Sering Muncul Bersama" chip: root Arabic form + frequency ONLY (the
// data model here is deliberately just {root, count} — meaning is
// available on hover via title, not shown inline, so the list stays
// compact even when unlimited/uncapped).
// title=... gives desktop hover a tooltip for free; data-gloss backs the
// floating touch tooltip (showChipTooltip/hideChipTooltip, toggled in
// the click handler below) for devices that never fire :hover at all —
// tapping once reveals it, tapping again (or a different chip) proceeds.
function assocChip(cardIdx, level, item) {
    const gloss = escapeHtml((UI_LANG === 'en' ? item.gloss_en : item.gloss_id) || '');
    return `
      <button type="button" class="word-chip assoc-chip" data-card="${cardIdx}" data-level="${level}" data-root-id="${item.root_id}" title="${gloss}" data-gloss="${gloss}">
        <span class="row"><span class="ar">${escapeHtml(item.text_ar)}</span><span class="cnt">×${item.count}</span></span>
      </button>`;
}

function assocLevelActions(cardIdx, level, data) {
    const path = ASSOC_STATE[cardIdx].levels.slice(0, level + 1).map(l => l.data.root_id);
    const rootsQs = path.map(id => `roots[]=${id}`).join('&');
    let html = `<div class="assoc-level-actions">
        <a class="ayah-btn" href="index.php?page=search&mode=assoc&${rootsQs}">📖 ${escapeHtml(I18N_JS.show_ayahs)} (${data.ayah_count})</a>`;
    if (data.only_count > 0) {
        html += `<a class="ayah-btn assoc-only" href="index.php?page=search&mode=assoc&${rootsQs}&only=1">✅ ${escapeHtml(I18N_JS.only_this_combo)} (${data.only_count})</a>`;
    }
    return html + '</div>';
}

// Windows-Explorer-style tree: each root is one compact row with a
// disclosure arrow. Only the currently SELECTED path stays expanded
// (its children listed indented below); sibling roots not on the path
// stay collapsed to just their row. Recurses one level per call.
function renderTreeLevel(cardIdx, level) {
    const st = ASSOC_STATE[cardIdx];
    const lvl = st.levels[level];
    const isLastLevel = level === st.levels.length - 1;
    const selectedChildId = !isLastLevel ? st.levels[level + 1].data.root_id : null;
    const items = lvl.data.associations;

    if (!items.length) return `<div class="assoc-empty">${escapeHtml(I18N_JS.no_results)}</div>`;

    const rows = items.map(item => {
        const selected = item.root_id === selectedChildId;
        const gloss = escapeHtml((UI_LANG === 'en' ? item.gloss_en : item.gloss_id) || '');
        const row = `<div class="tree-row${selected ? ' expanded' : ''}" data-card="${cardIdx}" data-level="${level}" data-root-id="${item.root_id}">
            <span class="tree-arrow">${selected ? '▾' : '▸'}</span>
            <span class="ar">${escapeHtml(item.text_ar)}</span>
            <span class="cnt">×${item.count}</span>
            ${gloss ? `<span class="tree-gloss">${gloss}</span>` : ''}
          </div>`;
        if (selected) {
            return `<div class="tree-node">${row}<div class="tree-children">${renderTreeLevel(cardIdx, level + 1)}</div></div>`;
        }
        return `<div class="tree-node">${row}</div>`;
    }).join('');
    return `<div class="tree-list">${rows}</div>`;
}

function renderAssocSection(cardIdx, expanded = false) {
    const st = ASSOC_STATE[cardIdx];
    if (!st) return '';
    // The expand button opens a copy of this very panel in a bigger modal
    // (see openAssocExpand) — omit it from that copy, nothing to expand to.
    const expandBtn = expanded ? '' : `<button type="button" class="assoc-layout-btn assoc-expand-btn" data-card="${cardIdx}" title="${escapeHtml(I18N_JS.assoc_expand)}">⛶</button>`;
    const layoutBtns = `
      <span class="assoc-layout-toggle">
        <button type="button" class="assoc-layout-btn ${st.layout === 'breadcrumb' ? 'active' : ''}" data-card="${cardIdx}" data-layout="breadcrumb" title="${escapeHtml(I18N_JS.breadcrumb_view)}">📍</button>
        <button type="button" class="assoc-layout-btn ${st.layout === 'tree' ? 'active' : ''}" data-card="${cardIdx}" data-layout="tree" title="${escapeHtml(I18N_JS.tree_view)}">🌳</button>
        ${expandBtn}
      </span>`;
    const searchBox = `
      <div class="assoc-search">
        <input type="text" class="assoc-search-input" data-card="${cardIdx}" placeholder="🔍 ${escapeHtml(I18N_JS.assoc_search_placeholder)}" autocomplete="off">
        <div class="assoc-search-dropdown" id="assocSearchDropdown-${cardIdx}"></div>
      </div>`;
    const lastIdx = st.levels.length - 1;
    const last = st.levels[lastIdx];

    if (st.layout === 'tree') {
        return layoutBtns + searchBox + renderTreeLevel(cardIdx, 0) + assocLevelActions(cardIdx, lastIdx, last.data);
    }

    // breadcrumb (default): only the last level's chips shown, path as
    // clickable segments above it.
    const crumbs = st.levels.map((lvl, i) => `
        <button type="button" class="assoc-crumb ${i === lastIdx ? 'current' : ''}" data-card="${cardIdx}" data-level="${i}">${escapeHtml(lvl.data.root_ar)}</button>
    `).join('<span class="assoc-crumb-sep">›</span>');
    const chips = last.data.associations.map(a => assocChip(cardIdx, lastIdx, a)).join('');
    return layoutBtns + searchBox +
        `<div class="assoc-breadcrumb">${crumbs}</div>` +
        `<div class="word-chips">${chips || `<span class="assoc-empty">${escapeHtml(I18N_JS.no_results)}</span>`}</div>` +
        assocLevelActions(cardIdx, lastIdx, last.data);
}

function ensureWordInfoModal() {
    let m = document.getElementById('wordInfoModal');
    if (m) return m;
    m = document.createElement('div');
    m.className = 'modal-overlay';
    m.id = 'wordInfoModal';
    m.innerHTML = `<div class="modal">
      <div class="modal-header"><h3 id="wordInfoTitle">🔤</h3>
      <button class="modal-close" type="button">✕</button></div>
      <div class="modal-body" id="wordInfoBody"></div></div>`;
    document.body.appendChild(m);
    m.querySelector('.modal-close').onclick = () => { m.classList.remove('active'); hideChipTooltip(); };
    m.addEventListener('click', (e) => { if (e.target === m) { m.classList.remove('active'); hideChipTooltip(); } });
    return m;
}

// Stacks ON TOP of #wordInfoModal (which stays open underneath) — a
// dedicated, bigger surface for just the "Muncul Bersama" panel so
// drilling through many levels/roots isn't cramped inside the compact
// word-info card. Shares ASSOC_STATE with it (see assocRerender), so
// closing this one back to the word-info modal loses no progress.
function ensureAssocExpandModal() {
    let m = document.getElementById('assocExpandModal');
    if (m) return m;
    m = document.createElement('div');
    m.className = 'modal-overlay';
    m.id = 'assocExpandModal';
    m.innerHTML = `<div class="modal">
      <div class="modal-header"><h3 id="assocExpandTitle">🔗</h3>
      <button class="modal-close" type="button">✕</button></div>
      <div class="modal-body" id="assocExpandBody"></div></div>`;
    document.body.appendChild(m);
    const close = () => { m.classList.remove('active'); hideChipTooltip(); ASSOC_EXPAND_CARD = null; };
    m.querySelector('.modal-close').onclick = close;
    m.addEventListener('click', (e) => { if (e.target === m) close(); });
    return m;
}

function openAssocExpand(cardIdx) {
    const st = ASSOC_STATE[cardIdx];
    if (!st) return;
    const modal = ensureAssocExpandModal();
    const title = document.getElementById('assocExpandTitle');
    const body = document.getElementById('assocExpandBody');
    title.innerHTML = `🔗 ${escapeHtml(I18N_JS.associations)} — <span style="font-family:var(--font-arabic)">${escapeHtml(st.levels[0].data.root_ar)}</span>`;
    ASSOC_EXPAND_CARD = cardIdx;
    body.innerHTML = `<div class="assoc-section assoc-section-expanded" id="assocSectionExpanded-${cardIdx}">${renderAssocSection(cardIdx, true)}</div>`;
    modal.classList.add('active');
}

function wordInfoChips(items, cssClass) {
    return items.map(x => `
      <a class="rel-chip ${cssClass}" href="index.php?page=search&mode=root&root=${encodeURIComponent(x.root_ar)}">
        <span class="ar">${escapeHtml(x.root_ar)}</span>
        <span>${escapeHtml((UI_LANG === 'en' ? x.meaning_en : x.meaning_id) || x.meaning_en || '')}</span>
      </a>`).join('');
}

// A derived-form word card: clicking it jumps to "word mode" search —
// the exact form only, not sibling derivations.
function derivedChip(f) {
    const gloss = escapeHtml((UI_LANG === 'en' ? f.gloss_en : f.gloss_id) || '');
    return `
      <a class="word-chip" href="index.php?page=search&mode=word&w=${encodeURIComponent(f.clean)}&d=${encodeURIComponent(f.text_ar)}">
        <span class="row"><span class="ar">${escapeHtml(f.text_ar)}</span><span class="cnt">×${f.count}</span></span>
        ${gloss ? `<span class="gloss">${gloss}</span>` : ''}
      </a>`;
}

async function openWordInfo(word) {
    const modal = ensureWordInfoModal();
    const title = document.getElementById('wordInfoTitle');
    const body = document.getElementById('wordInfoBody');
    title.innerHTML = `🔤 ${escapeHtml(I18N_JS.word_info)} — <span style="font-family:var(--font-arabic)">${escapeHtml(word)}</span>`;
    body.innerHTML = '<div class="loading-spinner"></div>';
    modal.classList.add('active');
    ASSOC_STATE = {};
    // A leftover expanded-modal card from a PREVIOUS word would otherwise
    // still match assocRerender()'s ASSOC_EXPAND_CARD check by coincidence.
    document.getElementById('assocExpandModal')?.classList.remove('active');
    ASSOC_EXPAND_CARD = null;

    try {
        const res = await fetch(`api/word_info.php?word=${encodeURIComponent(word)}`);
        const data = await res.json();
        if (!data.roots.length) {
            body.innerHTML = `<div class="empty-state"><div class="icon">🔤</div><p>${escapeHtml(I18N_JS.no_results)}</p></div>`;
            return;
        }
        body.innerHTML = data.roots.map((r, i) => {
            ASSOC_STATE[i] = { layout: 'breadcrumb', levels: [{ data: r }] };
            const meaning = (UI_LANG === 'en' ? r.meaning_en : r.meaning_id) || r.meaning_en || '';
            const derived = r.derived.map(f => derivedChip(f)).join('');
            return `
            <div class="word-info-root">
              <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
                <span class="word-info-rootword">${escapeHtml(r.root_ar)}</span>
                <span style="font-size:13px;color:var(--text-muted)">${escapeHtml(r.root_en || '')}</span>
                <span style="font-size:14px;font-weight:600">${escapeHtml(meaning)}</span>
                <a class="ayah-btn" style="margin-left:auto" href="index.php?page=search&mode=root&root=${encodeURIComponent(r.root_ar)}">🔤 ${escapeHtml(I18N_JS.root_search)}</a>
              </div>
              ${r.derived.length ? `<details class="word-info-section"><summary>${escapeHtml(I18N_JS.derived_forms)} (${r.derived.length})</summary><div class="word-chips">${derived}</div></details>` : ''}
              <details class="word-info-section"><summary>${escapeHtml(I18N_JS.synonyms)} (${r.synonyms.length})</summary>${r.synonyms.length ? `<div class="word-chips" style="direction:ltr">${wordInfoChips(r.synonyms, 'rel-syn')}</div>` : `<div class="assoc-empty">${escapeHtml(I18N_JS.no_results)}</div>`}</details>
              ${r.antonyms.length ? `<details class="word-info-section"><summary>${escapeHtml(I18N_JS.antonyms)} (${r.antonyms.length})</summary><div class="word-chips" style="direction:ltr">${wordInfoChips(r.antonyms, 'rel-ant')}</div></details>` : ''}
              <details class="word-info-section"><summary>${escapeHtml(I18N_JS.related_words)} (${r.related.length})</summary>${r.related.length ? `<div class="word-chips" style="direction:ltr">${wordInfoChips(r.related, 'rel-rel')}</div>` : `<div class="assoc-empty">${escapeHtml(I18N_JS.no_results)}</div>`}</details>
              <details class="word-info-section"><summary>${escapeHtml(I18N_JS.associations)} (${r.associations.length})</summary>
                <div class="assoc-section" id="assocSection-${i}">${renderAssocSection(i)}</div>
              </details>
            </div>`;
        }).join('') + `<p style="font-size:11px;color:var(--text-muted);margin-top:12px">ⓘ ${escapeHtml(I18N_JS.morphology_disclaimer)}</p>`;
    } catch (err) {
        body.innerHTML = `<div class="empty-state"><p>${escapeHtml(I18N_JS.no_results)}</p></div>`;
    }
}

// ============================================================
// Card Slider — paginates a grid's children into sliding pages so a
// long list (e.g. 114 surahs, 30 juz) fits on screen without scrolling.
// Progressive enhancement over a plain CSS grid: this only ever ADDS
// the sliding behavior — without it (JS failure, stale cache, or the
// user's "list" browse mode) the cards still render as a normal grid.
// Auto-advances every 6s, pausing on hover/touch/focus; supports swipe.
// Returns { setFiltering(bool) } so a page-local search box can drop
// back to a plain scrollable grid of just the matching cards.
// ============================================================
const SLIDER_AUTO_MS = 10000;
const SLIDER_TOUCH_RESUME_MS = 12000;

function sliderColumns() {
    const w = window.innerWidth;
    if (w >= 1100) return 5;   // desktop: 5 cols x 3 rows = 15/page
    if (w >= 700) return 3;    // tablet:  3 cols x 3 rows = 9/page
    return 2;                  // mobile:  2 cols x 3 rows = 6/page
}

function initSlider(trackEl, controlsEl, rows = 3) {
    if (!trackEl || !controlsEl) return null;
    // "list" browse mode (Settings): leave the plain CSS grid untouched.
    if (getCookie('browse_mode') === 'list') { controlsEl.style.display = 'none'; return null; }

    const sliderEl = trackEl.closest('.slider');
    const originals = Array.from(trackEl.children);
    if (!originals.length) return null;

    const prevBtn = controlsEl.querySelector('.slider-prev');
    const nextBtn = controlsEl.querySelector('.slider-next');
    const dotsEl = controlsEl.querySelector('.slider-dots');
    let pages = [];
    let current = 0;
    let cols = 0;
    let autoTimer = null;
    let paused = false;
    let touchResumeTimer = null;

    function render() {
        trackEl.style.transform = `translateX(-${current * 100}%)`;
        pages.forEach((p, i) => p.classList.toggle('active', i === current));
        if (prevBtn) prevBtn.disabled = current === 0;
        if (nextBtn) nextBtn.disabled = current === pages.length - 1;
        if (dotsEl) {
            dotsEl.innerHTML = '';
            pages.forEach((_, i) => {
                const dot = document.createElement('button');
                dot.type = 'button';
                dot.className = 'slider-dot' + (i === current ? ' active' : '');
                dot.onclick = () => { goTo(i); };
                dotsEl.appendChild(dot);
            });
        }
        controlsEl.style.display = pages.length > 1 ? 'flex' : 'none';
    }

    function build() {
        cols = sliderColumns();
        const perPage = cols * rows;
        trackEl.style.setProperty('--slider-cols', cols);
        trackEl.innerHTML = '';
        pages = [];
        for (let i = 0; i < originals.length; i += perPage) {
            const pageEl = document.createElement('div');
            pageEl.className = 'slider-page';
            originals.slice(i, i + perPage).forEach(item => pageEl.appendChild(item));
            trackEl.appendChild(pageEl);
            pages.push(pageEl);
        }
        sliderEl.classList.add('slider-ready');
        current = Math.min(current, pages.length - 1);
        render();
    }

    function goTo(i) {
        current = Math.max(0, Math.min(pages.length - 1, i));
        render();
        startAuto(); // manual navigation resets the auto-advance clock
    }

    function startAuto() {
        clearInterval(autoTimer);
        autoTimer = setInterval(() => {
            if (paused || document.hidden || sliderEl.classList.contains('filtering') || pages.length < 2) return;
            current = (current + 1) % pages.length; // wrap around
            render();
        }, SLIDER_AUTO_MS);
    }

    if (prevBtn) prevBtn.onclick = () => goTo(current - 1);
    if (nextBtn) nextBtn.onclick = () => goTo(current + 1);

    // Pause while the pointer is over the slider or it holds focus;
    // touch pauses too, resuming after a quiet period.
    sliderEl.addEventListener('mouseenter', () => { paused = true; });
    sliderEl.addEventListener('mouseleave', () => { paused = false; });
    sliderEl.addEventListener('focusin', () => { paused = true; });
    sliderEl.addEventListener('focusout', () => { paused = false; });

    // Swipe navigation (also acts as the touch pause trigger)
    let touchStartX = null;
    sliderEl.addEventListener('touchstart', (e) => {
        paused = true;
        clearTimeout(touchResumeTimer);
        touchStartX = e.touches[0].clientX;
    }, { passive: true });
    sliderEl.addEventListener('touchend', (e) => {
        if (touchStartX !== null) {
            const dx = e.changedTouches[0].clientX - touchStartX;
            if (Math.abs(dx) > 40) goTo(dx < 0 ? current + 1 : current - 1);
            touchStartX = null;
        }
        touchResumeTimer = setTimeout(() => { paused = false; }, SLIDER_TOUCH_RESUME_MS);
    }, { passive: true });

    // Rebuild page grouping when a resize crosses a column breakpoint
    let resizeTimer = null;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => { if (sliderColumns() !== cols) build(); }, 250);
    });

    build();
    startAuto();

    return {
        setFiltering(active) {
            if (sliderEl) sliderEl.classList.toggle('filtering', active);
            if (!active) render();
            else controlsEl.style.display = 'none';
        },
    };
}

// ============================================================
// Cookies
// ============================================================
function setCookie(name, value, days) {
    const d = new Date();
    d.setTime(d.getTime() + days * 86400000);
    document.cookie = `${name}=${encodeURIComponent(value)};expires=${d.toUTCString()};path=/`;
}

function getCookie(name) {
    const match = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
    return match ? decodeURIComponent(match[1]) : null;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ============================================================
// Init
// ============================================================
document.addEventListener('DOMContentLoaded', initUI);

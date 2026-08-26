# SmartQuran — Rencana Implementasi v1.01.Alpha.016

**Fitur**: Multi-level co-occurrence tree ("Sering Muncul Bersama" berjenjang) di dialog Info Kata.

**Status dokumen**: **SELESAI SEPENUHNYA** (2026-08-25) — Fase 0 (sourcing + import root, §9) dan Fase 1+ (algoritma, API, mode pencarian, UI drill-down, §10) semua sudah diimplementasikan & diverifikasi. Desain final ada beberapa perubahan dari rencana awal di §1-§8 berdasarkan klarifikasi user selama implementasi — lihat §10 untuk desain yang benar-benar dibangun.

## Keputusan User (2026-08-24)

3 pertanyaan terbuka di §5 (versi awal dokumen ini) sudah dijawab langsung oleh user:

| Keputusan | Jawaban user |
|---|---|
| Cakupan root (§5a) | **Ekstraksi/impor lebih luas (~1.700-an root)** — BUKAN cuma 160 root terkurasi (opsi yang saya rekomendasikan awalnya) |
| Render tree (§5b) | Lazy, 1 level per klik — sesuai rekomendasi |
| Lokasi UI (§5c) | Modal/halaman baru khusus — sesuai rekomendasi |

Karena user memilih cakupan yang LEBIH BESAR dari rekomendasi awal saya, saya lakukan riset tambahan (bukan eksekusi kode — cuma riset sumber data) sebelum menganggap §5a "selesai". Hasilnya ada di §5a (revisi) di bawah — **ternyata ada jalan yang jauh lebih realistis** daripada bikin lemmatizer Arab sendiri seperti yang saya khawatirkan di draf pertama.

---

## 1. Memahami Requirement

Fitur "Sering Muncul Bersama" yang sudah jalan (v1.01.Alpha.014) cuma 1 level: dari root KD1 (misal رزق), tampilkan top-10 kata yang paling sering muncul bersama di ayat-ayat yang memuat turunan KD1.

Permintaan baru: jadikan ini **bisa didalami berjenjang (drill-down)**. Alurnya:

1. User klik salah satu kata di daftar "Sering Muncul Bersama" Level 1 (misal KATA2/KD2).
2. Sistem tampilkan Level 2: kata-kata BARU yang sering muncul bersama **KD1 DAN KD2 sekaligus** (irisan, bukan gabungan) — dengan kata KD1/KD2 sendiri tidak ikut ditampilkan lagi.
3. Berulang ke Level 3, 4, dst. — tiap level mempersempit irisan ayat dengan menambah 1 kata dasar terpilih.
4. **Kondisi berhenti**: begitu tidak ada lagi kata dasar LAIN yang bisa jadi kandidat level berikutnya (semua ayat yang cocok dengan kombinasi terpilih tidak punya kata dasar tambahan lagi di luar yang sudah dipilih) — sistem **tidak menampilkan level baru yang kosong**, melainkan langsung menampilkan daftar ayat yang cocok dengan kombinasi kata dasar yang sudah dipilih sejauh itu (KD1 AND KD2 AND ... AND KDn).
5. Ada juga kondisi "ONLY" — begitu ada ayat dalam irisan saat ini yang **PERSIS** hanya berisi kombinasi terpilih (tidak ada kata dasar lain sama sekali di ayat itu), itu ditawarkan sebagai pilihan terminal tersendiri di antara kandidat level berikutnya, sejajar dengan kandidat kata dasar lain yang masih bisa didalami lagi.
6. Layout "Info Kata" YANG SUDAH ADA (Level 1 flat, seperti sekarang) **tidak boleh berubah**. Fitur berjenjang ini adalah TAMBAHAN, dipicu tombol baru **"Advance"**.

Saya sudah cross-check simulasi matriks & tree yang user berikan di backlog.md — hasil rekonstruksi saya cocok dengan penjelasan naratifnya (dijelaskan di §3 di bawah), jadi saya yakin pemahamannya benar.

---

## 2. Evaluasi Usulan Algoritma User

Usulan user (matriks boolean n × 6236) **secara konsep sudah tepat** — ini persis pola *frequent itemset / association-rule mining* dengan ayat sebagai "transaksi" dan kata dasar sebagai "item". Beberapa penyesuaian yang saya usulkan, murni soal **representasi penyimpanan**, bukan soal konsep:

- Matriks n×6236 penuh (dense) ukurannya kecil (n=160 → ~125KB kalau di-bit-pack), TAPI SQLite tidak punya tipe bitset native, jadi query AND-antar-kolom jadi tidak natural kalau disimpan dense.
- Karena matriksnya **sangat sparse** (lihat data real di §4 — kepadatan cuma ~1.5%), saya usulkan simpan sebagai **tabel edge list ternormalisasi** `root_ayah_map(root_id, ayah_id)` — ini representasi lain dari matriks boolean yang SAMA PERSIS (setiap sel bernilai 1 = satu baris di tabel ini), tapi jauh lebih ringkas dan bisa dikueri pakai SQL biasa (`GROUP BY` + `HAVING COUNT`) tanpa perlu operasi bitwise custom. Idenya tidak berubah, cuma bentuk fisiknya.

---

## 3. Rekonstruksi Simulasi User (Verifikasi Pemahaman)

Dari matriks contoh:

| Ayat | Kata dasar |
|---|---|
| 001 | KD1, KD2 |
| 002 | KD1, KD2, KD3 |
| 003 | KD1, KD2, KD4 |
| 004 | KD1, KD4, KD5 |
| 005 | KD1, KD4, KD6 |

Tree yang digambar user (KD1 → {KD2, KD4} → cabang masing-masing) itu bukan "semua kata yang co-occur dengan KD1" (yang secara literal itu SEMUA dari KD2..KD6) — itu levelnya digambar bertingkat berdasarkan **kata mana yang paling sering muncul duluan**. Jadi:

- KD1 dipilih → kandidat level-1: {KD2 (3 ayat), KD4 (3 ayat)} — KD3/KD5/KD6 TIDAK muncul di level ini karena mereka selalu "menumpang" bersama KD2 atau KD4 (baru relevan satu level lebih dalam).
- Pilih KD2 → irisan {001,002,003}. Kandidat level-2: "ONLY" (ayat 001, tidak ada kata dasar lain), KD3 (ayat 002), KD4 (ayat 003).
- Pilih KD3 dari situ → irisan cuma {002}, dan ayat 002 tidak py kata dasar lain → **berhenti otomatis**, langsung tampilkan ayat 002.
- Sama untuk cabang KD4 dari KD1 langsung → kandidat {KD5, KD6} (masing-masing 1 ayat, keduanya juga langsung terminal).

Pemahaman saya cocok dengan penjelasan naratif user di baris 40-46 backlog.md ("Jika user memilih KD2 ... KD1|KD2|KD3 (ayat 002) ..." dst — itu daftar hasil AKHIR untuk tiap kemungkinan pilihan, bukan ditampilkan sekaligus).

**Catatan implikasi desain**: level-1 yang ditampilkan bukan "semua yang co-occur", tapi "co-occur langsung, sebelum kata lain jadi prasyarat". Ini PERSIS DEFINISI yang sudah dipakai fitur "Sering Muncul Bersama" Level-1 yang sudah jalan sekarang (co-occurrence langsung dengan KD1 saja) — jadi Level-1 di tree baru = 100% sama datanya dengan Level-1 yang sudah ada, cuma di-render sebagai node tree yang expandable, bukan flat list. Ini bagus, artinya endpoint Level-1 yang sudah ada BISA dipakai ulang sebagai kasus k=1 dari algoritma umum di bawah.

---

## 4. Angka Nyata dari Database (untuk validasi skala)

Dicek langsung ke `data/smartquran.db` lokal:

| Metrik | Nilai |
|---|---|
| Root dasar terkurasi (`root_words`) | **160** |
| Ayat | 6.236 |
| Kata turunan unik (`text_ar_clean`, semua bentuk infleksi) | 14.773 |
| Total kemunculan kata (`ayah_words`) | 77.429 |
| Rata-rata ayat per root (sampel acak 15 root) | ~183 (min 2 — نجس, max 1189 — علم) |
| **Estimasi ukuran edge table** (160 root × rata-rata 183 ayat) | **~29.300 baris** |
| Kepadatan matriks 160×6236 | ~1,5% (sangat sparse) |

29 ribuan baris di tabel edge-list itu KECIL untuk SQLite — query intersection/group-by di skala ini akan sub-detik bahkan tanpa index istimewa (dengan index tetap akan sangat cepat).

---

## 5. Pertanyaan Krusial Sebelum Implementasi

Ada 1 keputusan besar yang MENENTUKAN skala & kelayakan proyek ini, dan 2 keputusan UX yang mempengaruhi desain — saya tandai di sini, tapi juga saya ajukan lewat pertanyaan interaktif setelah dokumen ini supaya user bisa jawab cepat:

### 5a. Definisi "kata dasar" (n) — RESOLVED: user pilih cakupan luas (~1.700 root)

Draf pertama dokumen ini mengira opsi ini butuh bikin lemmatizer Arab dari nol (mahal, riset besar). Setelah user memilih opsi ini, saya riset (browsing, bukan eksekusi kode) dan hasilnya **jauh lebih layak dari dugaan awal**:

**Temuan**: [Quranic Arabic Corpus](https://corpus.quran.com/) (proyek akademik oleh Kais Dukes, sudah berjalan lama & jadi rujukan standar linguistik Quran) sudah men-tag **root triliteral untuk SEMUA 77.430 kata di Al-Quran** — persis sama skalanya dengan `ayah_words` proyek ini (77.429 baris, beda 1 kemungkinan cuma soal konvensi hitung). Datanya diekspor ulang dalam bentuk lebih siap-pakai oleh **QUL / Tarteel AI** ([qul.tarteel.ai/resources/morphology/76](https://qul.tarteel.ai/resources/morphology/76)) sebagai **database SQLite** dengan 2 tabel:

- `roots` — kolom: `id`, `arabic_trilateral` (huruf root, format sama dengan `root_words.root_ar` yang sudah ada di proyek ini), `english_trilateral` (transliterasi), `words_count`, `uniq_words_count`.
- `word_roots` — kolom: `root_id`, `word_location` (format `"surah:ayat:posisi_kata"`, misal `"2:3:5"`) — **format ini cocok 1:1 dengan skema `ayahs`+`ayah_words` yang sudah ada** (surah_id+ayah_number → ayah_id, position sudah ada per baris `ayah_words`). Import-nya jadi murni pencocokan skema, bukan riset NLP.

Alternatif cadangan (kalau QUL ternyata tidak cocok/bermasalah lisensinya): fork GitHub [mustafa0x/quran-morphology](https://github.com/mustafa0x/quran-morphology) — versi perbaikan dari Quranic Arabic Corpus v0.4 yang sama, format teks/JSON, root ikut ditag (tapi tidak ada file LICENSE eksplisit di repo-nya — perlu dicek/ditanya ke pemilik repo sebelum dipakai kalau QUL gagal).

**Implikasi penting yang perlu disadari user sebelum lanjut:**

1. **Root impor TIDAK datang dengan arti/meaning (ID/EN)** — tabel `roots` dari QUL cuma punya huruf root + transliterasi, BUKAN terjemahan makna seperti kolom `meaning_ar`/`meaning_en`/`meaning_id` yang sudah dikurasi manual untuk 160 root yang ada sekarang. Jadi dari ~1.700 root, cuma 160 yang akan tampil dengan arti lengkap di UI; sisanya (~1.540) akan tampil root+transliterasi SAJA sampai dikurasi manual (bisa bertahap, sama seperti pola sinonim/relasi selama ini) — **kecuali user mau sumber data arti root tambahan juga (itu pencarian terpisah lagi)**.
2. **Belum saya verifikasi izin lisensi/pemakaian ulang** datanya secara resmi (halaman QUL menyebut ada "Terms of use" tapi saya belum baca detail lengkapnya) — sebelum data ini benar-benar diimpor & didistribusikan sebagai bagian dari `data/smartquran.db` proyek ini, perlu dicek dulu apakah ini free-to-use/reuse untuk app seperti ini (kemungkinan besar aman untuk proyek edukasi/pribadi non-komersial, tapi saya tidak mau asumsi sepihak).
3. Import `word_roots` juga otomatis BISA menggantikan cara `root_ayah_map` dihitung — bukan lagi lewat tebakan morfologi (`search_by_root()`, yang sifatnya heuristik/subsequence matching), tapi dari **tag akurat langsung dari korpus** untuk root manapun yang datanya ter-import. Ini bonus: `root_ayah_map` jadi lebih akurat dari fitur root-search yang sudah ada sekarang (yang tetap heuristik, tidak diubah — di luar scope dokumen ini).

**Rekomendasi saya (direvisi)**: lanjutkan dengan cakupan luas sesuai pilihan user, TAPI sebagai **Fase 0 terpisah** sebelum fase-fase teknis lain di §7 — karena ini melibatkan keputusan sourcing data eksternal (bukan cuma kode), sebaiknya user konfirmasi dulu: (a) OK pakai sumber QUL/Tarteel di atas, (b) OK dengan gap "1.540 root tanpa arti dulu", (c) saya boleh lanjut cek lisensi lebih detail & mulai proses download+import sebagai langkah kode pertama begitu ini di-ACC.

### 5b. Render tree: sekaligus penuh, atau bertahap per klik?
Kalau tree di-precompute & dirender PENUH sekaligus (semua level, semua cabang), ini berisiko meledak kombinatorial (root populer seperti علم/1189 ayat bisa punya puluhan cabang di tiap level, ×level berikutnya, dst). **Rekomendasi saya: lazy/on-demand** — tiap node di-expand cuma saat diklik (fetch level berikutnya via API saat itu juga), PERSIS seperti alur narasi user sendiri di simulasi (user pilih KD2 dulu, baru muncul next options — bukan semua level muncul sekaligus di awal).

### 5c. Tempat menampilkan tree: di dalam modal Info Kata yang sudah ada, atau halaman/modal baru yang lebih lega?
Modal Info Kata sekarang sudah cukup padat (lebar 680px, sudah dioptimalkan untuk kata turunan). Tree berjenjang + breadcrumb path (KD1 › KD2 › KD3...) + daftar kandidat tiap level butuh ruang lebih. **Rekomendasi saya**: tombol "Advance" buka modal/halaman BARU yang lebih besar khusus untuk tree explorer ini, modal Info Kata yang sekarang tidak disentuh sama sekali (sesuai instruksi eksplisit user).

---

## 6. Proposal Arsitektur

### 6a. Skema data baru (direvisi untuk sumber data QUL, §5a)

```sql
-- root_words yang sudah ada DIPERLUAS (bukan diganti): tambah kolom
-- penanda sumber, supaya UI tahu mana yang punya arti terkurasi vs
-- baru root+transliterasi hasil impor.
ALTER TABLE root_words ADD COLUMN source TEXT NOT NULL DEFAULT 'curated';
-- source = 'curated' (160 baris lama, ada meaning_ar/en/id)
-- source = 'imported' (baris baru dari QUL, meaning_* boleh NULL)

CREATE TABLE IF NOT EXISTS root_ayah_map (
    root_id INTEGER NOT NULL REFERENCES root_words(id),
    ayah_id INTEGER NOT NULL REFERENCES ayahs(id),
    PRIMARY KEY (root_id, ayah_id)
);
CREATE INDEX IF NOT EXISTS idx_root_ayah_map_ayah ON root_ayah_map(ayah_id);
```

Diisi via seeder baru (`data/build_root_ayah_map.php`, bagian dari `run_install.php`, jalan SETELAH `ayah_words` ter-seed), dua sumber:

1. **Impor dari file QUL** (`data/quran-roots-source.sqlite` atau `.json` hasil convert, di-commit ke repo seperti data seed lain yang sudah ada — bukan fetch live tiap install, matching pola `seed_quran_full.sql`/`seed_words_full.sql` yang sudah ada): baca tabel `roots` → upsert ke `root_words` (match by `arabic_trilateral` = `root_ar` ternormalisasi; yang sudah ada di 160 root dipertahankan meaning-nya, root baru masuk dengan `source='imported'`, meaning NULL). Baca `word_roots` → parse `word_location` (`"S:A:W"`) → resolve ke `ayah_id` (via `ayahs.surah_id`+`ayah_number`) → `INSERT OR IGNORE` ke `root_ayah_map`.
2. **Fallback heuristik** (opsional, untuk root manapun yang TIDAK ada di data QUL tapi ada di `root_words` versi lama): tetap pakai `search_by_root()` yang sudah ada seperti draf awal, supaya 160 root kurasi lama tidak kehilangan cakupan kalau ada mismatch nama root.

Estimasi ukuran (direvisi dari ~29rb ke skala penuh): sampai ~77.430 pasang (root_id, ayah_id) mentah dari `word_roots`, tapi setelah dedup per (root,ayah) — karena 1 ayat sering punya >1 kata dari root yang sama — realistis di kisaran **35.000–55.000 baris unik**. Tetap kecil untuk SQLite, tidak ada masalah performa.

### 6b. Algoritma umum (generalisasi dari kasus k=1 yang sudah ada)

Diberikan himpunan root terpilih `S = {r1, r2, ..., rk}` (k≥1):

**Langkah 1 — cari ayat yang match SEMUANYA:**
```sql
SELECT ayah_id FROM root_ayah_map
WHERE root_id IN (r1,...,rk)
GROUP BY ayah_id
HAVING COUNT(DISTINCT root_id) = k
```
→ hasil disebut `A(S)`.

**Langkah 2 — cari kandidat level berikutnya (root LAIN yang muncul di A(S)):**
```sql
SELECT root_id, COUNT(*) AS cnt
FROM root_ayah_map
WHERE ayah_id IN (A(S)) AND root_id NOT IN (r1,...,rk)
GROUP BY root_id
ORDER BY cnt DESC
```

**Langkah 3 — cek opsi "ONLY" (ayat di A(S) yang tidak punya root lain sama sekali):**
```sql
SELECT COUNT(*) FROM (
    SELECT ayah_id FROM root_ayah_map
    WHERE ayah_id IN (A(S))
    GROUP BY ayah_id
    HAVING COUNT(*) = k
)
```

**Langkah 4 — kondisi berhenti**: kalau hasil Langkah 2 kosong (tidak ada root lain sama sekali di A(S)) → tidak usah tampilkan picker, langsung anggap sebagai leaf dan tampilkan `A(S)` sebagai daftar ayat. Ini termasuk kasus di mana Langkah 3 = 100% dari A(S) (semua ayat yang tersisa persis kombinasi S, tidak ada yang bisa didalami lagi).

k=1 (S = {KD1} saja) = **PERSIS** query yang sudah dipakai fitur "Sering Muncul Bersama" Level-1 sekarang — jadi endpoint baru ini adalah generalisasi natural, bukan sistem paralel yang terpisah dari yang sudah ada.

### 6c. API baru
`api/word_assoc_tree.php?roots[]=رزق&roots[]=نعم&...`
→ balas JSON: `{ "roots": [...S...], "ayah_count": N, "only_count": M, "children": [{root_ar, meaning_id, meaning_en, count}, ...] }`

Satu request = satu level (lazy). Kalau `children` kosong, frontend tahu harus render tombol "Tampilkan N Ayat" alih-alih daftar kandidat.

### 6d. Mode pencarian baru untuk hasil akhir
`pages/search.php?mode=assoc&roots[]=...` — query `A(S)` yang sama, lalu reuse `render_result_texts()`/highlighting yang SUDAH ADA (highlight semua root terpilih sekaligus). Pola ini persis mengikuti `mode=word` yang sudah dibangun di v1.01.Alpha.014.

### 6e. UI/UX
Tombol "🌳 Advance" baru di modal Info Kata (di sebelah tombol "Cari Kata Dasar" yang sudah ada) → buka modal/halaman baru "Penjelajah Asosiasi": breadcrumb path di atas (`رزق › نعم × `, tiap segmen bisa diklik untuk mundur/reset ke level itu), lalu daftar kandidat level saat ini (klik → expand ke level berikutnya, fetch API), plus 1 opsi "✅ Hanya N kata ini (tampilkan M ayat)" kalau `only_count>0`, plus tombol "📖 Tampilkan Semua Ayat Level Ini" yang langsung ke `mode=assoc`.

---

## 7. Rencana Bertahap (kalau disetujui)

0. ✅ **DONE (2026-08-24)** — Sourcing & impor data root + tool Kata Kurator AI. Detail lengkap: §9.
1. `api/word_assoc_tree.php` (generalisasi query k=1..n, §6b) — **belum dikerjakan**.
2. `pages/search.php` mode `assoc` (filter ayat multi-root) + highlight multi-root — **belum dikerjakan**.
3. Frontend: tombol "Advance" di Info Kata + modal/halaman tree explorer baru (breadcrumb, expand-on-click, opsi ONLY) — **belum dikerjakan**.
4. i18n keys baru (5 bahasa) untuk label-label tree explorer — **belum dikerjakan**.
5. Testing: Playwright — cek path drill-down 2-3 level dalam, cek breadcrumb back-navigation, cek kondisi ONLY & kondisi auto-terminal, cek performa (root sangat populer), cek tampilan root tanpa meaning — **belum dikerjakan**.
6. Update `backlog.md` (entry baru), commit+push, deploy.

**Langkah 1-5 TIDAK akan dieksekusi sampai user memberi lampu hijau eksplisit**, sesuai instruksi awal — Langkah 0 sudah dieksekusi karena user secara eksplisit meminta pembuatan Fase 0 (sourcing data) dan tool Kata Kurator di permintaan berikutnya (2026-08-24).

---

## 8. Risiko & Catatan Terbuka

- **Lisensi/Terms of Use data QUL belum diverifikasi detail** (lihat §5a poin 2) — WAJIB dicek sebelum data di-commit & didistribusikan sebagai bagian `data/smartquran.db`.
- **Gap arti (meaning) untuk ~1.540 root hasil impor** (§5a poin 1) — root-root ini akan tampil root Arab + transliterasi saja di UI sampai dikurasi manual bertahap. Bukan blocker teknis, tapi pengalaman user untuk root yang belum ada artinya akan terasa kurang lengkap dibanding 160 root kurasi lama.
- Root sangat umum bisa menghasilkan cabang level-1 yang lebar (puluhan+ kandidat, makin lebar dengan cakupan 1.700 root vs 160) — perlu pembatasan (top-N per level, sama seperti Level-1 sekarang dibatasi 10) supaya UI tetap terpakai.
- Tabel `root_ayah_map` perlu di-refresh tiap kali `root_words`/data impor berubah (rebuild seeder), sama seperti tabel lain yang di-rebuild penuh tiap `run_install.php`.
- Reuse `search_by_root()` yang sudah ada (heuristik) hanya sebagai FALLBACK untuk root lama yang mismatch — bukan diganti sepenuhnya; fitur root-search yang sudah ada (halaman 🔤 Root Search, Word Info non-tree) TIDAK diubah oleh dokumen ini, tetap heuristik seperti sekarang.

---

## 9. Implementasi Fase 0 + Kata Kurator (SELESAI, 2026-08-24)

### 9a. Sumber data — pivot dari QUL ke GitHub mustafa0x/quran-morphology

Tombol download QUL/Tarteel (§5a) ternyata **JS-rendered dan broken** (`href="#_"`, tidak bisa diambil otomatis). Dicek alternatif yang sudah disebut di §5a: fork GitHub **[mustafa0x/quran-morphology](https://github.com/mustafa0x/quran-morphology)** — file teks langsung (`quran-morphology.txt`, tab-delimited `surah:ayah:word:segment`, tag `ROOT:xxx` per segmen), fetchable langsung via `raw.githubusercontent.com`, tanpa email-gate/JS. README repo ini eksplisit bilang ini fork dari **Quranic Arabic Corpus Morphology v0.4** (GPLv3, sama seperti sumber asli — lisensi dikonfirmasi via `corpus.quran.com/license.jsp`: GPLv3, mencakup data morfologi/root, bukan cuma kode situs).

**Kesimpulan lisensi** (user sudah approve "OK" untuk lanjut sebelum riset ini): repo `CoreAI-CPanel` di GitHub **private** (dikonfirmasi: API GitHub unauthenticated return 404 untuk repo ini — perilaku khas repo private) → commit data turunan GPLv3 di sini BUKAN "conveying ke publik" dalam pengertian GPL. Bahkan situs live di CPanel pun kemungkinan besar aman (GPLv3 — beda dengan AGPLv3 — tidak mewajibkan source-sharing untuk sekadar menjalankan sebagai network service, hanya untuk *conveying*/distribusi salinan file). Atribusi ditulis di header `data/seed_word_roots.sql` sebagai praktik baik, bukan karena wajib secara ketat pada kondisi saat ini.

### 9b. Yang diimplementasikan

| Komponen | File | Keterangan |
|---|---|---|
| Kolom sumber root | `data/schema.sql` | `root_words.source` (`'curated'` default \| `'imported'`) |
| Generator seed | `data/build_word_roots_import.py` | Python (matching `build_seed_words.py`), fetch morphology.txt + chapter offsets (`api.quran.com/v4`), hitung `ayah_id` **deterministik** (`offset[surah]+ayah`, diverifikasi cocok 100% dengan DB nyata) — jadi TIDAK perlu tabel baru terpisah/PHP importer seperti draf §6a awal |
| Seed hasil generate | `data/seed_word_roots.sql` (4.2 MB, tidak masuk repo mentah — hanya hasil olahan) | `INSERT OR IGNORE` roots + `ayah_root_words` (root_id via subquery `WHERE root_ar=...`, pola sama seperti `seed_root_relations.sql`) |
| **Reuse tabel existing** | `ayah_root_words` (bukan tabel baru `root_ayah_map` seperti draf §6a) | Ternyata tabel ini SUDAH ADA di schema (dibuat awal proyek) tapi nyaris kosong (7 baris/5 root — sample lama). Dipakai ulang, bonus: fitur `get_ayah_roots()`/root-chip di halaman surat yang tadinya nyaris mati jadi berfungsi penuh |
| Wiring installer | `install.php` | 2 step opsional baru: "Imported root-ayah map" + "AI-curated root meanings" (lihat 9c), mengikuti pola optional-file yang sudah ada persis |
| **Tool Kata Kurator** | `data/words-kurator-by-ai/` (`index.html`, `app.js`, `proxy.php`, `list_roots.php`, `apply.php`, `status.php`) | Lihat §9c |
| Config secrets | `config.keys.json.example` (root project) | Skema multi-provider sama persis `src/ai` (`providers.{OLLAMA_CLOUD,OPENROUTER,OPENAI,OLLAMA_LOCAL}.apiKey`) — **user isi sendiri** `config.keys.json` (gitignored), tidak disalin dari `src/ai` |
| Exclude dari CPanel | `deploy-cpanel.bat` | `data/words-kurator-by-ai/` ditambah ke filter skip-upload — tool lokal-only sesuai keputusan user |
| Exclude dari git | `.gitignore` | `data/.quran-morphology-source.txt` (cache mentah 6.3MB, hasil-download, bukan artefak final) |

**Angka nyata hasil import**: 77.429 kata diparse (cocok hampir persis dengan `ayah_words` proyek ini — 77.429 vs 77.430 versi upstream, beda konvensi hitung minor), **50.268 kata bertag root** (sisanya partikel/kata ganti tanpa root — wajar), **1.651 root distinct** ditemukan → 1.495 benar-benar baru (**source='imported'**), 156 cocok persis dengan 160 root kurasi yang sudah ada (4 root kurasi tidak ke-tag otomatis oleh korpus ini — kemungkinan varian ejaan, tidak fatal). `ayah_root_words` naik dari 7 baris (5 root) → **50.272 baris (1.652 root)**, mencakup 6.214 dari 6.236 ayat (22 ayat tanpa kata ber-root sama sekali — wajar, ayat sangat pendek/partikel). Waktu apply ke DB lokal: **0,4 detik**.

### 9c. Tool Kata Kurator (AI)

`http://localhost:8885/data/words-kurator-by-ai/index.html` — dibangun mengikuti pola `src/ai/proxy.php` PERSIS (server-side proxy baca key dari `config.keys.json`, browser tidak pernah lihat API key — krusial karena Ollama Cloud dkk tidak kirim header CORS, direct fetch dari browser pasti gagal juga selain soal keamanan key):

1. **`list_roots.php`** — daftar root `source='imported'` yang `meaning_id` masih kosong, dengan **konteks nyata** (bentuk kata turunan + sample ayat+terjemahan Indonesia) untuk grounding prompt — bukan tanya AI "apa arti root X" secara vakum.
2. **Prompt** (`app.js`, sistem+per-root) — minta AI balas JSON `{meaning_ar, meaning_en, meaning_id}` berdasarkan konteks kemunculan nyata di atas. Preview prompt bisa dilihat langsung di UI (`<details>` collapsible).
3. **`proxy.php`** — generic AI reverse-proxy, dukung 4 provider (OLLAMA_CLOUD/OPENROUTER/OPENAI/OLLAMA_LOCAL), auto-pilih shape request sesuai provider (Ollama `/api/chat` vs OpenAI `/responses` vs OpenAI-compatible `/chat/completions`).
4. UI: tabel batch (5/10/20/50 root sekaligus) dengan field arti **editable** (textarea per bahasa) — sesuai pilihan user "langsung terima, saya cek belakangan", generate TIDAK menunggu approval per-baris, tapi hasil tetap bisa diedit di tabel sebelum/sesudah di-apply.
5. **`apply.php`** — "follow-up script" yang diminta user: UPDATE langsung ke `root_words` di DB lokal (efek instan, tanpa reinstall) **DAN** append ke `data/seed_roots_ai_curated.sql` (idempotent, di-commit ke git, otomatis dipakai lagi oleh `install.php`/`run_install.php` berikutnya — lokal maupun setelah `deploy-cpanel.bat`).

Diverifikasi Playwright (`localhost:8885`, tanpa API key asli — belum ada `config.keys.json` di sesi ini): halaman render benar, banner peringatan "belum ada API key" tampil, tombol Generate ter-disable dengan benar, "Muat Batch" berhasil menarik 10 root teratas (diurut frekuensi — root الله/أله jadi contoh pertama, freq 2851) lengkap dengan konteks ayat+terjemahan, nol error console. Pipeline `apply.php` diuji terpisah (payload manual, bukan lewat AI) — UPDATE ke DB lokal ✓, file `seed_roots_ai_curated.sql` ✓ (lalu data uji dibersihkan lagi).

### 9d. Regresi ditemukan & diperbaiki sendiri (bukan dari laporan user)

Import 1.495 root baru TANPA arti ternyata **mencemari 2 fitur yang sudah live**: root_words dengan `meaning_id`/`meaning_en` NULL ikut lolos ke query yang sebelumnya cuma melihat 160 root berarti lengkap.

1. **`api/word_info.php`** (popup Info Kata) — klik kata "الله" (kata paling sering di Al-Quran!) balik 2 root dengan makna kosong, padahal SEBELUM import balik 0 hasil (kosong tapi bersih). Klik "الرحمن" nambah 1 root kosong nimbrung di antara 2 root kurasi yang benar.
2. **`get_ayah_roots()`** (root-chip di halaman baca surat, `pages/surah.php`) — LEBIH parah karena dipakai di path yang jauh lebih sering dilihat (tiap ayat, tiap halaman surat): chip `✓ ريب — ` (strip kosong tanpa arti) bertebaran, ditemukan lewat curl langsung ke `index.php?page=surah&id=2` (bukan cuma baca kode).

**Fix** (kedua tempat, pola sama): filter `source != 'imported' OR (meaning_id IS NOT NULL AND meaning_id != '')` — root impor tanpa arti tetap ada di DB & tetap kelihatan penuh di tool Kata Kurator, tapi disembunyikan dari 2 fitur user-facing ini sampai dikurasi. Diverifikasi: "الله" balik ke 0 hasil (baseline lama, bukan regresi baru), "الرحمن" balik 2 root bersih lagi, chip di surah 2 naik dari 34 → **60 chip bersih** (semuanya sudah berarti — net **positif**, bukan cuma "tidak lebih buruk"), rezeki/word-mode/associations semua tetap 100% berfungsi seperti v1.01.Alpha.014. Full smoke test 5 halaman inti + Playwright modal Info Kata: nol error.

---

## 10. Fase 1+: Algoritma, API, Mode Pencarian, UI Drill-Down (SELESAI, 2026-08-25)

Sebelum implementasi, 1.495 root hasil impor (§9) dipastikan **sudah terkurasi 100%** oleh sesi lain (`data/seed_roots_ai_curated.sql`, 4310 baris, commit "Kurasi semua kata dasar bahasa Arab") — dicek langsung ke DB, 0 yang masih kosong.

### 10a. Desain final — beda dari draf §1-§8

Diskusi lanjutan dengan user (2026-08-25) mengubah beberapa keputusan dari draf awal:

- **BUKAN halaman/modal baru terpisah** ("Advance" button) — user ingin fitur ini jadi **bagian dari modal Info Kata yang sudah ada**: section "Sering Muncul Bersama" sendiri yang jadi tree-nya. Klik kata di situ → modal yang SAMA di-render ulang untuk root berikutnya (rekursif), bukan navigasi ke tempat lain.
- **"Kata Turunan" tetap berbasis bentuk kata** (tidak diubah) — TAPI **"Sering Muncul Bersama" diupgrade jadi berbasis ROOT** (grup+gabung, mis. "أمن (Aman, iman) ×15" bukan daftar bentuk kata mentah terpisah), supaya konsisten dengan algoritma AND antar-level & manfaatkan 1.655 root+arti.
- **Filter/highlight ayat akhir tetap berbasis KATA TURUNAN spesifik, bukan root** — dipecahkan dengan wawasan: `ayah_root_words` (dari Fase 0) sudah menyimpan `word_form` SPESIFIK per (root, ayat), jadi query AND-nya tetap di level root (efisien, pakai index), tapi highlight/tampilan hasil akhir pakai kata spesifik yang tersimpan di situ — dapat keduanya sekaligus tanpa kompromi.
- **Dua layout UI, keduanya diimplementasikan, bisa di-toggle**: breadcrumb (ganti-di-tempat) DAN tree menjorok (semua level tetap kelihatan) — bukan pilih salah satu.

### 10b. Backend

| Komponen | File | Keterangan |
|---|---|---|
| Algoritma umum k=1..n | `includes/functions.php` → `root_co_occurrence(array $rootIds, int $childLimit)` | Generalisasi persis §6b: ayat yang match SEMUA root terpilih (`GROUP BY ayah_id HAVING COUNT(DISTINCT root_word_id) = k`), kandidat level berikutnya (root lain di ayat-ayat itu, diurut frekuensi + 1 contoh kata), plus `only_ayah_ids`/`only_count` (ayat yang PERSIS kombinasi ini, tanpa root lain) |
| Index baru | `data/schema.sql` | `idx_arw_root`/`idx_arw_ayah` di `ayah_root_words` — query di atas filter berulang kali by root-set dan by ayah-set |
| API rekursif | `api/word_info.php` | Endpoint yang sama dipakai 2 cara: `?word=X` (klik segar, seperti sebelumnya) ATAU `?context[]=13&context[]=207` (drill-down, root TERAKHIR di path = fokus). Field baru per root: `root_id`, `path` (breadcrumb info), `ayah_count`, `only_count`; `associations` sekarang **root-grouped** (bukan bentuk kata mentah) |
| Mode pencarian akhir | `pages/search.php` → `mode=assoc&roots[]=..&roots[]=..` (+ `&only=1` opsional) | Ayat yang match AND semua root (via `root_co_occurrence`), highlight PERSIS bentuk kata turunan yang tercatat di `ayah_root_words` untuk ayat itu (fungsi baru `render_assoc_result_texts()`) — bukan highlight seluruh morfologi root |

### 10c. Frontend (`assets/js/app.js`)

State per-card (`ASSOC_STATE`, keyed per root yang matched — 1 kata bisa match sampai 3 root, tiap kartu independent): `{layout: 'breadcrumb'|'tree', levels: [{data: <api response>}, ...]}`. Level 0 = hasil fetch awal (tidak perlu request tambahan). Klik chip asosiasi → `assocDrill()` (truncate ke level yang diklik, fetch `context[]=...`, push level baru). Klik breadcrumb segment → truncate tanpa fetch (data sudah ke-cache). Toggle layout → re-render `ASSOC_STATE` yang sama dengan 2 fungsi render berbeda, tidak ada re-fetch. Semua event pakai delegation (didaftarkan sekali di `initWordInfo()`), jadi tetap jalan meskipun section di-`innerHTML`-replace berkali-kali.

Tiap level punya tombol "📖 Tampilkan Ayat (N)" (selalu terlihat, bukan cuma pas mentok — analisis §5b/8 menunjukkan `ayah_count` sering tetap 1 sampai belasan level drill karena satu ayat bisa punya banyak root, jadi memaksa user sampai mentok akan sangat dalam) + "✅ Hanya kombinasi ini (M)" kalau `only_count > 0`.

### 10d. Verifikasi

- **Bug ditemukan sendiri (bukan laporan user)**: `HAVING COUNT(...) = ?` dengan parameter TER-BIND silent-fail (0 baris) di SQLite/PDO meski literal integer yang identik bekerja normal — dibuktikan lewat isolasi 4 varian query. Fix: inline `$k` langsung ke SQL (aman, itu `count()` internal, bukan input user), bukan bind sebagai parameter.
- Algoritma diuji langsung: k=1 رزق → 109 ayat, 10 kandidat (أله×96, قول×40, ربب×26, ...) — 1.4ms. k=1 root TERSIBUK di seluruh Quran (أله, 2851 kemunculan) → 1879 ayat, tetap **22.9ms** — jauh dari masalah performa. Drill manual 8 level pada root langka (أثث, freq 2) mengonfirmasi kondisi terminal REALISTIS butuh banyak level (karena 1 ayat bisa >15 root berbeda) — memvalidasi keputusan "tombol Tampilkan Ayat selalu ada", bukan cuma di leaf.
- End-to-end Playwright: klik kata "rezeki" → level 1 (breadcrumb "رزق", 10 chip root+arti) → drill ke level 2 (أله, breadcrumb "رزق › أله") → drill ke level 3 (قول) → klik breadcrumb kembali ke level 1 (truncate berhasil, tanpa fetch ulang) → toggle ke tree layout (semua level ke-render terindentasi, state tetap sama) → klik "Tampilkan Ayat" → `mode=assoc&roots[]=13` → 109 hasil, 224 `<mark>` — dicek manual: SEMUA mark adalah varian harakat dari بentuk kata رزق/الله yang SPESIFIK per ayat (mis. رِزْقًۭا, لِلَّه, رَزَقْنَٰكُم, يَرْزُق — bukan sekadar "root ini ada"), plus highlight terjemahan (Allah/rezeki/makanan/memberi) ikut jalan. Nol error console di semua langkah.
- Smoke test regresi 5 halaman inti + halaman baru sesi lain (auth/kurator/profile/ai_chat) — semua HTTP 200.
- **Bug lain ditemukan & diperbaiki sendiri** (bukan bagian dari fitur ini, ketemu saat commit-sweep): `pages/auth.php` (dari commit sesi lain, "Add authentication & admin page/kurator") punya typo `?=>` alih-alih `?>` di baris 28 — parse error fatal, akan 500 di setiap akses `?page=auth` tanpa action. Diperbaiki (1 karakter), `php -l` bersih.

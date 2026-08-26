# FACTA (dulu SmartQuran) — Analisis & Implementasi v1.01.Alpha.023

**Status dokumen**: **SELESAI SEPENUHNYA** (2026-08-26). Semua keputusan di §2c/§2d/§3 sudah dijawab user dan diimplementasikan — lihat §4 untuk log implementasi lengkap.

---

## 1. Reproduksi Masalah "lahir"

Dicek langsung ke 3 jalur pencarian yang ada:

### 1a. `search_ayahs('lahir', 'id')` — pencarian teks utama (FTS5, mode=text)

Ini kemungkinan besar jalur yang dipakai user saat melapor. Hasil: **4 ayat**, dan ternyata BUKAN bug pencocokan — kata "lahir" di teks terjemahan Kemenag memang dipakai literal dalam 2 makna berbeda:

| Ayat | Konteks kalimat | Makna |
|---|---|---|
| 30:7 | "Mereka hanya mengetahui **yang lahir** (saja) dari kehidupan dunia" | **tampak/nyata** (lawan kata "batin") |
| 11:71 | "...berita gembira tentang (**kelahiran**) Ishak..." | **melahirkan/anak** |
| 31:20 | "...menyempurnakan untukmu nikmat-Nya **lahir dan batin**..." | **tampak/nyata** |
| 18:22 | "...kecuali pertengkaran **lahir** saja..." | **tampak/nyata** |

3 dari 4 hasil sebenarnya bermakna "tampak/nyata" (lawan "batin"), cuma 1 yang benar-benar tentang "melahirkan". Bukan salah cocok teknis — kata "lahir" di Bahasa Indonesia memang **polisemi asli** (satu ejaan, dua makna tidak berhubungan), dan pencarian teks literal tidak (dan tidak bisa) membedakan maksud user.

### 1b. `search_stem_derived('lahir', 'id')` — pencarian kata turunan (checkbox "kata dasar & turunannya")

36 hasil, SEMUANYA varian dari akar **ولد** (lahirkan/melahirkan/dilahirkan) — jalur ini ternyata TIDAK tercampur, karena stemmer ID mencocokkan ke `translation_id` PER KATA (bukan ke kalimat penuh), dan kata-kata turunan "tampak/nyata" di `ayah_words` ternyata tidak literal mengandung stem "lahir" (translasi per-katanya pakai kata lain).

### 1c. `api/word_info.php` (popup Info Kata, klik kata) — pencocokan ke `root_words.meaning_id`

Ini jalur PALING rawan campur — kata "lahir" (dan bentukan "lahiriah") muncul di **teks deskripsi makna** 8 root SEKALIGUS, termasuk yang konsepnya sama sekali tidak berhubungan dengan "melahirkan" (dipakai cuma sebagai kata dalam kalimat definisi):

| Root | Cuplikan makna | Relevan dgn "lahir=born"? |
|---|---|---|
| ولد | "Anak, lahir" | ✅ Ya, memang ini root-nya |
| كمه | "Buta sejak **lahir**..." | ✅ Ya (pakai kata lahir sbg konteks) |
| مخض | "...saat **melahirkan**." | ✅ Ya |
| وأد | "...setelah **lahir**..." | ✅ Ya |
| وضع | "**Melahirkan** bayi dari rahim..." | ✅ Ya |
| أمم | "...sehingga **melahirkan** kata ummah..." | ❌ Tidak — "melahirkan" di sini kiasan ("menghasilkan"), root aslinya tentang "induk/kepemimpinan" |
| بكر | "...yang belum pernah **melahirkan**..." | ⚠️ Konteksnya "perawan", bukan tentang proses lahir itu sendiri |
| سوم / مسس | "...sifat **lahiriah**..." / "...secara **lahiriah**..." | ❌ Tidak — ini pakai makna "tampak", bukan "melahirkan" |

Root **ظهر** (yang disebut user) sendiri ternyata TIDAK muncul di daftar ini — meaning_id-nya pakai kata "tampak"/"nyata", bukan literal "lahir". Jadi campur-baurnya bukan ظهر vs ولد seperti dugaan awal user, tapi lebih luas: kata "lahir" nyasar ke banyak root yang sekadar memakainya sebagai kata dalam kalimat penjelasan (termasuk makna kiasan "melahirkan konsep").

**Kesimpulan akar masalah**: ada 2 sumber campur-aduk yang beda:
1. **Polisemi bahasa Indonesia asli** (1a) — "lahir" = tampak/nyata ATAU melahirkan, dua konsep beda tapi 1 ejaan. Pencarian teks literal tidak salah, cuma tidak bisa baca pikiran user.
2. **False-positive substring dalam kalimat definisi** (1c) — kata "lahir"/"melahirkan" dipakai di kalimat PENJELASAN root yang topiknya beda (mis. أمم "melahirkan konsep" secara kiasan). Ini murni masalah pencocokan kata-dalam-kalimat, bisa diperbaiki independen dari soal disambiguasi makna.

---

## 2. Kebutuhan "Pencarian AND" — `lahir(ولد)` dan `hari(يوم)`

### 2a. Sintaks yang diusulkan user

Contoh dari raw item: `lahir(ولد)` dan `hari (يوم)` — pola "**kata(root)**": kata Indonesia/Inggris diikuti akar Arab spesifik dalam kurung, untuk **disambiguasi + jadi unit pencarian AND**.

### 2b. Kabar baik: backend-nya SUDAH ADA

Fitur tree co-occurrence (v1.01.Alpha.018) sudah membangun persis mesin yang dibutuhkan di sini:

- `root_co_occurrence(array $rootIds)` di `functions.php` — generalisasi k=1..n, ayat yang match SEMUA root terpilih.
- `pages/search.php?mode=assoc&roots[]=X&roots[]=Y` — sudah bisa render ayat hasil AND dengan highlight PERSIS ke bentuk kata turunan (bukan seluruh root), pakai `render_assoc_result_texts()`.

Jadi "lahir(ولد) DAN hari(يوم)" secara teknis = `mode=assoc&roots[]=<id ولد>&roots[]=<id يوم>` — **infrastruktur query + rendering-nya tidak perlu dibuat ulang**, cuma butuh (a) cara resolve "lahir"+"(ولد)" jadi root_id, dan (b) UI/tempat user mengetikkannya.

### 2c. Opsi UI (perlu keputusan user)

**Opsi A — Sintaks inline di kotak cari utama**
User ketik langsung `lahir(ولد) hari(يوم)` di search box biasa. Backend parse pola `kata(root)` pakai regex, tiap pasangan di-resolve ke root_id (match `root_ar` setelah `ar_normalize()`), lalu redirect ke `mode=assoc`.
- 👍 Tidak ada UI baru, satu kotak untuk semua.
- 👎 User harus SUDAH TAHU akar Arab yang tepat sebelum mengetik — kalau tidak tahu ولد itu apa, fitur ini tidak membantu sama sekali. Juga rawan salah ketik/format.

**Opsi B — Builder AND-search interaktif (Recommended)**
Mode pencarian baru, mis. `mode=and`: user ketik kata Indonesia/Inggris di kotak → dropdown typeahead muncul (PERSIS pola yang baru dibangun untuk "Muncul Bersama" — reuse langsung: cocokkan ke `root_words.meaning_id`/`meaning_en`, tampilkan `root_ar` + arti singkat) → user PILIH root yang dimaksud dari dropdown → jadi "chip" term pertama → ulangi untuk kata ke-2, dst → tombol "Cari (AND)" → render via `mode=assoc`.
- 👍 User tidak perlu tahu Arab sama sekali — tinggal ketik Indonesia, sistem yang kasih pilihan sense yang mana. Reuse pola UI yang sudah terbukti jalan (typeahead dari v1.01.Alpha.021).
- 👎 Perlu halaman/mode baru + sedikit UI tambahan (tapi kecil, karena komponen typeahead-nya sudah ada tinggal dipasang ulang).

**Opsi C — Disambiguasi inline di pencarian teks biasa**
Kalau pencarian teks biasa (mode=text) mendeteksi query cocok ke BANYAK root berbeda (seperti "lahir" → 7-8 root), tampilkan prompt kecil di atas hasil: *"'lahir' cocok dengan beberapa kata dasar: [ولد - Anak, lahir] [كمه - Buta sejak lahir] ... — pilih salah satu untuk mempersempit, atau [+ tambah kata lain untuk AND]"*. Klik salah satu me-refine ke hasil spesifik root itu; ada juga opsi lanjut ke Opsi B untuk menambah term AND.
- 👍 Muncul otomatis pas dibutuhkan (query ambigu), tidak perlu user tahu ada fitur "AND search" duluan.
- 👎 Lebih banyak kerja gabungan (perlu deteksi ambiguitas + prompt + hook ke builder).

**Rekomendasi saya**: **Opsi B sebagai fitur utama**, dengan **Opsi C sebagai pemicu/pintu masuk** (link "🔍 Cari lebih spesifik (AND)" muncul di hasil pencarian teks biasa kalau query match >1 root, mengarah ke Opsi B yang sudah pre-filled term pertama). Opsi A saya sarankan TIDAK usah dibuat terpisah — kalau user sudah expert dan hafal akar Arab, mereka bisa pakai flow Opsi B lebih cepat lewat keyboard hijaiyah yang sudah ada di mode root-search.

### 2d. Perbaikan terpisah (independen dari opsi UI di atas)

False-positive di §1c (kata "lahir" nyasar ke root أمم/بكر/سوم/مسس yang topiknya beda) itu masalah TERSENDIRI di `api/word_info.php`'s non-Arabic matching — bisa diperbaiki terpisah, tidak tergantung opsi A/B/C di atas: kandidat perbaikannya cek utuh SATU KATA per match (bukan substring bebas di seluruh kalimat), atau prioritaskan match di AWAL kalimat (biasanya definisi utama), atau — paling simpel — tetap tampilkan tapi urutkan berdasar relevansi (skor lebih tinggi kalau kata muncul sebagai kata PERTAMA/definisi utama, bukan di tengah kalimat penjelasan tambahan). Saya sarankan dikerjakan terpisah dari fitur AND-search di atas kalau user setuju, karena scope-nya beda (bug relevansi vs fitur baru).

---

## 3. Rebranding SmartQuran → FACTA

Raw item: *"rebranding SmartQuran menjadi FACTA (Finding Association in Collection of Text Alquran)"*.

**Ini task terpisah dari §1-2 di atas, dan sengaja saya TIDAK eksekusi dulu** — beberapa hal perlu dipastikan dulu karena scope-nya bisa sangat lebar dan sebagian implikasinya sulit dibatalkan dengan mudah:

- **Cakupan**: hanya nama tampilan (judul halaman, header, footer, `APP_NAME` di `config.php`, sidebar) — TIDAK menyentuh domain live (`aiquran.diasoft.web.id`), nama folder (`src/aiquran`), nama repo GitHub, atau identifier internal (tabel DB, dsb)? Saya asumsikan YA (cuma branding tampilan) kecuali dikoreksi, karena mengubah domain/folder/repo punya konsekuensi teknis jauh lebih besar (DNS, broken link, config path) di luar scope "rebranding tampilan".
- **Tagline**: kepanjangan "Finding Association in Collection of Text Alquran" ditampilkan di UI (mis. subtitle kecil di bawah logo) atau cukup nama "FACTA" saja yang tampil, kepanjangannya cuma referensi/dokumentasi?
- **Timing**: begitu selesai & diverifikasi, langsung deploy ke CPanel (situs publik langsung ganti nama), atau mau di-review dulu di IIS lokal sebelum publish live?

Saya akan tanyakan ini secara eksplisit setelah dokumen ini (lihat pesan berikutnya) sebelum mulai kerjakan.

---

## 4. Implementasi (SELESAI, 2026-08-26)

Keputusan user: **Opsi C** (builder AND + pintu masuk otomatis dari pencarian biasa), **perbaikan relevansi sekalian sekarang**, **rebranding cuma tampilan UI**.

### 4a. Fix relevansi (§2d)

Fungsi baru `match_roots_by_gloss()` di `includes/functions.php`, dipakai ulang di 3 tempat (`api/word_info.php`, `api/root_lookup.php`, prompt disambiguasi di `pages/search.php`) — tidak ada logika duplikat. **Position-aware scoring**: match di awal teks makna (definisi utama) skor jauh lebih tinggi dari match yang terkubur di kalimat penjelasan panjang; match di luar 6 kata pertama **dibuang total**, bukan cuma diturunkan skornya (supaya UI disambiguasi tidak menampilkan root yang jelas-jelas cuma "numpang lewat" di kalimat definisi root lain).

Diverifikasi ulang persis dengan temuan §1c: query "lahir" sekarang cuma balik **5 match relevan** (وضع, ولد, كمه, مخض, وأد — skor 7,6,5,2,2) — أمم dan بكر (yang cuma numpang "melahirkan"/"lahiriah" jauh di kalimat penjelasan) **sudah tidak muncul lagi**. Regresi dicek: "rezeki"→رزق tunggal seperti biasa, "hari"→يوم di posisi pertama dengan skor tertinggi (7).

### 4b. AND-search builder + pintu masuk otomatis (§2c Opsi C)

| Komponen | File | Keterangan |
|---|---|---|
| Endpoint typeahead | `api/root_lookup.php` (baru) | `?q=kata` → daftar root via `match_roots_by_gloss()`, dipakai builder DAN prompt disambiguasi |
| Prompt disambiguasi | `pages/search.php`, blok `$ambiguousRoots` | Muncul otomatis di atas hasil `mode=text` kalau query (non-Arab) cocok ≥2 root — tiap chip klik langsung ke `mode=and&seed=...&pick=<id>` (skip typeahead, root sudah otomatis jadi term pertama); tombol "🔀 Cari lebih spesifik" ke builder kosong dengan query ter-seed |
| Builder | `pages/search.php`, `mode=and` (baru, tab "🔀 Cari AND" di header) | Ketik kata → typeahead (`api/root_lookup.php`) → pilih dari dropdown → jadi chip term (bisa dihapus ✕) → ulangi → tombol "Cari (AND)" |
| Hasil akhir | Redirect ke `mode=assoc&roots[]=..&roots[]=..` | **Tidak ada algoritma/rendering baru** — reuse penuh `root_co_occurrence()` + `render_assoc_result_texts()` dari v1.01.Alpha.018, persis seperti diprediksi di §2b |

Diverifikasi end-to-end Playwright: cari "lahir" → prompt disambiguasi 5 chip (وضع/ولد/كمه/مخض/وأد) → klik وضع → landing `mode=and&seed=lahir&pick=1768` dengan term "وضع" otomatis ter-chip → ketik "hari" → dropdown 8 hasil → pilih يوم → 2 term chip → klik "Cari (AND)" → `mode=assoc&roots[]=1768&roots[]=100` → **3 ayat**, salah satunya (QS 21:47) memang mengandung KEDUA bentuk kata (نَضَعُ dari وضع + لِيَوْمِ dari يوم) — AND-nya benar secara semantik, bukan cuma teknis. Uji hapus term (chip ✕ → tombol Cari ter-disable lagi saat 0 term) juga berfungsi. Nol error console di semua langkah.

### 4c. Rebranding SmartQuran → FACTA (§3, scope UI-only)

- `includes/config.php`: `APP_NAME` = `'FACTA'`.
- `includes/i18n.php`: `'app_name'` di 5 bahasa → `FACTA` (Arab: `فاكتا`, transliterasi — akronim tidak diterjemahkan per-bahasa, mengikuti konvensi umum nama brand).
- `install.php`: judul halaman, `<h1>`, teks instruksi, pesan sukses — semua string tampilan yang tadinya hardcode "SmartQuran".
- `includes/header.php`: title tag diperbaiki dari yang tadinya redundan ("SmartQuran — SmartQuran") jadi `FACTA — Finding Association in Collection of Text Alquran` (kepanjangan akronim ditaruh di tab browser + `<meta name="description">`, bukan elemen visual baru di halaman — jawaban untuk pertanyaan tagline di §3 yang belum sempat ditanyakan eksplisit: saya pilih lokasi low-key/non-intrusive ini sebagai default aman).
- Judul `backlog.md` juga ikut diupdate (`# FACTA — Development Backlog`, dengan catatan nama lama) — perubahan sepele/tanpa risiko teknis, beda dengan domain/folder/repo.
- **TIDAK disentuh** sesuai scope yang dikonfirmasi: domain live (`aiquran.diasoft.web.id`), folder `src/aiquran`, nama repo GitHub, komentar dokumentasi di kode (`/** SmartQuran — X Page */` di ~60 file dibiarkan — itu komentar developer, bukan tampilan).

Diverifikasi: 0 sisa teks "SmartQuran" di halaman manapun yang di-render (dicek via grep hasil HTML + screenshot), smoke test 5 halaman inti nol error.

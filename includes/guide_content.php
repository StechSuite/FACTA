<?php
/**
 * FACTA — Help/Guide content (page=guide).
 *
 * Long-form prose, deliberately kept OUT of $I18N (includes/i18n.php) —
 * that file is for short UI labels, this is paragraphs. Real content in
 * ar/en/id; su/jv intentionally omitted per user decision (2026-08-26):
 * deep technical/algorithm explanations in Sundanese/Javanese would be
 * low-quality machine translation with no established terminology, so
 * guide_text() below falls back to 'id' for those two UI languages —
 * same fallback chain, just applied to prose instead of single words.
 *
 * Structure: ordered list of groups, each a list of sections. Flattened
 * order (via guide_flatten_sections()) drives the sidebar accordion AND
 * the prev/next footer nav in pages/guide.php.
 */

function guide_text(array $map, string $lang): string {
    return $map[$lang] ?? $map['id'] ?? $map['en'] ?? '';
}

function guide_groups(): array {
    return [

        // ============================================================
        [
            'id' => 'mulai',
            'icon' => '🚀',
            'title' => ['ar' => 'البدء', 'en' => 'Getting Started', 'id' => 'Memulai'],
            'sections' => [
                [
                    'id' => 'tentang',
                    'icon' => '📖',
                    'title' => ['ar' => 'ما هي فاكتا؟', 'en' => 'What is FACTA?', 'id' => 'Apa itu FACTA?'],
                    'body' => [
                        'id' => <<<'HTML'
<p><strong>FACTA</strong> adalah singkatan dari <em>Finding Association in Collection of Text Al-Quran</em> — sebuah aplikasi baca &amp; kaji Al-Quran yang, selain fungsi baca/terjemah/tafsir/audio standar, punya satu fitur inti yang membedakannya: <strong>menelusuri hubungan antar-kata dasar (root Arab)</strong> di seluruh 6.236 ayat Al-Quran.</p>
<p>Ide inti FACTA bukan hal baru — berakar dari <strong>tugas akhir</strong> di Jurusan Teknik Informatika (IF), Institut Teknologi Bandung (ITB), diselesaikan sekitar tahun 2004 oleh <strong>Hendi Wibowo (NIM 13599039)</strong>. Versi awal itu ditulis dengan <strong>Java (J2SE)</strong>, antarmuka dibangun dengan <strong>JBuilder</strong>, model bahasanya sederhana disimpan lewat <em>binary serialization</em> dari sebuah <em>matrix of BitSet</em>, dan korpus teksnya berupa file teks biasa. Yang kamu pakai sekarang adalah hasil <strong>rekayasa ulang penuh</strong> — arsitektur dan struktur data disesuaikan untuk masa kini, dibangun dengan bantuan AI — tapi dengan sengaja tetap mempertahankan <strong>logika, algoritma pencarian, dan visi produk</strong> yang sama dari versi 2004 tersebut.</p>
<p>Filosofinya: setiap kata dalam Bahasa Arab Al-Quran berasal dari sebuah <strong>akar kata (root)</strong> 2-4 huruf. Kata-kata yang berbeda bentuk tapi berbagi akar yang sama biasanya berbagi makna dasar yang sama pula. FACTA membangun graf hubungan "kata dasar mana muncul bersama kata dasar mana, di ayat mana" dari seluruh Al-Quran, lalu menyediakan alat untuk menelusuri graf itu secara interaktif — baik untuk mencari ayat berdasarkan kombinasi makna, maupun untuk sekadar memahami satu kata lebih dalam lewat kata-kata dasar terkait, sinonim, dan antonimnya.</p>
<p>Panduan ini menjelaskan setiap fitur langkah demi langkah, DAN — di bagian akhir — menjelaskan <em>bagaimana</em> semua ini dibangun: dari mana data berasal, bagaimana ia dikurasi, dan algoritma apa yang bekerja di baliknya. Bagian yang lebih teknis ditandai dengan panel "🔧 Detail Teknis" yang bisa dibuka kalau kamu penasaran, dan dilipat kalau tidak.</p>
HTML,
                        'en' => <<<'HTML'
<p><strong>FACTA</strong> stands for <em>Finding Association in Collection of Text Al-Quran</em> — a Quran reading &amp; study app that, on top of the usual read/translate/tafsir/audio features, has one feature at its core that sets it apart: <strong>tracing relationships between Arabic root words</strong> across all 6,236 verses of the Quran.</p>
<p>FACTA's core idea isn't new — it traces back to a <strong>final-year thesis project (Tugas Akhir)</strong> at the Informatics Engineering department (IF), Institut Teknologi Bandung (ITB), completed around 2004 by <strong>Hendi Wibowo (student ID 13599039)</strong>. That original version was written in <strong>Java (J2SE)</strong>, with a <strong>JBuilder</strong>-built UI, a simple language model stored via <em>binary serialization</em> of a <em>matrix of BitSet</em>, and its text corpus kept as plain text files. What you're using today is a <strong>full re-engineering</strong> — architecture and data structures adapted for the present, built with AI assistance — but it deliberately keeps the same underlying <strong>logic, search algorithm, and product vision</strong> as that 2004 original.</p>
<p>The philosophy: every word in the Quran's Arabic derives from a 2-4 letter <strong>root</strong>. Words with different forms that share the same root usually share the same core meaning. FACTA builds a graph of "which root co-occurs with which root, in which verse" across the entire Quran, then gives you interactive tools to explore that graph — whether you're searching for verses by a combination of meanings, or simply want to understand one word more deeply through its related roots, synonyms, and antonyms.</p>
<p>This guide walks through every feature step by step, and — in the last section — explains <em>how</em> all of it was built: where the data comes from, how it was curated, and what algorithms run underneath. The more technical parts are marked with a "🔧 Technical Detail" panel you can open if you're curious, and leave collapsed if not.</p>
HTML,
                        'ar' => <<<'HTML'
<p><strong>فاكتا (FACTA)</strong> اختصار لعبارة <em>Finding Association in Collection of Text Al-Quran</em> (اكتشاف الارتباطات في نصوص القرآن) — تطبيق لقراءة القرآن ودراسته، يضيف إلى وظائف القراءة والترجمة والتفسير والاستماع المعتادة ميزة أساسية مميزة: <strong>تتبّع العلاقات بين الجذور العربية</strong> عبر آيات القرآن الكريم البالغة 6236 آية.</p>
<p>لا تُعدّ الفكرة الجوهرية لفاكتا جديدة — فهي تعود إلى <strong>مشروع تخرج (Tugas Akhir)</strong> في قسم هندسة المعلوماتية (IF) بمعهد باندونغ التكنولوجي (ITB)، أُنجز نحو عام 2004 على يد <strong>هندي ويبوو (الرقم الجامعي 13599039)</strong>. كُتبت تلك النسخة الأصلية بلغة <strong>جافا (J2SE)</strong>، بواجهة مستخدم مبنية باستخدام <strong>JBuilder</strong>، ونموذج لغوي بسيط يُخزَّن عبر <em>تسلسل ثنائي (binary serialization)</em> لمصفوفة من <em>BitSet</em>، وموضوعة نصية (corpus) على هيئة ملفات نصية عادية. أما ما تستخدمه اليوم فهو <strong>إعادة هندسة كاملة</strong> — بمعمارية وهياكل بيانات مُكيَّفة للحاضر، مبنية بمساعدة الذكاء الاصطناعي — لكنها تحافظ عمداً على نفس <strong>المنطق وخوارزمية البحث ورؤية المنتج</strong> من تلك النسخة الأصلية لعام 2004.</p>
<p>الفكرة: كل كلمة في القرآن الكريم مشتقة من <strong>جذر</strong> من حرفين إلى أربعة أحرف. الكلمات المختلفة الشكل التي تشترك في نفس الجذر تشترك عادة في نفس المعنى الأساسي. يبني FACTA شبكة علاقات "أي جذر يظهر مع أي جذر، وفي أي آية" عبر القرآن كله، ثم يوفر أدوات لاستكشاف هذه الشبكة تفاعلياً — سواء للبحث عن آيات حسب توليفة من المعاني، أو لفهم كلمة واحدة بعمق أكبر من خلال الجذور المرتبطة بها والمرادفات والأضداد.</p>
<p>يشرح هذا الدليل كل ميزة خطوة بخطوة، ويشرح في القسم الأخير <em>كيفية</em> بناء كل ذلك: من أين تأتي البيانات، وكيف نُقّحت، وما الخوارزميات التي تعمل خلف الكواليس. الأجزاء الأكثر تقنية موسومة بلوحة "🔧 تفاصيل تقنية" يمكن فتحها لمن يرغب في التعمق، وتُترك مطوية لغير ذلك.</p>
HTML,
                    ],
                ],
                [
                    'id' => 'navigasi',
                    'icon' => '🧭',
                    'title' => ['ar' => 'التنقل في التطبيق', 'en' => 'Navigating the App', 'id' => 'Navigasi Aplikasi'],
                    'body' => [
                        'id' => <<<'HTML'
<p>Tiga area utama yang akan sering kamu pakai:</p>
<ul>
  <li><strong>Sidebar kiri</strong> — menu utama: Jelajah (beranda), Juz, Cari, Topik, AI/Chat AI. Di layar sempit, sidebar ini bisa disembunyikan/muncul lewat tombol menu.</li>
  <li><strong>Toolbar kanan-atas</strong> — kotak pencarian cepat, pemilih bahasa antarmuka, tombol ganti tema (🌙/☀️), tombol Bookmark (🔖), tombol Pengaturan (⚙️), tombol Panduan (❓ — ya, halaman yang sedang kamu baca ini), dan tombol Login.</li>
  <li><strong>Halaman Beranda (Jelajah)</strong> — kartu "Terakhir dibaca" untuk lanjut membaca cepat, dan grid 114 kartu surat (bisa mode geser/slider atau daftar, lihat Pengaturan).</li>
</ul>
<p>Hampir semua teks antarmuka (menu, tombol, label) mengikuti <strong>bahasa antarmuka</strong> yang dipilih di toolbar atau di halaman Pengaturan — keduanya mengubah pengaturan yang sama, cukup pakai salah satu.</p>
HTML,
                        'en' => <<<'HTML'
<p>Three main areas you'll use constantly:</p>
<ul>
  <li><strong>Left sidebar</strong> — main menu: Browse (home), Juz, Search, Topics, AI/Chat AI. On narrow screens this sidebar can be toggled open/closed with a menu button.</li>
  <li><strong>Top-right toolbar</strong> — a quick search box, the UI language picker, a theme toggle (🌙/☀️), a Bookmarks button (🔖), a Settings button (⚙️), a Guide button (❓ — the one that got you to this very page), and a Login button.</li>
  <li><strong>Home page (Browse)</strong> — a "Continue Reading" card for quick resume, and a grid of all 114 surah cards (either a sliding-page layout or a plain list — see Settings).</li>
</ul>
<p>Nearly all interface text (menus, buttons, labels) follows the <strong>UI language</strong> picked from the toolbar or the Settings page — both change the same underlying setting, so either one works.</p>
HTML,
                        'ar' => <<<'HTML'
<p>ثلاث مناطق رئيسية ستستخدمها باستمرار:</p>
<ul>
  <li><strong>الشريط الجانبي الأيسر</strong> — القائمة الرئيسية: تصفح (الرئيسية)، جزء، بحث، مواضيع، الذكاء الاصطناعي/الدردشة. في الشاشات الضيقة يمكن إظهار/إخفاء هذا الشريط بزر القائمة.</li>
  <li><strong>الشريط العلوي الأيمن</strong> — مربع بحث سريع، منتقي لغة الواجهة، زر تبديل المظهر (🌙/☀️)، زر المفضلة (🔖)، زر الإعدادات (⚙️)، زر الدليل (❓ — نفس الزر الذي أوصلك إلى هذه الصفحة)، وزر تسجيل الدخول.</li>
  <li><strong>الصفحة الرئيسية (تصفح)</strong> — بطاقة "آخر قراءة" للمتابعة السريعة، وشبكة من 114 بطاقة سورة (إما بتخطيط شرائح منزلقة أو قائمة عادية — راجع الإعدادات).</li>
</ul>
<p>يتبع كل نص الواجهة تقريباً (القوائم، الأزرار، التسميات) <strong>لغة الواجهة</strong> المختارة من الشريط العلوي أو من صفحة الإعدادات — كلاهما يغيّر نفس الإعداد، فأيّهما يفي بالغرض.</p>
HTML,
                    ],
                ],
                [
                    'id' => 'bahasa-tema',
                    'icon' => '🌐',
                    'title' => ['ar' => 'اللغة والمظهر', 'en' => 'Language & Theme', 'id' => 'Bahasa & Tema'],
                    'body' => [
                        'id' => <<<'HTML'
<p>FACTA punya <strong>lima bahasa antarmuka</strong>: Arab, Inggris, Indonesia, Sunda, dan Jawa. Ganti lewat dropdown di toolbar atau di Pengaturan → Bahasa. Perubahan ini langsung memengaruhi seluruh menu/label/tombol di aplikasi.</p>
<p>Ini <strong>terpisah</strong> dari "Bahasa Terjemahan" (hanya Inggris/Indonesia tersedia) — itu mengatur bahasa <em>teks terjemahan ayat</em> yang ditampilkan saat membaca, bukan bahasa menu. Lihat bagian <a href="#hubungan-setting">Hubungan Pengaturan &amp; Menu</a> untuk penjelasan lengkap kenapa keduanya dipisah dan bagaimana keduanya saling memengaruhi tampilan.</p>
<p>Tema terang/gelap bisa diganti lewat ikon 🌙/☀️ di toolbar atau toggle di Pengaturan → Tampilan — pengaturan ini tersimpan di perangkatmu (cookie), jadi tetap sama di kunjungan berikutnya.</p>
HTML,
                        'en' => <<<'HTML'
<p>FACTA has <strong>five UI languages</strong>: Arabic, English, Indonesian, Sundanese, and Javanese. Switch it from the toolbar dropdown or Settings → Language. The change immediately affects every menu/label/button across the app.</p>
<p>This is <strong>separate</strong> from "Translation Language" (only English/Indonesian available) — that one controls the language of the <em>verse translation text</em> shown while reading, not the menus. See <a href="#hubungan-setting">How Settings Relate to Menus</a> for the full explanation of why these are split and how they interact.</p>
<p>Dark/light theme is toggled from the 🌙/☀️ toolbar icon or the switch in Settings → Appearance — it's saved on your device (a cookie), so it stays the same on your next visit.</p>
HTML,
                        'ar' => <<<'HTML'
<p>يحتوي فاكتا على <strong>خمس لغات واجهة</strong>: العربية، الإنجليزية، الإندونيسية، السوندانية، والجاوية. بدّلها من القائمة المنسدلة في الشريط العلوي أو من الإعدادات ← اللغة. يؤثر التغيير فوراً على كل قائمة/تسمية/زر في التطبيق.</p>
<p>هذا <strong>منفصل</strong> عن "لغة الترجمة" (الإنجليزية/الإندونيسية فقط متاحتان) — تلك تتحكم بلغة <em>نص ترجمة الآية</em> المعروض أثناء القراءة، وليس لغة القوائم. راجع قسم <a href="#hubungan-setting">علاقة الإعدادات بالقوائم</a> للشرح الكامل لسبب هذا الفصل وكيفية تأثير كل منهما على الآخر.</p>
<p>يمكن تبديل المظهر الداكن/الفاتح من أيقونة 🌙/☀️ في الشريط العلوي أو المفتاح في الإعدادات ← المظهر — يُحفظ هذا الإعداد على جهازك (ملف تعريف ارتباط)، فيبقى كما هو في زيارتك التالية.</p>
HTML,
                    ],
                ],
            ],
        ],

        // ============================================================
        [
            'id' => 'membaca',
            'icon' => '📗',
            'title' => ['ar' => 'قراءة القرآن', 'en' => 'Reading the Quran', 'id' => 'Membaca Al-Quran'],
            'sections' => [
                [
                    'id' => 'mode-baca',
                    'icon' => '📚',
                    'title' => ['ar' => 'التصفح: سورة، جزء', 'en' => 'Browsing: Surah, Juz', 'id' => 'Mode Jelajah: Surat & Juz'],
                    'body' => [
                        'id' => <<<'HTML'
<p><strong>Langkah membaca sebuah surat:</strong></p>
<ol>
  <li>Dari Beranda, klik salah satu dari 114 kartu surat (atau gunakan menu <strong>Juz</strong> di sidebar untuk memilih dari 30 juz).</li>
  <li>Halaman surat menampilkan nama Arab, transliterasi, nama terjemahan, jenis (Makkiyah/Madaniyah), jumlah ayat, dan nomor juz.</li>
  <li>Tiap ayat menampilkan (sesuai pengaturan tampilanmu): teks Arab, terjemahan, dan — kalau ayat itu punya kata beraksara tebal yang bisa diklik — chip kata dasar kecil di bawahnya (lihat bagian <a href="#info-kata">Info Kata</a>).</li>
  <li>Klik kata Arab mana pun dalam teks ayat untuk membuka popup <strong>Info Kata</strong>.</li>
</ol>
<p>Ada juga mode <strong>tampilan per-kata</strong> (word-by-word) di halaman surat — beralih ke tampilan ini menampilkan tiap kata Arab berikut terjemahannya satu per satu secara berurutan, cocok untuk belajar kosakata sambil membaca.</p>
HTML,
                        'en' => <<<'HTML'
<p><strong>Steps to read a surah:</strong></p>
<ol>
  <li>From the Home page, click any of the 114 surah cards (or use the <strong>Juz</strong> menu in the sidebar to pick from the 30 juz).</li>
  <li>The surah page shows the Arabic name, transliteration, translated name, type (Meccan/Medinan), verse count, and juz number.</li>
  <li>Each verse shows (depending on your display settings): the Arabic text, the translation, and — if that verse has a clickable highlighted word — a small root-word chip row underneath (see <a href="#info-kata">Word Info</a>).</li>
  <li>Click any Arabic word in the verse text to open the <strong>Word Info</strong> popup.</li>
</ol>
<p>There's also a <strong>word-by-word view</strong> on the surah page — switching to it shows each Arabic word followed by its translation, one after another, handy for learning vocabulary while reading.</p>
HTML,
                        'ar' => <<<'HTML'
<p><strong>خطوات قراءة سورة:</strong></p>
<ol>
  <li>من الصفحة الرئيسية، اضغط على أي من بطاقات السور الـ114 (أو استخدم قائمة <strong>جزء</strong> في الشريط الجانبي لاختيار أحد الأجزاء الثلاثين).</li>
  <li>تعرض صفحة السورة الاسم العربي، والنقحرة، والاسم المترجم، والنوع (مكية/مدنية)، وعدد الآيات، ورقم الجزء.</li>
  <li>تعرض كل آية (حسب إعدادات العرض لديك): النص العربي، الترجمة، وإن كانت الآية تحتوي كلمة مظللة قابلة للنقر — صف صغير من رقاقات الجذور تحتها (راجع <a href="#info-kata">معلومات الكلمة</a>).</li>
  <li>اضغط على أي كلمة عربية في نص الآية لفتح نافذة <strong>معلومات الكلمة</strong>.</li>
</ol>
<p>توجد أيضاً <strong>عرض كلمة بكلمة</strong> في صفحة السورة — التبديل إليه يعرض كل كلمة عربية تليها ترجمتها، واحدة تلو الأخرى، مفيد لتعلم المفردات أثناء القراءة.</p>
HTML,
                    ],
                ],
                [
                    'id' => 'reading-mode',
                    'icon' => '📕',
                    'title' => ['ar' => 'أوضاع القراءة', 'en' => 'Reading Modes', 'id' => 'Mode Tampilan Bacaan'],
                    'body' => [
                        'id' => <<<'HTML'
<p>Ada <strong>tiga</strong> lapisan pengaturan tampilan bacaan yang independen satu sama lain (semuanya di Pengaturan → Tampilan Bacaan):</p>
<ul>
  <li><strong>Reading Mode: Penuh vs Halaman</strong> — "Penuh" menampilkan semua ayat satu surat sekaligus (scroll panjang); "Halaman" membaginya 20 ayat per halaman dengan navigasi berikutnya/sebelumnya.</li>
  <li><strong>Browse Mode: Slider vs List</strong> — mengatur tampilan grid 114 kartu surat/30 kartu juz di halaman Jelajah/Juz: "Slider" = kartu bergeser per halaman dengan panah &amp; titik navigasi; "List" = daftar polos yang di-scroll biasa.</li>
  <li><strong>📕 Mode Buku</strong> — tombol terpisah di halaman surat (bukan cookie reading-mode di atas) yang membuka tampilan seperti buku digital: satu "halaman" berisi sejumlah ayat yang bisa diatur (Auto = sebanyak muat di layar, atau tetap 1/3/5/10 ayat), dengan navigasi flip halaman.</li>
</ul>
<p>Toggle terpisah untuk menyembunyikan/menampilkan teks Arab, terjemahan, dan warna tajwid juga ada di grup pengaturan yang sama, independen dari tiga mode di atas.</p>
HTML,
                        'en' => <<<'HTML'
<p>There are <strong>three</strong> independent layers of reading-display settings (all under Settings → Reading Display):</p>
<ul>
  <li><strong>Reading Mode: Full vs Paged</strong> — "Full" shows every verse of a surah at once (one long scroll); "Paged" splits it into 20-verse pages with next/previous navigation.</li>
  <li><strong>Browse Mode: Slider vs List</strong> — controls the grid of 114 surah / 30 juz cards on the Browse/Juz pages: "Slider" = cards slide page-by-page with arrows &amp; dot navigation; "List" = a plain scrollable list.</li>
  <li><strong>📕 Book Mode</strong> — a separate button on the surah page (not the reading-mode cookie above) that opens a digital-book-like view: one "page" holds a configurable number of verses (Auto = as many as fit on screen, or a fixed 1/3/5/10), with page-flip navigation.</li>
</ul>
<p>Separate toggles to show/hide Arabic text, translation, and tajweed coloring live in the same settings group, independent of the three modes above.</p>
HTML,
                        'ar' => <<<'HTML'
<p>توجد <strong>ثلاث</strong> طبقات مستقلة من إعدادات عرض القراءة (كلها ضمن الإعدادات ← عرض القراءة):</p>
<ul>
  <li><strong>وضع القراءة: كامل مقابل مُصفّح</strong> — "كامل" يعرض كل آيات السورة دفعة واحدة (تمرير طويل)؛ "مُصفّح" يقسمها إلى صفحات من 20 آية مع تنقل تالي/سابق.</li>
  <li><strong>وضع التصفح: شرائح مقابل قائمة</strong> — يتحكم بشبكة بطاقات السور الـ114/الأجزاء الـ30 في صفحتي التصفح/الجزء: "شرائح" = تنزلق البطاقات صفحة بصفحة بأسهم ونقاط تنقل؛ "قائمة" = قائمة عادية قابلة للتمرير.</li>
  <li><strong>📕 وضع الكتاب</strong> — زر منفصل في صفحة السورة (وليس إعداد وضع القراءة أعلاه) يفتح عرضاً يشبه الكتاب الرقمي: "صفحة" واحدة تحوي عدداً قابلاً للتهيئة من الآيات (تلقائي = بقدر ما يتسع في الشاشة، أو ثابت 1/3/5/10)، مع تنقل بتقليب الصفحات.</li>
</ul>
<p>مفاتيح منفصلة لإظهار/إخفاء النص العربي والترجمة وتلوين التجويد موجودة في نفس مجموعة الإعدادات، بشكل مستقل عن الأوضاع الثلاثة أعلاه.</p>
HTML,
                    ],
                ],
                [
                    'id' => 'audio-tafsir',
                    'icon' => '🔊',
                    'title' => ['ar' => 'الصوت والتفسير', 'en' => 'Audio & Tafsir', 'id' => 'Audio Murottal & Tafsir'],
                    'body' => [
                        'id' => <<<'HTML'
<p>Setiap ayat punya tombol <strong>▶️ Play Audio</strong> untuk memutar bacaan (murottal). Ada dua qari yang bisa dipilih di Pengaturan → Audio: <strong>Mishari Rashid Alafasy</strong> dan <strong>Abdul Basit Murattal</strong>; file audio diambil langsung dari sumber pihak ketiga (everyayah.com), bukan disimpan di server aplikasi.</p>
<p>Tombol <strong>📔 Tafsir</strong> membuka penjelasan tafsir untuk ayat itu, kalau tersedia. <em>Catatan jujur</em>: basis data tafsir saat ini masih berupa contoh terbatas (bukan tafsir lengkap 6.236 ayat) — bertambah seiring waktu, bukan dataset final.</p>
HTML,
                        'en' => <<<'HTML'
<p>Every verse has a <strong>▶️ Play Audio</strong> button for recitation (murottal). Two reciters are selectable under Settings → Audio: <strong>Mishari Rashid Alafasy</strong> and <strong>Abdul Basit Murattal</strong>; audio files are streamed directly from a third-party source (everyayah.com), not hosted on the app's own server.</p>
<p>The <strong>📔 Tafsir</strong> button opens exegesis for that verse, when available. <em>Honest note</em>: the tafsir dataset today is still a limited sample (not full commentary for all 6,236 verses) — it grows over time, it isn't a finished dataset.</p>
HTML,
                        'ar' => <<<'HTML'
<p>لكل آية زر <strong>▶️ تشغيل الصوت</strong> للاستماع إلى التلاوة (المرتّل). يمكن اختيار أحد قارئين من الإعدادات ← الصوت: <strong>مشاري راشد العفاسي</strong> و<strong>عبد الباسط عبد الصمد المرتّل</strong>؛ تُبثّ ملفات الصوت مباشرة من مصدر خارجي (everyayah.com)، وليست مستضافة على خادم التطبيق نفسه.</p>
<p>يفتح زر <strong>📔 التفسير</strong> شرحاً تفسيرياً لتلك الآية، عند توفره. <em>ملاحظة بصراحة</em>: قاعدة بيانات التفسير حالياً لا تزال عينة محدودة (وليست تفسيراً كاملاً لجميع الآيات الـ6236) — تنمو مع الوقت، وليست مجموعة بيانات نهائية.</p>
HTML,
                    ],
                ],
                [
                    'id' => 'bookmark-share',
                    'icon' => '🔖',
                    'title' => ['ar' => 'الإشارات المرجعية والمشاركة', 'en' => 'Bookmarks & Sharing', 'id' => 'Bookmark, Salin, & Bagikan'],
                    'body' => [
                        'id' => <<<'HTML'
<p><strong>Bookmark</strong>: klik tombol 🔖 di bawah ayat mana pun untuk menyimpannya. Semua bookmark bisa dilihat lewat ikon 🔖 di toolbar (badge angka menunjukkan jumlahnya) atau menu Bookmark di sidebar. Aplikasi juga otomatis mengingat <strong>ayat terakhir dibaca</strong> (kartu "Terakhir dibaca" di Beranda) — ini terpisah dari bookmark manual, tersimpan otomatis tiap kali kamu berpindah ayat.</p>
<p><strong>Salin (Copy)</strong>: menyalin teks Arab + terjemahan ayat ke clipboard, siap ditempel ke mana saja.</p>
<p><strong>Bagikan (Share)</strong>: membuka menu berbagi ke WhatsApp, Email, Telegram, X/Twitter, Facebook, atau menyalin tautan langsung ke ayat tersebut.</p>
HTML,
                        'en' => <<<'HTML'
<p><strong>Bookmark</strong>: click the 🔖 button under any verse to save it. All bookmarks are listed via the 🔖 toolbar icon (the number badge shows the count) or the Bookmarks menu in the sidebar. The app also automatically remembers your <strong>last-read verse</strong> (the "Continue Reading" card on Home) — that's separate from manual bookmarks, saved automatically every time you move between verses.</p>
<p><strong>Copy</strong>: copies the verse's Arabic text + translation to the clipboard, ready to paste anywhere.</p>
<p><strong>Share</strong>: opens a share menu for WhatsApp, Email, Telegram, X/Twitter, Facebook, or copying a direct link to that verse.</p>
HTML,
                        'ar' => <<<'HTML'
<p><strong>الإشارة المرجعية (Bookmark)</strong>: اضغط زر 🔖 أسفل أي آية لحفظها. تُعرض جميع الإشارات المرجعية عبر أيقونة 🔖 في الشريط العلوي (يوضح الشارة الرقمية العدد) أو قائمة الإشارات المرجعية في الشريط الجانبي. يتذكر التطبيق أيضاً تلقائياً <strong>آخر آية مقروءة</strong> (بطاقة "آخر قراءة" في الرئيسية) — وهذا منفصل عن الإشارات المرجعية اليدوية، ويُحفظ تلقائياً كلما انتقلت بين الآيات.</p>
<p><strong>نسخ</strong>: ينسخ النص العربي وترجمة الآية إلى الحافظة، جاهزين للصق في أي مكان.</p>
<p><strong>مشاركة</strong>: يفتح قائمة مشاركة عبر واتساب، البريد الإلكتروني، تيليجرام، إكس/تويتر، فيسبوك، أو نسخ رابط مباشر لتلك الآية.</p>
HTML,
                    ],
                ],
            ],
        ],

        // ============================================================
        [
            'id' => 'pencarian',
            'icon' => '🔍',
            'title' => ['ar' => 'البحث', 'en' => 'Search', 'id' => 'Pencarian'],
            'sections' => [
                [
                    'id' => 'cari-teks',
                    'icon' => '📝',
                    'title' => ['ar' => 'البحث النصي', 'en' => 'Text Search', 'id' => 'Pencarian Teks'],
                    'body' => [
                        'id' => <<<'HTML'
<p>Mode pencarian paling dasar: ketik kata/frasa di kotak pencarian, pilih bahasa yang ingin dicari (Arab dan/atau Indonesia dan/atau Inggris — bisa lebih dari satu sekaligus), lalu tekan Cari. Hasilnya adalah daftar ayat yang teks Arab atau terjemahannya mengandung kata itu, dengan kata yang cocok disorot (highlight kuning/hijau).</p>
<p>Pencarian ini <strong>literal berbasis kata</strong> (full-text search), bukan pencarian akar kata — mengetik "rezeki" hanya menemukan ayat yang benar-benar memuat kata "rezeki" (atau bentuk dekatnya lewat stemmer ringan untuk ID/EN), bukan seluruh kata turunan akar ر-ز-ق. Untuk itu, pakai <a href="#cari-akar">Pencarian Kata Dasar</a>.</p>
HTML,
                        'en' => <<<'HTML'
<p>The most basic search mode: type a word/phrase into the search box, pick which language(s) to search (Arabic and/or Indonesian and/or English — more than one at once is fine), then hit Search. The result is a list of verses whose Arabic text or translation contains that word, with the matching text highlighted (yellow/green).</p>
<p>This is a <strong>literal, word-based</strong> full-text search, not a root-word search — typing "provision" only finds verses that actually contain the word "provision" (or a close form of it, via a light stemmer for ID/EN), not every word derived from the root ر-ز-ق. For that, use <a href="#cari-akar">Root Word Search</a>.</p>
HTML,
                        'ar' => <<<'HTML'
<p>أبسط أوضاع البحث: اكتب كلمة/عبارة في مربع البحث، اختر اللغة (اللغات) المراد البحث فيها (العربية و/أو الإندونيسية و/أو الإنجليزية — يمكن أكثر من واحدة معاً)، ثم اضغط بحث. النتيجة قائمة بالآيات التي يحتوي نصها العربي أو ترجمتها على تلك الكلمة، مع تظليل النص المطابق (أصفر/أخضر).</p>
<p>هذا بحث <strong>نصي حرفي مبني على الكلمات</strong> (بحث نص كامل)، وليس بحثاً بالجذر — كتابة "رزق" تجد فقط الآيات التي تحتوي فعلاً كلمة "رزق" (أو شكلاً قريباً منها عبر مقوّم جذع خفيف للإندونيسية/الإنجليزية)، وليس كل كلمة مشتقة من جذر ر-ز-ق. لذلك، استخدم <a href="#cari-akar">البحث بالجذر</a>.</p>
HTML,
                    ],
                ],
                [
                    'id' => 'cari-akar',
                    'icon' => '🌱',
                    'title' => ['ar' => 'البحث بالجذر', 'en' => 'Root Word Search', 'id' => 'Pencarian Kata Dasar (Root)'],
                    'body' => [
                        'id' => <<<'HTML'
<p>Mode ini menemukan <strong>semua bentuk turunan</strong> dari sebuah akar Arab 2-4 huruf, di seluruh Al-Quran — bukan cuma satu bentuk kata persis. Cara pakai:</p>
<ol>
  <li>Buka tab <strong>Cari Kata Dasar</strong>, lalu ketik akar Arab-nya (mis. <span style="font-family:var(--font-arabic)">رزق</span>) — kamu bisa pakai keyboard hijaiyah bawaan aplikasi kalau tidak punya keyboard Arab.</li>
  <li>Atau, dari popup Info Kata mana pun, klik tombol <strong>🔤 Cari Kata Dasar</strong> untuk langsung mencari akar kata yang sedang dilihat.</li>
  <li>Hasilnya: semua ayat yang memuat kata berbasis akar itu, dalam bentuk apa pun (kata dasar, jamak, kata kerja lampau/sekarang, dst).</li>
</ol>
<p>Ini <em>tidak</em> memakai tabel data yang sudah dihitung sebelumnya — pencocokan dilakukan <strong>langsung saat itu juga (runtime)</strong> lewat pendekatan morfologi ringan. Lihat panel Detail Teknis untuk cara kerjanya.</p>
HTML,
                        'en' => <<<'HTML'
<p>This mode finds <strong>every derived form</strong> of a 2-4 letter Arabic root, across the whole Quran — not just one exact word form. How to use it:</p>
<ol>
  <li>Open the <strong>Root Search</strong> tab, then type the Arabic root (e.g. <span style="font-family:var(--font-arabic)">رزق</span>) — you can use the app's built-in Hijaiyah keyboard if you don't have an Arabic keyboard.</li>
  <li>Or, from any Word Info popup, click the <strong>🔤 Root Search</strong> button to instantly search the root of the word you're looking at.</li>
  <li>The result: every verse containing a word based on that root, in any form (base form, plural, past/present verb, etc).</li>
</ol>
<p>This does <em>not</em> use a precomputed lookup table — matching happens <strong>live, at query time</strong>, via a light morphology approach. See the Technical Detail panel for how it works.</p>
HTML,
                        'ar' => <<<'HTML'
<p>يجد هذا الوضع <strong>كل الأشكال المشتقة</strong> من جذر عربي من حرفين إلى أربعة، عبر القرآن كله — وليس شكلاً واحداً بالضبط. طريقة الاستخدام:</p>
<ol>
  <li>افتح تبويب <strong>البحث بالجذر</strong>، ثم اكتب الجذر العربي (مثل <span style="font-family:var(--font-arabic)">رزق</span>) — يمكنك استخدام لوحة المفاتيح الهجائية المدمجة في التطبيق إن لم يكن لديك لوحة مفاتيح عربية.</li>
  <li>أو، من أي نافذة معلومات كلمة، اضغط زر <strong>🔤 البحث بالجذر</strong> للبحث الفوري عن جذر الكلمة التي تنظر إليها.</li>
  <li>النتيجة: كل آية تحتوي كلمة مبنية على ذلك الجذر، بأي صيغة (الصيغة الأساسية، الجمع، الفعل الماضي/المضارع، إلخ).</li>
</ol>
<p>هذا <em>لا</em> يستخدم جدول بيانات محسوباً مسبقاً — تتم المطابقة <strong>حيّة، وقت الاستعلام</strong>، عبر مقاربة صرفية خفيفة. راجع لوحة التفاصيل التقنية لمعرفة آلية العمل.</p>
HTML,
                    ],
                    'deep_dive' => [
                        'id' => <<<'HTML'
<p>Pencocokan akar-ke-kata pakai pendekatan <em>light stemmer</em> (mirip semangat ISRI/Khoja stemmer untuk Arab), tiga langkah:</p>
<ol>
  <li><strong>Normalisasi</strong> — hapus tanda diakritik (harakat), samakan variasi hamzah (أ إ آ ٱ → ا), dan ة → ه.</li>
  <li><strong>Lucuti imbuhan (klitik)</strong> — coba lepas kombinasi awalan (ال، و، ف، ب، ك، ل...) dan akhiran (ون، ين، ات، كم، ها...) dari kata secara iteratif. Karena imbuhan pendek seperti "ك" bisa ambigu (bagian akar atau imbuhan?), <strong>semua kemungkinan residu disimpan sebagai kandidat</strong> — bukan cuma satu tebakan "terbaik".</li>
  <li><strong>Cocokkan sebagai subsequence</strong> — untuk tiap kandidat residu, cek apakah huruf-huruf akar muncul <em>berurutan</em> di dalamnya (boleh ada huruf sisipan di antaranya, tidak harus berdampingan). Ini penting karena pola pembentukan kata Arab menyisipkan huruf tambahan (ا و ي ت م ن س) di antara huruf akar — misalnya "مكتوب" (م-ك-ت-و-ب) tetap cocok dengan akar ك-ت-ب karena ك، ت، ب muncul berurutan meski disisipi م dan و.</li>
</ol>
<p>Ini pendekatan <strong>heuristik</strong>, bukan analisis morfologi yang diverifikasi ahli bahasa — bisa saja ada kata yang secara kebetulan cocok pola tapi sebenarnya bukan turunan akar itu (positif palsu), atau sebaliknya. Pencarian ini juga dipakai untuk kata dasar Bahasa Indonesia/Inggris dengan stemmer yang jauh lebih sederhana (pencocokan awalan/akhiran umum).</p>
HTML,
                        'en' => <<<'HTML'
<p>Root-to-word matching uses a <em>light stemmer</em> approach (in the spirit of the well-known ISRI/Khoja Arabic stemmers), in three steps:</p>
<ol>
  <li><strong>Normalize</strong> — strip diacritics (harakat), unify hamza variants (أ إ آ ٱ → ا), and ة → ه.</li>
  <li><strong>Strip clitics</strong> — iteratively try stripping prefix (ال، و، ف، ب، ك، ل...) and suffix (ون، ين، ات، كم، ها...) combinations off the word. Because a short clitic like "ك" can be ambiguous (part of the root, or a genuine prefix?), <strong>every possible residual is kept as a candidate</strong> — not just one "best guess".</li>
  <li><strong>Match as a subsequence</strong> — for each candidate residual, check whether the root's letters appear <em>in order</em> within it (other letters may sit between them, they don't need to be adjacent). This matters because Arabic word-formation patterns interleave extra letters (ا و ي ت م ن س) between the root's radicals — e.g. "مكتوب" (m-k-t-w-b) still matches root ك-ت-ب because ك, ت, ب appear in order despite the inserted م and و.</li>
</ol>
<p>This is a <strong>heuristic</strong> approach, not linguist-verified morphological analysis — a word could coincidentally match the pattern without truly deriving from that root (a false positive), or vice versa. The same search also powers Indonesian/English root/base-word search, with a much simpler stemmer (common prefix/suffix matching).</p>
HTML,
                        'ar' => <<<'HTML'
<p>تستخدم مطابقة الجذر بالكلمة مقاربة <em>مقوّم جذع خفيف</em> (بروح مقوّمات الجذع العربية المعروفة مثل ISRI وKhoja)، على ثلاث خطوات:</p>
<ol>
  <li><strong>التطبيع</strong> — إزالة علامات التشكيل، وتوحيد أشكال الهمزة (أ إ آ ٱ ← ا)، وة ← ه.</li>
  <li><strong>تجريد الزوائد</strong> — محاولة تجريد تركيبات من السوابق (ال، و، ف، ب، ك، ل...) واللواحق (ون، ين، ات، كم، ها...) عن الكلمة بشكل تكراري. ولأن زائدة قصيرة مثل "ك" قد تكون ملتبسة (جزء من الجذر أم زائدة حقيقية؟)، <strong>تُحفظ كل البقايا المحتملة كمرشحات</strong> — وليس تخميناً واحداً "أفضل".</li>
  <li><strong>المطابقة كسلسلة فرعية</strong> — لكل بقية مرشحة، يُفحص ما إذا كانت حروف الجذر تظهر <em>بالترتيب</em> ضمنها (يجوز وجود حروف أخرى بينها، لا يلزم أن تكون متجاورة). هذا مهم لأن أنماط تكوين الكلمة العربية تُدخل حروفاً إضافية (ا و ي ت م ن س) بين حروف الجذر — فمثلاً "مكتوب" (م-ك-ت-و-ب) لا تزال تطابق جذر ك-ت-ب لأن ك، ت، ب تظهر بالترتيب رغم إدراج م وو.</li>
</ol>
<p>هذه مقاربة <strong>استدلالية</strong>، وليست تحليلاً صرفياً موثّقاً من لغويين — قد تطابق كلمة النمط صدفةً دون أن تكون مشتقة فعلاً من ذلك الجذر (إيجابية زائفة)، أو العكس. يُستخدم البحث نفسه أيضاً للبحث بالكلمة الأساسية بالإندونيسية/الإنجليزية، بمقوّم جذع أبسط بكثير (مطابقة سوابق ولواحق شائعة).</p>
HTML,
                    ],
                ],
                [
                    'id' => 'info-kata',
                    'icon' => '🔤',
                    'title' => ['ar' => 'معلومات الكلمة', 'en' => 'Word Info', 'id' => 'Info Kata'],
                    'body' => [
                        'id' => <<<'HTML'
<p>Klik kata Arab mana pun yang bergaris bawah/disorot dalam teks ayat untuk membuka popup <strong>Info Kata</strong>. Popup ini punya beberapa bagian yang bisa dibuka/ditutup (accordion):</p>
<ul>
  <li><strong>Kata Turunan</strong> — bentuk-bentuk kata lain di Al-Quran yang berasal dari akar yang sama, tiap chip bisa diklik untuk mencari bentuk kata itu persis.</li>
  <li><strong>Sinonim</strong> &amp; <strong>Antonim</strong> — akar kata lain yang maknanya mirip atau berlawanan, hasil kurasi manual (lihat bagian <a href="#sinonim-antonim">Sinonim &amp; Antonim</a> di bawah untuk metodologinya).</li>
  <li><strong>Kata Terkait</strong> — akar-akar lain yang berhubungan secara tematik tapi bukan sinonim/antonim ketat.</li>
  <li><strong>Muncul Bersama</strong> — fitur paling dalam, dijelaskan di bagian tersendiri berikut ini.</li>
</ul>
<p>Kalau satu kata bisa berasal dari lebih dari satu akar yang masuk akal (ambigu), popup hanya menampilkan akar yang paling relevan (bukan menumpuk semua kemungkinan) — dipilih lewat skor kecocokan, diprioritaskan akar hasil kurasi manual di atas hasil impor otomatis, lalu frekuensi kemunculan.</p>
HTML,
                        'en' => <<<'HTML'
<p>Click any underlined/highlighted Arabic word in a verse to open the <strong>Word Info</strong> popup. It has several collapsible sections:</p>
<ul>
  <li><strong>Derived Forms</strong> — other word forms in the Quran sharing the same root; each chip can be clicked to search that exact form.</li>
  <li><strong>Synonyms</strong> &amp; <strong>Antonyms</strong> — other roots with similar or opposite meaning, manually curated (see <a href="#sinonim-antonim">Synonyms &amp; Antonyms</a> below for the methodology).</li>
  <li><strong>Related Words</strong> — other roots that are thematically related but not strictly synonyms/antonyms.</li>
  <li><strong>Appears With</strong> ("Muncul Bersama") — the deepest feature, explained in its own section right after this one.</li>
</ul>
<p>If a word could plausibly come from more than one root (ambiguous), the popup only shows the single most relevant root (not every possibility stacked up) — chosen by a match score, prioritizing manually-curated roots over auto-imported ones, then occurrence frequency.</p>
HTML,
                        'ar' => <<<'HTML'
<p>اضغط أي كلمة عربية مسطّرة/مظللة في نص آية لفتح نافذة <strong>معلومات الكلمة</strong>. تحتوي على عدة أقسام قابلة للطي/الفتح:</p>
<ul>
  <li><strong>الكلمات المشتقة</strong> — أشكال كلمات أخرى في القرآن تشترك في نفس الجذر؛ يمكن الضغط على أي رقاقة للبحث عن ذلك الشكل بالضبط.</li>
  <li><strong>المرادفات</strong> و<strong>الأضداد</strong> — جذور أخرى ذات معنى مشابه أو معاكس، منسقة يدوياً (راجع <a href="#sinonim-antonim">المرادفات والأضداد</a> أدناه لمعرفة المنهجية).</li>
  <li><strong>كلمات ذات صلة</strong> — جذور أخرى مرتبطة موضوعياً لكنها ليست مرادفات/أضداداً بدقة.</li>
  <li><strong>مُنَاسَبَات الظهور</strong> ("تظهر مع") — الميزة الأعمق، تُشرح في قسمها الخاص بعد هذا مباشرة.</li>
</ul>
<p>إن كانت الكلمة قد تنتمي منطقياً لأكثر من جذر (غموض)، تعرض النافذة الجذر الأكثر صلة فقط (وليس كل الاحتمالات مكدّسة) — يُختار عبر درجة تطابق، مع تفضيل الجذور المنسقة يدوياً على المستوردة تلقائياً، ثم تكرار الظهور.</p>
HTML,
                    ],
                ],
                [
                    'id' => 'muncul-bersama',
                    'icon' => '🌳',
                    'title' => ['ar' => 'تظهر مع (الظهور المشترك)', 'en' => '"Appears With" (Co-occurrence)', 'id' => '"Muncul Bersama" (Kemunculan Bersama)'],
                    'body' => [
                        'id' => <<<'HTML'
<p>Ini fitur paling khas FACTA. Konsepnya: untuk sebuah akar kata (mis. <span style="font-family:var(--font-arabic)">رزق</span> — "rezeki"), tampilkan <strong>semua akar kata lain</strong> yang pernah muncul di ayat yang sama, beserta berapa kali. Dari situ kamu bisa <strong>drill-down</strong> — pilih salah satu akar hasil, dan sistem mencari akar-akar lain yang muncul bersama KEDUA akar itu sekaligus (bukan cuma yang pertama), dan seterusnya, makin dalam makin spesifik.</p>
<p><strong>Dua tampilan</strong>, bisa ditukar lewat tombol 📍/🌳:</p>
<ul>
  <li><strong>📍 Breadcrumb</strong> — jejak akar yang sedang ditelusuri di atas, hasil level saat ini sebagai kartu (chip) di bawahnya. Klik jejak breadcrumb untuk mundur ke level sebelumnya.</li>
  <li><strong>🌳 Tree</strong> — gaya folder/explorer: tiap baris satu akar, klik untuk expand ke anak-anaknya (menjorok ke bawah), klik baris yang sudah terbuka untuk menutupnya kembali. Terjemahan tiap akar tampil langsung di baris (bukan cuma lewat hover).</li>
</ul>
<p>Di kedua tampilan ada kotak pencarian untuk memfilter cepat akar di level saat ini (ketik Arab atau terjemahan), dan tombol <strong>⛶</strong> untuk membuka panel ini di jendela yang lebih besar (berguna kalau daftarnya panjang atau terjemahannya panjang dan ingin dibaca utuh tanpa terpotong) — jendela besar ini tetap tersinkron dengan kartu kecil, drill-down di salah satunya langsung terlihat di yang lain.</p>
<p>Di setiap level ada tombol <strong>📖 Tampilkan Ayat</strong> — membuka hasil pencarian berisi SEMUA ayat yang memuat kombinasi akar-akar yang sedang dipilih (dengan setiap kata yang cocok disorot presisi, bukan seluruh akar), dan opsi <strong>"Hanya kombinasi ini"</strong> untuk mempersempit ke ayat yang PERSIS memuat kombinasi itu saja (tidak ada akar tambahan lain dari daftar yang sedang ditelusuri).</p>
HTML,
                        'en' => <<<'HTML'
<p>This is FACTA's signature feature. The idea: for a given root (e.g. <span style="font-family:var(--font-arabic)">رزق</span> — "provision"), show <strong>every other root</strong> that has ever appeared in the same verse, and how many times. From there you can <strong>drill down</strong> — pick one of the resulting roots, and the system finds other roots that co-occur with BOTH roots at once (not just the first), and so on, going deeper and more specific each time.</p>
<p><strong>Two views</strong>, switchable via the 📍/🌳 buttons:</p>
<ul>
  <li><strong>📍 Breadcrumb</strong> — the path of roots explored so far on top, the current level's results as cards below it. Click a breadcrumb segment to go back to an earlier level.</li>
  <li><strong>🌳 Tree</strong> — folder-explorer style: one row per root, click to expand its children (indented below), click an already-expanded row to collapse it back. Each root's translation shows right in the row (not just on hover).</li>
</ul>
<p>Both views have a search box to quickly filter the current level's roots (type Arabic or a translation), and an <strong>⛶</strong> button to open this panel in a bigger window (handy when the list is long, or translations are long and you want to read them in full without truncation) — that bigger window stays in sync with the compact card, drilling in either one shows up instantly in the other.</p>
<p>Every level has a <strong>📖 Show Ayahs</strong> button — opens search results with EVERY verse containing the currently-selected root combination (with each matching word precisely highlighted, not the whole root), and an <strong>"Only this combination"</strong> option to narrow it down to verses containing EXACTLY that combination (no other extra root from the path being explored).</p>
HTML,
                        'ar' => <<<'HTML'
<p>هذه هي الميزة المميزة لفاكتا. الفكرة: لجذر معين (مثل <span style="font-family:var(--font-arabic)">رزق</span>)، اعرض <strong>كل جذر آخر</strong> ظهر معه يوماً في نفس الآية، وكم مرة. من هناك يمكنك <strong>التعمق</strong> — اختر أحد الجذور الناتجة، ويجد النظام جذوراً أخرى تظهر مع كلا الجذرين معاً (وليس الأول فقط)، وهكذا، بتعمق وتخصيص أكبر في كل مرة.</p>
<p><strong>عرضان</strong>، يمكن التبديل بينهما بزري 📍/🌳:</p>
<ul>
  <li><strong>📍 مسار</strong> — مسار الجذور المستكشفة حتى الآن في الأعلى، ونتائج المستوى الحالي كبطاقات أسفله. اضغط جزءاً من المسار للعودة إلى مستوى سابق.</li>
  <li><strong>🌳 شجرة</strong> — بأسلوب مستكشف الملفات: صف واحد لكل جذر، اضغط للتوسيع إلى فروعه (بمسافة بادئة أسفله)، واضغط صفاً موسّعاً بالفعل لطيّه مجدداً. تظهر ترجمة كل جذر مباشرة في الصف (وليس فقط عند التحويم).</li>
</ul>
<p>يحتوي كلا العرضين على مربع بحث للتصفية السريعة لجذور المستوى الحالي (اكتب عربية أو ترجمة)، وزر <strong>⛶</strong> لفتح هذه اللوحة في نافذة أكبر (مفيد عندما تكون القائمة طويلة، أو الترجمات طويلة وتريد قراءتها كاملة دون قطع) — تبقى تلك النافذة الكبيرة متزامنة مع البطاقة المدمجة، فالتعمق في إحداهما يظهر فوراً في الأخرى.</p>
<p>في كل مستوى زر <strong>📖 عرض الآيات</strong> — يفتح نتائج بحث تضم كل آية تحتوي توليفة الجذور المختارة حالياً (مع تظليل دقيق لكل كلمة مطابقة، وليس الجذر كله)، وخيار <strong>"هذا المزيج فقط"</strong> لتضييق النتائج إلى الآيات التي تحتوي بالضبط تلك التوليفة (دون أي جذر إضافي آخر من المسار المستكشف).</p>
HTML,
                    ],
                ],
                [
                    'id' => 'cari-and',
                    'icon' => '🔀',
                    'title' => ['ar' => 'البحث بأداة AND', 'en' => 'AND-Search Builder', 'id' => 'Pencarian AND (Kombinasi)'],
                    'body' => [
                        'id' => <<<'HTML'
<p>Cara pintas kalau kamu sudah tahu kombinasi konsep yang dicari (mis. ayat yang membahas "melahirkan" DAN "hari") tanpa harus drill-down manual lewat Muncul Bersama:</p>
<ol>
  <li>Buka tab <strong>🔀 Cari AND</strong>.</li>
  <li>Ketik kata pertama (bahasa apa pun yang didukung) — dropdown menampilkan akar-akar Arab yang cocok beserta artinya, karena satu kata bisa berarti beberapa akar berbeda (mis. "lahir" bisa berarti "tampak/nyata" ATAU "melahirkan" — dua akar berbeda).</li>
  <li>Pilih akar yang dimaksud → jadi chip di area builder.</li>
  <li>Ulangi untuk kata kedua, ketiga, dst.</li>
  <li>Tekan Cari — hasilnya ayat yang memuat SEMUA akar dalam kombinasi itu sekaligus.</li>
</ol>
<p>Kalau kamu mengetik kata di kotak pencarian teks biasa dan kata itu ternyata cocok dengan ≥2 akar berbeda, aplikasi otomatis menyarankan chip-chip disambiguasi di atas hasil pencarian — klik salah satu langsung membuka builder AND dengan akar itu sudah terisi.</p>
HTML,
                        'en' => <<<'HTML'
<p>A shortcut for when you already know the combination of concepts you're after (e.g. verses discussing "birth" AND "day") without manually drilling through "Appears With":</p>
<ol>
  <li>Open the <strong>🔀 AND Search</strong> tab.</li>
  <li>Type the first word (any supported language) — a dropdown shows matching Arabic roots along with their meanings, since one word can correspond to several different roots (e.g. "born" could mean "visible/manifest" OR "give birth" — two different roots).</li>
  <li>Pick the intended root → it becomes a chip in the builder area.</li>
  <li>Repeat for a second, third word, etc.</li>
  <li>Hit Search — the result is every verse containing ALL roots in that combination at once.</li>
</ol>
<p>If you type a word into the regular text search box and it happens to match 2+ different roots, the app automatically suggests disambiguation chips above the search results — clicking one instantly opens the AND-builder with that root pre-filled.</p>
HTML,
                        'ar' => <<<'HTML'
<p>اختصار عندما تعرف مسبقاً توليفة المفاهيم التي تبحث عنها (مثل آيات تتناول "الولادة" و"اليوم" معاً) دون التعمق يدوياً عبر "تظهر مع":</p>
<ol>
  <li>افتح تبويب <strong>🔀 بحث AND</strong>.</li>
  <li>اكتب الكلمة الأولى (بأي لغة مدعومة) — تعرض قائمة منسدلة الجذور العربية المطابقة مع معانيها، لأن كلمة واحدة قد تقابل عدة جذور مختلفة (مثل "لاحت" قد تعني "ظاهر/واضح" أو "ولدت" — جذران مختلفان).</li>
  <li>اختر الجذر المقصود ← يصبح رقاقة في منطقة أداة البناء.</li>
  <li>كرر للكلمة الثانية والثالثة وهكذا.</li>
  <li>اضغط بحث — النتيجة كل آية تحتوي جميع الجذور في تلك التوليفة معاً.</li>
</ol>
<p>إن كتبت كلمة في مربع البحث النصي العادي وصادف أنها تطابق جذرين مختلفين أو أكثر، يقترح التطبيق تلقائياً رقاقات لتوضيح المقصود فوق نتائج البحث — الضغط على إحداها يفتح فوراً أداة بناء AND مع ذلك الجذر معبأً مسبقاً.</p>
HTML,
                    ],
                ],
            ],
        ],

        // ============================================================
        [
            'id' => 'pengaturan-grup',
            'icon' => '⚙️',
            'title' => ['ar' => 'الإعدادات', 'en' => 'Settings', 'id' => 'Pengaturan'],
            'sections' => [
                [
                    'id' => 'pengaturan-lengkap',
                    'icon' => '🎛️',
                    'title' => ['ar' => 'كل خيارات الإعدادات', 'en' => 'Every Setting Explained', 'id' => 'Semua Opsi Pengaturan'],
                    'body' => [
                        'id' => <<<'HTML'
<p>Halaman Pengaturan (⚙️) dikelompokkan empat bagian:</p>
<ul>
  <li><strong>🎨 Tampilan</strong> — tema terang/gelap, ukuran font Arab, ukuran font terjemahan.</li>
  <li><strong>🌐 Bahasa</strong> — Bahasa UI (5 pilihan) dan Bahasa Terjemahan (2 pilihan) — lihat bagian berikut untuk perbedaannya.</li>
  <li><strong>📖 Tampilan Bacaan</strong> — toggle teks Arab/terjemahan/tajwid, Reading Mode (Penuh/Halaman), Browse Mode (Slider/List), dan pengaturan Mode Buku (ayat per halaman).</li>
  <li><strong>🔊 Audio</strong> — pilih qari default.</li>
</ul>
<p>Semua pengaturan tersimpan sebagai cookie di perangkatmu (bukan akun) — jadi tersimpan per-browser, dan akan hilang kalau kamu membersihkan cookie atau berpindah perangkat/browser.</p>
HTML,
                        'en' => <<<'HTML'
<p>The Settings page (⚙️) is grouped into four sections:</p>
<ul>
  <li><strong>🎨 Appearance</strong> — dark/light theme, Arabic font size, translation font size.</li>
  <li><strong>🌐 Language</strong> — UI Language (5 options) and Translation Language (2 options) — see the next section for the difference.</li>
  <li><strong>📖 Reading Display</strong> — Arabic text/translation/tajweed toggles, Reading Mode (Full/Paged), Browse Mode (Slider/List), and Book Mode settings (verses per page).</li>
  <li><strong>🔊 Audio</strong> — pick the default reciter.</li>
</ul>
<p>All settings are saved as cookies on your device (not tied to an account) — so they're per-browser, and will reset if you clear cookies or switch device/browser.</p>
HTML,
                        'ar' => <<<'HTML'
<p>تُقسّم صفحة الإعدادات (⚙️) إلى أربعة أقسام:</p>
<ul>
  <li><strong>🎨 المظهر</strong> — المظهر الداكن/الفاتح، حجم الخط العربي، حجم خط الترجمة.</li>
  <li><strong>🌐 اللغة</strong> — لغة الواجهة (5 خيارات) ولغة الترجمة (خياران) — راجع القسم التالي لمعرفة الفرق.</li>
  <li><strong>📖 عرض القراءة</strong> — مفاتيح النص العربي/الترجمة/التجويد، وضع القراءة (كامل/مُصفّح)، وضع التصفح (شرائح/قائمة)، وإعدادات وضع الكتاب (آيات لكل صفحة).</li>
  <li><strong>🔊 الصوت</strong> — اختيار القارئ الافتراضي.</li>
</ul>
<p>تُحفظ جميع الإعدادات كملفات تعريف ارتباط على جهازك (وليست مرتبطة بحساب) — فهي إذن خاصة بكل متصفح، وستُعاد ضبطها إن مسحت ملفات تعريف الارتباط أو بدّلت الجهاز/المتصفح.</p>
HTML,
                    ],
                ],
                [
                    'id' => 'hubungan-setting',
                    'icon' => '🔗',
                    'title' => ['ar' => 'علاقة الإعدادات بالقوائم', 'en' => 'How Settings Relate to Menus', 'id' => 'Hubungan Pengaturan & Menu'],
                    'body' => [
                        'id' => <<<'HTML'
<p>Beberapa pengaturan sengaja <strong>independen</strong> satu sama lain, dan ini sumber kebingungan paling umum — jadi penting dipahami:</p>
<ul>
  <li><strong>Bahasa UI ≠ Bahasa Terjemahan.</strong> Bahasa UI (5 pilihan, termasuk Sunda &amp; Jawa) mengatur teks menu/tombol/label di seluruh aplikasi, termasuk arti kata dasar &amp; terjemahan root di popup Info Kata. Bahasa Terjemahan (cuma Inggris/Indonesia) khusus mengatur bahasa <em>teks terjemahan ayat Al-Quran</em> yang tampil saat membaca. Kalau Bahasa UI-mu Sunda/Jawa, teks ayat tetap memakai salah satu dari Inggris/Indonesia (karena memang cuma dua itu yang tersedia untuk terjemahan ayat) — bukan bug, dua pengaturan ini memang untuk hal yang berbeda.</li>
  <li><strong>Reading Mode (Penuh/Halaman)</strong> mengatur halaman <em>surat biasa</em>, sama sekali <strong>tidak memengaruhi</strong> 📕 Mode Buku (yang punya pengaturan ayat-per-halaman sendiri) atau Browse Mode (yang mengatur grid kartu surat, bukan isi surat).</li>
  <li><strong>Toggle tampilan (Teks Arab / Terjemahan / Tajwid)</strong> berlaku di halaman surat mode biasa maupun Mode Buku — mematikan "Terjemahan" di sini akan menyembunyikannya di kedua tempat.</li>
  <li>Mengganti Bahasa UI lewat dropdown di toolbar dan lewat halaman Pengaturan → Bahasa adalah <strong>pengaturan yang persis sama</strong> — pakai yang mana saja, hasilnya identik dan langsung berlaku di seluruh menu.</li>
</ul>
HTML,
                        'en' => <<<'HTML'
<p>Some settings are deliberately <strong>independent</strong> of each other, and this is the most common source of confusion — worth understanding clearly:</p>
<ul>
  <li><strong>UI Language ≠ Translation Language.</strong> UI Language (5 options, including Sundanese &amp; Javanese) controls menu/button/label text app-wide, including root-word meanings shown in the Word Info popup. Translation Language (English/Indonesian only) specifically controls the language of the <em>Quran verse translation text</em> shown while reading. If your UI Language is Sundanese/Javanese, verse text still uses either English or Indonesian (since those are the only two available for verse translation) — that's not a bug, these two settings simply govern different things.</li>
  <li><strong>Reading Mode (Full/Paged)</strong> governs the <em>regular surah page</em>, and has <strong>no effect at all</strong> on 📕 Book Mode (which has its own verses-per-page setting) or Browse Mode (which controls the surah-card grid, not surah content).</li>
  <li><strong>Display toggles (Arabic Text / Translation / Tajweed)</strong> apply in both the regular surah page and Book Mode — turning off "Translation" here hides it in both places.</li>
  <li>Changing UI Language via the toolbar dropdown vs. via Settings → Language is <strong>the exact same setting</strong> — either one works, the result is identical and applies app-wide immediately.</li>
</ul>
HTML,
                        'ar' => <<<'HTML'
<p>بعض الإعدادات مستقلة عن بعضها <strong>عمداً</strong>، وهذا أكثر مصدر شائع للالتباس — يستحق فهماً واضحاً:</p>
<ul>
  <li><strong>لغة الواجهة ≠ لغة الترجمة.</strong> لغة الواجهة (5 خيارات، تشمل السوندانية والجاوية) تتحكم بنص القوائم/الأزرار/التسميات على مستوى التطبيق كله، بما في ذلك معاني الجذور المعروضة في نافذة معلومات الكلمة. لغة الترجمة (الإنجليزية/الإندونيسية فقط) تتحكم تحديداً بلغة <em>نص ترجمة آية القرآن</em> المعروض أثناء القراءة. إن كانت لغة واجهتك سوندانية/جاوية، يظل نص الآية يستخدم إما الإنجليزية أو الإندونيسية (لأنهما الوحيدتان المتاحتان لترجمة الآيات) — وهذا ليس خللاً، فهذان الإعدادان ببساطة يحكمان أموراً مختلفة.</li>
  <li><strong>وضع القراءة (كامل/مُصفّح)</strong> يحكم <em>صفحة السورة العادية</em>، و<strong>لا يؤثر إطلاقاً</strong> على 📕 وضع الكتاب (الذي له إعداد آيات-لكل-صفحة خاص به) أو وضع التصفح (الذي يتحكم بشبكة بطاقات السور، وليس محتوى السورة).</li>
  <li><strong>مفاتيح العرض (النص العربي / الترجمة / التجويد)</strong> تنطبق في كل من صفحة السورة العادية ووضع الكتاب — إيقاف "الترجمة" هنا يخفيها في كلا المكانين.</li>
  <li>تغيير لغة الواجهة عبر القائمة المنسدلة في الشريط العلوي مقابل صفحة الإعدادات ← اللغة هو <strong>نفس الإعداد تماماً</strong> — أيّهما يفي بالغرض، والنتيجة متطابقة وتُطبَّق فوراً على مستوى التطبيق.</li>
</ul>
HTML,
                    ],
                ],
            ],
        ],

        // ============================================================
        [
            'id' => 'data',
            'icon' => '🗄️',
            'title' => ['ar' => 'مصدر البيانات ومنهجيتها', 'en' => 'Data Source & Methodology', 'id' => 'Sumber & Metodologi Data'],
            'sections' => [
                [
                    'id' => 'sumber-teks',
                    'icon' => '📜',
                    'title' => ['ar' => 'مصدر النص والترجمة', 'en' => 'Text & Translation Sources', 'id' => 'Sumber Teks & Terjemahan'],
                    'body' => [
                        'id' => <<<'HTML'
<p>Teks Arab dan pembagian ayat mengikuti mushaf standar — <strong>6.236 ayat</strong> di <strong>114 surat</strong>, angka yang sama persis dengan mushaf cetak pada umumnya. Terjemahan Indonesia bersumber dari <strong>Kemenag</strong> (Kementerian Agama RI), terjemahan Inggris dari <strong>Saheeh International</strong> — kedua-duanya lengkap 6.236 ayat. Audio murottal disediakan lewat tautan langsung ke everyayah.com (dua qari, lihat bagian <a href="#audio-tafsir">Audio &amp; Tafsir</a>). Data tafsir masih berupa contoh terbatas (Ibn Kathir, sebagian kecil ayat), belum lengkap.</p>
HTML,
                        'en' => <<<'HTML'
<p>The Arabic text and verse divisions follow the standard mushaf — <strong>6,236 verses</strong> across <strong>114 surahs</strong>, matching common printed mushaf counts exactly. The Indonesian translation comes from <strong>Kemenag</strong> (Indonesia's Ministry of Religious Affairs), the English translation from <strong>Saheeh International</strong> — both cover the full 6,236 verses. Recitation audio is streamed from everyayah.com (two reciters, see <a href="#audio-tafsir">Audio &amp; Tafsir</a>). Tafsir data is still a limited sample (Ibn Kathir, a small subset of verses), not yet complete.</p>
HTML,
                        'ar' => <<<'HTML'
<p>يتبع النص العربي وتقسيم الآيات المصحف القياسي — <strong>6236 آية</strong> عبر <strong>114 سورة</strong>، مطابقة تماماً لأعداد المصاحف المطبوعة الشائعة. تأتي الترجمة الإندونيسية من <strong>وزارة الشؤون الدينية الإندونيسية (Kemenag)</strong>، والترجمة الإنجليزية من <strong>Saheeh International</strong> — وتغطي كلتاهما الآيات الـ6236 كاملة. تُبثّ صوتيات التلاوة من everyayah.com (قارئان، راجع <a href="#audio-tafsir">الصوت والتفسير</a>). بيانات التفسير لا تزال عينة محدودة (ابن كثير، مجموعة فرعية صغيرة من الآيات)، وليست مكتملة بعد.</p>
HTML,
                    ],
                ],
                [
                    'id' => 'model-data',
                    'icon' => '🕸️',
                    'title' => ['ar' => 'نموذج البيانات: العلاقات', 'en' => 'The Data Model: Relationships', 'id' => 'Model Data: Relasi Kata'],
                    'body' => [
                        'id' => <<<'HTML'
<p>Inti dari fitur "Muncul Bersama" adalah satu tabel relasi sederhana yang menghubungkan <strong>ayat</strong> dengan <strong>akar kata</strong>: untuk tiap kemunculan sebuah akar di sebuah ayat, tersimpan satu baris. Saat ini tabel itu berisi <strong>50.272 baris relasi</strong>, menghubungkan <strong>6.236 ayat</strong> dengan <strong>1.655 akar kata dasar</strong> berbeda — inilah yang dimaksud dengan "6.236 ayat × jumlah akar kata" di deskripsi model: bukan perkalian matematis literal, tapi ukuran matriks jarang (sparse matrix) ayat-vs-akar yang mendasari seluruh fitur pencarian relasi di aplikasi ini.</p>
<p>Dari matriks inilah <a href="#algoritma-model">algoritma pembentukan model</a> dan <a href="#algoritma-cari">algoritma pencarian kombinasi</a> bekerja — keduanya dijelaskan di bagian tersendiri di bawah.</p>
HTML,
                        'en' => <<<'HTML'
<p>At the heart of the "Appears With" feature is one simple relation table linking <strong>verses</strong> to <strong>root words</strong>: for every occurrence of a root in a verse, one row is stored. Today that table holds <strong>50,272 relation rows</strong>, connecting <strong>6,236 verses</strong> to <strong>1,655 distinct root words</strong> — this is what "6,236 verses × number of roots" means in the model's description: not a literal mathematical product, but the size of the sparse verse-by-root matrix underlying every relationship-search feature in this app.</p>
<p>This matrix is what the <a href="#algoritma-model">model-building algorithm</a> and the <a href="#algoritma-cari">combination search algorithm</a> operate on — both explained in their own sections below.</p>
HTML,
                        'ar' => <<<'HTML'
<p>جوهر ميزة "تظهر مع" هو جدول علاقة بسيط واحد يربط <strong>الآيات</strong> <strong>بالجذور</strong>: لكل ظهور لجذر في آية، يُخزَّن صف واحد. يحتوي هذا الجدول اليوم على <strong>50272 صف علاقة</strong>، تربط <strong>6236 آية</strong> بـ<strong>1655 جذراً</strong> مختلفاً — هذا ما يُقصد بـ"6236 آية × عدد الجذور" في وصف النموذج: ليس ضرباً حسابياً حرفياً، بل حجم مصفوفة متناثرة (آية × جذر) تقوم عليها كل ميزة بحث عن العلاقات في هذا التطبيق.</p>
<p>على هذه المصفوفة تعمل <a href="#algoritma-model">خوارزمية بناء النموذج</a> و<a href="#algoritma-cari">خوارزمية بحث التوليفات</a> — وكلتاهما مشروحتان في قسميهما الخاصين أدناه.</p>
HTML,
                    ],
                ],
                [
                    'id' => 'sumber-root',
                    'icon' => '📥',
                    'title' => ['ar' => 'مصدر الجذور وتنقيحها', 'en' => 'Root Word Source & Curation', 'id' => 'Sumber & Kurasi Root Words'],
                    'body' => [
                        'id' => <<<'HTML'
<p>Dari <strong>1.655 akar kata</strong> yang dipakai aplikasi ini:</p>
<ul>
  <li><strong>160 akar</strong> adalah kurasi manual awal (label <code>curated</code> di database) — akar-akar paling umum, artinya ditulis tangan sejak versi awal aplikasi.</li>
  <li><strong>1.495 akar</strong> (label <code>imported</code>) diimpor dari proyek data terbuka <a href="https://github.com/mustafa0x/quran-morphology" target="_blank" rel="noopener">mustafa0x/quran-morphology</a> di GitHub — sebuah fork dari <em>Quranic Arabic Corpus v0.4</em> (lisensi GPLv3). Proses impor mem-parse <strong>77.429 entri kata</strong> dari seluruh Al-Quran, menandai <strong>50.268 di antaranya</strong> dengan akar Arab, menghasilkan 1.651 akar unik (1.495 di antaranya baru, sisanya sudah ada di 160 kurasi awal).</li>
</ul>
<p><strong>Semua 1.655 akar kini sudah 100% punya arti</strong> (Indonesia &amp; Inggris) — 1.495 akar hasil impor tadinya tidak punya arti sama sekali (Quranic Arabic Corpus fokus ke analisis morfologi, bukan kamus arti), diisi lewat alat internal <strong>"Kata Kurator"</strong>: sebuah tool berbasis AI yang mengambil konteks nyata tiap akar (kata-kata turunannya + contoh ayat & terjemahan tempat ia muncul) sebagai <em>grounding</em>, lalu menghasilkan draf arti lewat salah satu dari 4 provider AI yang bisa dipilih (Ollama Cloud, OpenRouter, OpenAI, atau Ollama lokal) — hasilnya tetap ditinjau/diedit di tabel sebelum diterapkan ke database.</p>
HTML,
                        'en' => <<<'HTML'
<p>Of the <strong>1,655 root words</strong> the app uses:</p>
<ul>
  <li><strong>160 roots</strong> are the original manual curation (tagged <code>curated</code> in the database) — the most common roots, hand-written since the app's early versions.</li>
  <li><strong>1,495 roots</strong> (tagged <code>imported</code>) were imported from the open-data project <a href="https://github.com/mustafa0x/quran-morphology" target="_blank" rel="noopener">mustafa0x/quran-morphology</a> on GitHub — a fork of the <em>Quranic Arabic Corpus v0.4</em> (GPLv3-licensed). The import parsed <strong>77,429 word entries</strong> across the whole Quran, tagged <strong>50,268 of them</strong> with an Arabic root, yielding 1,651 distinct roots (1,495 of which were new, the rest already existed among the original 160).</li>
</ul>
<p><strong>All 1,655 roots now have a meaning</strong> (Indonesian &amp; English) — the 1,495 imported roots originally had none at all (the Quranic Arabic Corpus focuses on morphological analysis, not a meaning dictionary), filled in via an internal tool called <strong>"Kata Kurator"</strong> ("Word Curator"): an AI-assisted tool that pulls real context for each root (its derived word forms + sample verses &amp; translations where it appears) as grounding, then drafts a meaning through one of 4 selectable AI providers (Ollama Cloud, OpenRouter, OpenAI, or local Ollama) — the results are still reviewed/edited in a table before being applied to the database.</p>
HTML,
                        'ar' => <<<'HTML'
<p>من أصل <strong>1655 جذراً</strong> يستخدمها التطبيق:</p>
<ul>
  <li><strong>160 جذراً</strong> هي التنقيح اليدوي الأصلي (موسومة <code>curated</code> في قاعدة البيانات) — الجذور الأكثر شيوعاً، كُتبت يدوياً منذ الإصدارات الأولى للتطبيق.</li>
  <li><strong>1495 جذراً</strong> (موسومة <code>imported</code>) استُوردت من مشروع بيانات مفتوح على GitHub باسم <a href="https://github.com/mustafa0x/quran-morphology" target="_blank" rel="noopener">mustafa0x/quran-morphology</a> — وهو تفرّع من <em>Quranic Arabic Corpus v0.4</em> (برخصة GPLv3). حلّلت عملية الاستيراد <strong>77429 مدخل كلمة</strong> عبر القرآن كله، ووسمت <strong>50268 منها</strong> بجذر عربي، فنتج 1651 جذراً متمايزاً (1495 منها جديدة، والباقي موجود مسبقاً ضمن الـ160 الأصلية).</li>
</ul>
<p><strong>لجميع الجذور الـ1655 الآن معنى</strong> (بالإندونيسية والإنجليزية) — الجذور المستوردة الـ1495 لم يكن لها معنى إطلاقاً في البداية (يركز Quranic Arabic Corpus على التحليل الصرفي، لا على معجم معانٍ)، فمُلئت عبر أداة داخلية تُدعى <strong>"Kata Kurator"</strong> ("منسّق الكلمات"): أداة بمساعدة الذكاء الاصطناعي تسحب سياقاً حقيقياً لكل جذر (أشكاله المشتقة + آيات وترجمات نموذجية يظهر فيها) كأساس استرشادي، ثم تصوغ معنى مبدئياً عبر أحد 4 مزودي ذكاء اصطناعي قابلين للاختيار (Ollama Cloud، OpenRouter، OpenAI، أو Ollama محلي) — وتُراجَع النتائج وتُحرَّر في جدول قبل تطبيقها على قاعدة البيانات.</p>
HTML,
                    ],
                    'deep_dive' => [
                        'id' => <<<'HTML'
<p>Detail teknis proses impor: setiap kata di corpus sumber tertaut ke sebuah ayat lewat ID ayat yang <strong>dihitung deterministik</strong> dari offset per surat (bukan disimpan eksplisit di corpus sumber) — diverifikasi 100% cocok dengan data ayat yang sudah ada di database aplikasi ini sebelum proses impor dijalankan. Berkat ini, tabel <code>ayah_root_words</code> yang jadi fondasi seluruh fitur "Muncul Bersama" ternyata sudah ada di skema database sejak awal (nyaris kosong sebelumnya, hanya 7 baris warisan) — tinggal diisi, tidak perlu tabel baru. Kolom <code>source</code> (<code>curated</code>/<code>imported</code>) pada tabel akar kata dipakai di beberapa tempat: sebagai salah satu kriteria tie-break saat mengurutkan hasil pencarian (akar kurasi menang atas impor bila skor kecocokan sama), dan untuk mengecualikan alat "Kata Kurator" dari file deploy publik (tool ini hanya untuk pengelolaan lokal, tidak pernah ikut dideploy ke server produksi).</p>
HTML,
                        'en' => <<<'HTML'
<p>Import process detail: every word in the source corpus links to a verse via a verse ID that is <strong>deterministically computed</strong> from a per-surah offset (it isn't stored explicitly in the source corpus) — verified to match 100% against this app's own existing verse data before the import ran. Thanks to that, the <code>ayah_root_words</code> table underlying the entire "Appears With" feature turned out to already exist in the database schema from the start (nearly empty before, just 7 legacy rows) — it only needed populating, no new table required. The <code>source</code> column (<code>curated</code>/<code>imported</code>) on the root-word table is used in a few places: as a tie-break criterion when sorting search results (curated roots beat imported ones on an equal match score), and to exclude the "Kata Kurator" tool from the public deploy (that tool is for local management only, never shipped to the production server).</p>
HTML,
                        'ar' => <<<'HTML'
<p>تفصيل تقني لعملية الاستيراد: تُربط كل كلمة في المدونة المصدرية بآية عبر معرّف آية <strong>يُحسب حتمياً</strong> من إزاحة لكل سورة (لا يُخزَّن صراحة في المدونة المصدرية) — وتم التحقق من تطابقه 100% مع بيانات الآيات الموجودة مسبقاً في قاعدة بيانات هذا التطبيق قبل تشغيل الاستيراد. بفضل ذلك، تبيّن أن جدول <code>ayah_root_words</code> الذي تقوم عليه ميزة "تظهر مع" بأكملها كان موجوداً أصلاً في مخطط قاعدة البيانات منذ البداية (شبه فارغ سابقاً، 7 صفوف قديمة فقط) — احتاج فقط للتعبئة، دون الحاجة لجدول جديد. يُستخدم عمود <code>source</code> (<code>curated</code>/<code>imported</code>) في جدول الجذور في عدة مواضع: كمعيار فاصل عند ترتيب نتائج البحث (تتفوق الجذور المنقّحة على المستوردة عند تساوي درجة التطابق)، ولاستبعاد أداة "Kata Kurator" من النشر العام (هذه الأداة للإدارة المحلية فقط، ولم تُنشر يوماً على خادم الإنتاج).</p>
HTML,
                    ],
                ],
                [
                    'id' => 'sinonim-antonim',
                    'icon' => '🔄',
                    'title' => ['ar' => 'المرادفات والأضداد', 'en' => 'Synonyms & Antonyms', 'id' => 'Sinonim & Antonim'],
                    'body' => [
                        'id' => <<<'HTML'
<p>Berbeda dari data "Muncul Bersama" (dihitung otomatis dari 50.272 baris kemunculan ayat), relasi <strong>Sinonim, Antonim, dan Kata Terkait</strong> antar-akar adalah hasil <strong>kurasi manual murni</strong> — saat ini berjumlah 13 pasangan sinonim, 25 pasangan antonim, dan 8 pasangan kata terkait. Jumlahnya sengaja masih kecil: kurasi semantik seperti ini butuh penilaian bahasa yang cermat (dua akar bisa "mirip" secara harfiah tapi sangat berbeda makna kontekstualnya dalam Al-Quran), jadi ditambah bertahap, bukan digenerate massal secara otomatis seperti kata turunan.</p>
HTML,
                        'en' => <<<'HTML'
<p>Unlike "Appears With" data (computed automatically from the 50,272 verse-occurrence rows), the <strong>Synonym, Antonym, and Related</strong> relations between roots are <strong>purely manually curated</strong> — currently 13 synonym pairs, 25 antonym pairs, and 8 related-word pairs. The count is deliberately small: this kind of semantic curation needs careful linguistic judgment (two roots can look "similar" literally but mean very different things in Quranic context), so it's added gradually rather than bulk-generated automatically the way derived word forms are.</p>
HTML,
                        'ar' => <<<'HTML'
<p>بخلاف بيانات "تظهر مع" (المحسوبة تلقائياً من 50272 صف ظهور في الآيات)، فإن علاقات <strong>المرادفات والأضداد والكلمات ذات الصلة</strong> بين الجذور هي نتاج <strong>تنقيح يدوي بحت</strong> — تبلغ حالياً 13 زوجاً من المرادفات، و25 زوجاً من الأضداد، و8 أزواج من الكلمات ذات الصلة. العدد صغير عمداً: يحتاج هذا النوع من التنقيح الدلالي حكماً لغوياً دقيقاً (قد يبدو جذران "متشابهين" حرفياً لكن معناهما مختلف جداً في السياق القرآني)، فيُضاف تدريجياً وليس توليداً آلياً جماعياً كما هو الحال مع أشكال الكلمات المشتقة.</p>
HTML,
                    ],
                ],
                [
                    'id' => 'algoritma-model',
                    'icon' => '⚙️',
                    'title' => ['ar' => 'خوارزمية بناء النموذج', 'en' => 'Model-Building Algorithm', 'id' => 'Algoritma Pembentukan Model'],
                    'body' => [
                        'id' => <<<'HTML'
<p>"Model" di sini bukan model machine-learning yang dilatih — melainkan tabel relasi ayat↔akar (<code>ayah_root_words</code>) yang dibangun sekali lewat proses impor+kurasi (lihat bagian sebelumnya), lalu dipakai apa adanya oleh algoritma pencarian di runtime. Tidak ada pelatihan ulang, tidak ada bobot yang disesuaikan — murni tabel lookup terstruktur.</p>
HTML,
                        'en' => <<<'HTML'
<p>The "model" here isn't a trained machine-learning model — it's the verse↔root relation table (<code>ayah_root_words</code>) built once through the import+curation process (see the previous section), then used as-is by the search algorithms at runtime. There's no retraining, no adjustable weights — it's purely a structured lookup table.</p>
HTML,
                        'ar' => <<<'HTML'
<p>"النموذج" هنا ليس نموذج تعلم آلي مدرَّباً — بل هو جدول علاقة آية↔جذر (<code>ayah_root_words</code>) بُني مرة واحدة عبر عملية الاستيراد والتنقيح (راجع القسم السابق)، ثم يُستخدم كما هو من قبل خوارزميات البحث وقت التشغيل. لا إعادة تدريب، ولا أوزان قابلة للتعديل — إنه جدول بحث منظم بحت.</p>
HTML,
                    ],
                    'deep_dive' => [
                        'id' => <<<'HTML'
<p>Fungsi inti: <code>root_co_occurrence($rootIds)</code>. Diberi satu set ID akar (bisa 1, bisa lebih — inilah yang membuatnya bisa dipakai untuk drill-down berjenjang, bukan cuma level pertama):</p>
<ol>
  <li>Cari semua ayat yang memuat <strong>SEMUA</strong> akar dalam set itu (<code>GROUP BY ayah_id HAVING COUNT(DISTINCT root_word_id) = k</code>, di mana k = jumlah akar dalam set).</li>
  <li>Dari ayat-ayat itu, cari akar-akar LAIN yang muncul di dalamnya (di luar set awal), dihitung berapa kali masing-masing — inilah daftar "kandidat level berikutnya".</li>
  <li>Sekaligus tandai ayat mana yang PERSIS hanya memuat set akar itu (tidak ada akar lain sama sekali) — inilah opsi "Hanya kombinasi ini".</li>
</ol>
<p>Daftar kandidat di langkah 2 <strong>sengaja tidak dibatasi top-N</strong> — semua akar yang pernah muncul bersama ditampilkan, bahkan yang cuma sekali, karena daftar ini dimaksudkan lengkap, bukan cuplikan. Dengan hanya 1.655 akar total, ini paling banyak menghasilkan ~1.654 baris bahkan untuk akar tersibuk — query tetap cepat (puluhan milidetik, diuji dengan akar tersibuk di seluruh Quran, أله/"Allah" dengan 2.851 kemunculan, yang menghasilkan 1.127 akar terkait dalam waktu pemrosesan murni ~178,5ms).</p>
HTML,
                        'en' => <<<'HTML'
<p>Core function: <code>root_co_occurrence($rootIds)</code>. Given a set of root IDs (can be 1, can be more — this is what lets it power multi-level drill-down, not just the first level):</p>
<ol>
  <li>Find every verse containing <strong>ALL</strong> roots in that set (<code>GROUP BY ayah_id HAVING COUNT(DISTINCT root_word_id) = k</code>, where k = the number of roots in the set).</li>
  <li>From those verses, find OTHER roots appearing in them (outside the original set), counted per root — this is the "next level candidates" list.</li>
  <li>At the same time, flag which of those verses contain EXACTLY that root set (no other root at all) — this powers the "Only this combination" option.</li>
</ol>
<p>The candidate list from step 2 is <strong>deliberately not capped at a top-N</strong> — every root that ever co-occurred is shown, even ones seen only once, because this list is meant to be complete, not a preview. With only 1,655 roots total, that's at most ~1,654 rows even for the busiest root — the query stays fast (tens of milliseconds; tested against the single busiest root in the whole Quran, أله/"Allah" with 2,851 occurrences, which yields 1,127 related roots in ~178.5ms of pure processing time).</p>
HTML,
                        'ar' => <<<'HTML'
<p>الدالة الأساسية: <code>root_co_occurrence($rootIds)</code>. بإعطائها مجموعة من معرّفات الجذور (قد تكون 1 أو أكثر — وهذا ما يمكّنها من دعم التعمق متعدد المستويات، وليس المستوى الأول فقط):</p>
<ol>
  <li>إيجاد كل آية تحتوي <strong>جميع</strong> الجذور في تلك المجموعة (<code>GROUP BY ayah_id HAVING COUNT(DISTINCT root_word_id) = k</code>، حيث k = عدد الجذور في المجموعة).</li>
  <li>من تلك الآيات، إيجاد جذور <strong>أخرى</strong> تظهر فيها (خارج المجموعة الأصلية)، مع عدّ كل جذر — وهذه هي قائمة "مرشحي المستوى التالي".</li>
  <li>في الوقت نفسه، تُوسم الآيات التي تحتوي بالضبط تلك المجموعة من الجذور (دون أي جذر آخر إطلاقاً) — وهذا ما يشغّل خيار "هذا المزيج فقط".</li>
</ol>
<p>قائمة المرشحين في الخطوة 2 <strong>غير محدودة عمداً بأفضل-N</strong> — يُعرض كل جذر ظهر معاً ولو مرة واحدة، لأن هذه القائمة يُقصد بها أن تكون كاملة، لا معاينة. بوجود 1655 جذراً فقط إجمالاً، هذا أقصى ما يكون حوالي 1654 صفاً حتى لأكثر الجذور ازدحاماً — يبقى الاستعلام سريعاً (عشرات المللي ثانية؛ اختُبر مقابل أكثر جذر ازدحاماً في القرآن كله، أله بـ2851 ظهوراً، وأنتج 1127 جذراً مرتبطاً في زمن معالجة صافٍ نحو 178.5 مللي ثانية).</p>
HTML,
                    ],
                ],
                [
                    'id' => 'algoritma-cari',
                    'icon' => '🔎',
                    'title' => ['ar' => 'خوارزميات البحث', 'en' => 'Search Algorithms', 'id' => 'Algoritma Pencarian'],
                    'body' => [
                        'id' => <<<'HTML'
<p>Beberapa algoritma pencarian yang berbeda bekerja untuk mode yang berbeda-beda — bukan satu algoritma serba-bisa:</p>
<ul>
  <li><strong>Pencarian Teks</strong> — memakai <strong>SQLite FTS5</strong> (full-text search bawaan mesin database), dengan indeks terpisah untuk teks Arab dan tiap bahasa terjemahan, diurutkan berdasar skor <code>rank</code> bawaan FTS5.</li>
  <li><strong>Pencarian Kata Dasar</strong> — TIDAK memakai tabel <code>ayah_root_words</code> maupun FTS5, melainkan pencocokan morfologi ringan langsung saat itu juga (lihat Detail Teknis di bagian <a href="#cari-akar">Pencarian Kata Dasar</a>).</li>
  <li><strong>Muncul Bersama &amp; Pencarian AND</strong> — sama-sama memakai <code>root_co_occurrence()</code> di atas tabel <code>ayah_root_words</code> (lihat bagian <a href="#algoritma-model">Algoritma Pembentukan Model</a>) — builder AND secara harfiah memakai ulang mesin yang sama persis dengan mode drill-down "Muncul Bersama", cuma jalan masuknya (UI) yang berbeda.</li>
  <li><strong>Pencocokan kata → arti akar</strong> (dipakai di popup Info Kata &amp; dropdown builder AND) — algoritma tersendiri yang mencari kata kunci di dalam teks ARTI tiap akar (bukan di kata Arab-nya), dengan skor lebih tinggi kalau kata itu muncul lebih awal dalam definisi.</li>
</ul>
HTML,
                        'en' => <<<'HTML'
<p>Several different search algorithms serve different modes — not one do-everything algorithm:</p>
<ul>
  <li><strong>Text Search</strong> — uses <strong>SQLite FTS5</strong> (the database engine's built-in full-text search), with separate indexes for Arabic text and each translation language, ranked by FTS5's built-in <code>rank</code> score.</li>
  <li><strong>Root Word Search</strong> — does NOT use the <code>ayah_root_words</code> table or FTS5 at all; it's live light-morphology matching (see the Technical Detail in the <a href="#cari-akar">Root Word Search</a> section).</li>
  <li><strong>"Appears With" &amp; AND Search</strong> — both use the same <code>root_co_occurrence()</code> function over the <code>ayah_root_words</code> table (see <a href="#algoritma-model">Model-Building Algorithm</a>) — the AND builder literally reuses the exact same engine as the "Appears With" drill-down, just with a different entry point (UI).</li>
  <li><strong>Word → root-meaning matching</strong> (used in the Word Info popup &amp; the AND-builder's dropdown) — a separate algorithm that searches for the typed word inside each root's MEANING text (not its Arabic form), scoring higher when the word appears earlier in the definition.</li>
</ul>
HTML,
                        'ar' => <<<'HTML'
<p>تخدم عدة خوارزميات بحث مختلفة أوضاعاً مختلفة — وليست خوارزمية واحدة تقوم بكل شيء:</p>
<ul>
  <li><strong>البحث النصي</strong> — يستخدم <strong>SQLite FTS5</strong> (البحث النصي الكامل المدمج في محرك قاعدة البيانات)، بفهارس منفصلة للنص العربي ولكل لغة ترجمة، مرتبة حسب درجة <code>rank</code> المدمجة في FTS5.</li>
  <li><strong>البحث بالجذر</strong> — لا يستخدم جدول <code>ayah_root_words</code> ولا FTS5 إطلاقاً؛ بل مطابقة صرفية خفيفة حية (راجع التفاصيل التقنية في قسم <a href="#cari-akar">البحث بالجذر</a>).</li>
  <li><strong>"تظهر مع" وبحث AND</strong> — يستخدمان نفس دالة <code>root_co_occurrence()</code> فوق جدول <code>ayah_root_words</code> (راجع <a href="#algoritma-model">خوارزمية بناء النموذج</a>) — تعيد أداة بناء AND استخدام نفس المحرك حرفياً المستخدم في التعمق عبر "تظهر مع"، فقط بمدخل واجهة مختلف.</li>
  <li><strong>مطابقة الكلمة بمعنى الجذر</strong> (تُستخدم في نافذة معلومات الكلمة وقائمة أداة بناء AND) — خوارزمية منفصلة تبحث عن الكلمة المكتوبة داخل نص معنى كل جذر (وليس شكله العربي)، وتمنح درجة أعلى عند ظهور الكلمة مبكراً في التعريف.</li>
</ul>
HTML,
                    ],
                    'deep_dive' => [
                        'id' => <<<'HTML'
<p>Detail algoritma pencocokan kata→arti (<code>match_roots_by_gloss()</code>), dipakai untuk menjawab "kata X ini artinya salah satu akar apa saja?":</p>
<ol>
  <li>Pecah teks arti tiap akar (Indonesia DAN Inggris) jadi kata-kata individual.</li>
  <li>Untuk tiap akar, cari posisi PALING AWAL kata kunci itu muncul (posisi 0 = kata pertama dalam definisi) — kalau muncul lebih dari posisi ke-6, akar itu <strong>dibuang total dari hasil</strong> (bukan cuma diberi skor rendah) karena kemunculan sejauh itu biasanya cuma "numpang lewat" di kalimat penjelasan, bukan makna inti kata tersebut.</li>
  <li>Skor = <code>7 - posisi</code> (makin awal makin tinggi skor).</li>
  <li>Urutkan hasil: skor tertinggi dulu, lalu akar <code>curated</code> menang atas <code>imported</code> pada skor sama, lalu frekuensi kemunculan di Al-Quran sebagai tie-break terakhir.</li>
</ol>
<p>Algoritma ini sama persis dipakai di tiga tempat: popup Info Kata (memilih akar paling relevan saat kata ambigu), endpoint pencarian akar untuk builder AND, dan prompt disambiguasi otomatis di atas hasil pencarian teks — bukan tiga implementasi terpisah yang kebetulan mirip.</p>
HTML,
                        'en' => <<<'HTML'
<p>Detail on the word→meaning matching algorithm (<code>match_roots_by_gloss()</code>), used to answer "which root(s) could this typed word mean?":</p>
<ol>
  <li>Split each root's meaning text (both Indonesian AND English) into individual words.</li>
  <li>For each root, find the EARLIEST position the query word appears (position 0 = the first word of the definition) — if it only appears past position 6, that root is <strong>dropped from the results entirely</strong> (not just scored low), because a match that far in usually means the word is just passing through an explanatory sentence, not the word's core meaning.</li>
  <li>Score = <code>7 - position</code> (earlier = higher score).</li>
  <li>Sort results: highest score first, then <code>curated</code> roots beat <code>imported</code> ones on a tied score, then Quran occurrence frequency as the final tie-break.</li>
</ol>
<p>This exact same algorithm is reused in three places: the Word Info popup (picking the most relevant root for an ambiguous word), the root-search endpoint powering the AND builder, and the automatic disambiguation prompt above text search results — not three separate implementations that happen to look similar.</p>
HTML,
                        'ar' => <<<'HTML'
<p>تفصيل خوارزمية مطابقة الكلمة بالمعنى (<code>match_roots_by_gloss()</code>)، المستخدمة للإجابة عن "أي جذر (جذور) قد تعنيه هذه الكلمة المكتوبة؟":</p>
<ol>
  <li>تقسيم نص معنى كل جذر (بالإندونيسية والإنجليزية معاً) إلى كلمات فردية.</li>
  <li>لكل جذر، إيجاد أبكر موضع تظهر فيه كلمة البحث (الموضع 0 = أول كلمة في التعريف) — إن ظهرت فقط بعد الموضع السادس، يُستبعد ذلك الجذر <strong>كلياً من النتائج</strong> (وليس مجرد درجة منخفضة)، لأن تطابقاً بهذا البعد يعني عادة أن الكلمة تمر عرضاً في جملة تفسيرية، لا أنها المعنى الجوهري للجذر.</li>
  <li>الدرجة = <code>7 - الموضع</code> (كلما بكّر ارتفعت الدرجة).</li>
  <li>ترتيب النتائج: الأعلى درجة أولاً، ثم تتفوق الجذور <code>curated</code> على <code>imported</code> عند تساوي الدرجة، ثم تكرار الظهور في القرآن كفاصل أخير.</li>
</ol>
<p>تُعاد هذه الخوارزمية بعينها في ثلاثة مواضع: نافذة معلومات الكلمة (اختيار الجذر الأنسب لكلمة غامضة)، ونقطة نهاية البحث بالجذر التي تشغّل أداة بناء AND، واقتراح التوضيح التلقائي فوق نتائج البحث النصي — وليست ثلاثة تطبيقات منفصلة تتشابه صدفة.</p>
HTML,
                    ],
                ],
                [
                    'id' => 'disclaimer',
                    'icon' => '⚠️',
                    'title' => ['ar' => 'إخلاء المسؤولية والحدود', 'en' => 'Disclaimer & Limitations', 'id' => 'Disclaimer & Keterbatasan'],
                    'body' => [
                        'id' => <<<'HTML'
<p>Beberapa batasan penting untuk dipahami saat memakai fitur pencarian akar/kata dasar:</p>
<ul>
  <li>Pencocokan akar/kata turunan bersifat <strong>pendekatan otomatis heuristik, bukan analisis morfologi resmi yang diverifikasi ahli bahasa</strong>. Kemungkinan ada positif palsu (kata kebetulan cocok pola tapi bukan turunan akar itu) atau negatif palsu (turunan sah yang tidak terdeteksi pola).</li>
  <li>Arti 1.495 akar hasil impor dihasilkan dengan bantuan AI (lihat <a href="#sumber-root">Sumber &amp; Kurasi Root Words</a>) — walau ditinjau, tetap mungkin ada ketidaktepatan nuansa dibanding kamus/tafsir akademis.</li>
  <li>Data tafsir sangat terbatas (contoh, bukan lengkap 6.236 ayat).</li>
  <li>Relasi Sinonim/Antonim/Kata Terkait masih sedikit (kurasi manual bertahap, lihat <a href="#sinonim-antonim">Sinonim &amp; Antonim</a>) — banyak akar belum punya relasi semacam ini sama sekali, bukan berarti benar-benar tidak punya sinonim/antonim di kenyataan.</li>
</ul>
<p>Aplikasi ini adalah <strong>alat bantu eksplorasi &amp; belajar</strong>, bukan pengganti rujukan tafsir, kamus, atau ahli bahasa Arab/ilmu Al-Quran yang berkompeten.</p>
HTML,
                        'en' => <<<'HTML'
<p>A few important limitations to keep in mind when using the root/derived-word search features:</p>
<ul>
  <li>Root/derived-form matching is an <strong>automatic heuristic approximation, not linguist-verified formal morphological analysis</strong>. There can be false positives (a word coincidentally matches the pattern without truly deriving from that root) or false negatives (a genuine derivation the pattern fails to catch).</li>
  <li>The meanings of the 1,495 imported roots were drafted with AI assistance (see <a href="#sumber-root">Root Word Source &amp; Curation</a>) — even after review, there may still be nuance inaccuracies compared to academic dictionaries/tafsir.</li>
  <li>Tafsir data is very limited (a sample, not the complete 6,236 verses).</li>
  <li>Synonym/Antonym/Related relations are still few (gradual manual curation, see <a href="#sinonim-antonim">Synonyms &amp; Antonyms</a>) — many roots have no such relation recorded at all yet, which doesn't mean they genuinely have no synonyms/antonyms in reality.</li>
</ul>
<p>This app is a <strong>learning &amp; exploration aid</strong>, not a substitute for tafsir references, dictionaries, or a competent Arabic/Quranic-studies scholar.</p>
HTML,
                        'ar' => <<<'HTML'
<p>بضع قيود مهمة يجب مراعاتها عند استخدام ميزات البحث بالجذر/الكلمة المشتقة:</p>
<ul>
  <li>مطابقة الجذر/الشكل المشتق هي <strong>تقريب استدلالي آلي، وليست تحليلاً صرفياً رسمياً موثّقاً من لغويين</strong>. قد توجد إيجابيات زائفة (كلمة تطابق النمط صدفة دون أن تكون مشتقة فعلاً من ذلك الجذر) أو سلبيات زائفة (اشتقاق حقيقي يفوت النمط اكتشافه).</li>
  <li>صيغت معاني الجذور المستوردة الـ1495 بمساعدة الذكاء الاصطناعي (راجع <a href="#sumber-root">مصدر الجذور وتنقيحها</a>) — وحتى بعد المراجعة، قد تبقى فروق دقيقة غير مطابقة تماماً للمعاجم/التفاسير الأكاديمية.</li>
  <li>بيانات التفسير محدودة جداً (عينة، وليست الآيات الـ6236 كاملة).</li>
  <li>علاقات المرادفات/الأضداد/ذات الصلة لا تزال قليلة (تنقيح يدوي تدريجي، راجع <a href="#sinonim-antonim">المرادفات والأضداد</a>) — لم تُسجَّل بعد لكثير من الجذور مثل هذه العلاقة إطلاقاً، وهذا لا يعني أنها فعلاً بلا مرادفات/أضداد في الواقع.</li>
</ul>
<p>هذا التطبيق أداة <strong>مساعدة للتعلم والاستكشاف</strong>، وليس بديلاً عن مراجع التفسير أو المعاجم أو عالم مختص باللغة العربية/الدراسات القرآنية.</p>
HTML,
                    ],
                ],
            ],
        ],

        // ============================================================
        [
            'id' => 'faq',
            'icon' => '❓',
            'title' => ['ar' => 'الأسئلة الشائعة', 'en' => 'FAQ', 'id' => 'FAQ'],
            'sections' => [
                [
                    'id' => 'faq-utama',
                    'icon' => '💬',
                    'title' => ['ar' => 'أسئلة متكررة', 'en' => 'Frequently Asked Questions', 'id' => 'Pertanyaan Umum'],
                    'body' => [
                        'id' => <<<'HTML'
<p><strong>Kenapa arti kata di popup Info Kata kadang beda bahasa dari menu?</strong><br>Cek pengaturan Bahasa UI-mu — arti kata dasar/root SELALU mengikuti Bahasa UI, bukan Bahasa Terjemahan. Lihat <a href="#hubungan-setting">Hubungan Pengaturan &amp; Menu</a>.</p>
<p><strong>Kenapa hasil "Cari Kata Dasar" saya menemukan kata yang kelihatannya tidak berhubungan?</strong><br>Pencocokan akar bersifat pendekatan otomatis (lihat <a href="#cari-akar">Pencarian Kata Dasar</a> dan <a href="#disclaimer">Disclaimer</a>) — sesekali ada positif palsu.</p>
<p><strong>Bookmark saya hilang setelah ganti browser/HP.</strong><br>Bookmark tersimpan sebagai data lokal per-browser (kecuali kamu login dan menyimpannya ke akun, jika fitur itu tersedia) — pindah browser/perangkat tanpa login bisa membuatnya tidak terbawa.</p>
<p><strong>Kenapa daftar tafsir kosong di kebanyakan ayat?</strong><br>Data tafsir memang masih berupa contoh terbatas, bukan basis data lengkap — lihat <a href="#disclaimer">Disclaimer &amp; Keterbatasan</a>.</p>
<p><strong>Apa bedanya "Muncul Bersama" dengan "Cari AND"?</strong><br>Keduanya memakai mesin yang sama persis (<code>root_co_occurrence</code>) — "Muncul Bersama" untuk menjelajah dari SATU kata secara berjenjang, "Cari AND" untuk langsung merakit beberapa kata sekaligus kalau kombinasinya sudah diketahui dari awal. Lihat <a href="#algoritma-cari">Algoritma Pencarian</a>.</p>
HTML,
                        'en' => <<<'HTML'
<p><strong>Why does the Word Info popup sometimes show a different language than the menus?</strong><br>Check your UI Language setting — root/word meanings ALWAYS follow UI Language, not Translation Language. See <a href="#hubungan-setting">How Settings Relate to Menus</a>.</p>
<p><strong>Why does "Root Word Search" find words that look unrelated?</strong><br>Root matching is an automated approximation (see <a href="#cari-akar">Root Word Search</a> and <a href="#disclaimer">Disclaimer</a>) — occasional false positives happen.</p>
<p><strong>My bookmarks disappeared after switching browser/phone.</strong><br>Bookmarks are saved as local, per-browser data (unless you're logged in and they're saved to an account, if that's available) — switching browser/device without being logged in can leave them behind.</p>
<p><strong>Why is the tafsir list empty for most verses?</strong><br>Tafsir data is still a limited sample, not a complete dataset — see <a href="#disclaimer">Disclaimer &amp; Limitations</a>.</p>
<p><strong>What's the difference between "Appears With" and "AND Search"?</strong><br>Both use the exact same engine (<code>root_co_occurrence</code>) — "Appears With" is for exploring outward from ONE word step by step, "AND Search" is for directly assembling several words at once when you already know the combination upfront. See <a href="#algoritma-cari">Search Algorithms</a>.</p>
HTML,
                        'ar' => <<<'HTML'
<p><strong>لماذا يظهر أحياناً معنى مختلف اللغة في نافذة معلومات الكلمة عن القوائم؟</strong><br>تحقق من إعداد لغة الواجهة — معاني الجذور/الكلمات تتبع دائماً لغة الواجهة، وليس لغة الترجمة. راجع <a href="#hubungan-setting">علاقة الإعدادات بالقوائم</a>.</p>
<p><strong>لماذا يجد "البحث بالجذر" كلمات تبدو غير ذات صلة؟</strong><br>مطابقة الجذر تقريب آلي (راجع <a href="#cari-akar">البحث بالجذر</a> و<a href="#disclaimer">إخلاء المسؤولية</a>) — تحدث إيجابيات زائفة أحياناً.</p>
<p><strong>اختفت إشاراتي المرجعية بعد تغيير المتصفح/الهاتف.</strong><br>تُحفظ الإشارات المرجعية كبيانات محلية خاصة بكل متصفح (ما لم تكن مسجلاً دخولك وتُحفظ في حساب، إن توفرت هذه الميزة) — تبديل المتصفح/الجهاز دون تسجيل دخول قد يترك الإشارات خلفك.</p>
<p><strong>لماذا قائمة التفسير فارغة لمعظم الآيات؟</strong><br>بيانات التفسير لا تزال عينة محدودة، وليست مجموعة بيانات كاملة — راجع <a href="#disclaimer">إخلاء المسؤولية والحدود</a>.</p>
<p><strong>ما الفرق بين "تظهر مع" و"بحث AND"؟</strong><br>كلاهما يستخدم نفس المحرك تماماً (<code>root_co_occurrence</code>) — "تظهر مع" لاستكشاف الانطلاق من كلمة واحدة تدريجياً، و"بحث AND" لتجميع عدة كلمات مباشرة دفعة واحدة عندما تكون التوليفة معروفة مسبقاً. راجع <a href="#algoritma-cari">خوارزميات البحث</a>.</p>
HTML,
                    ],
                ],
            ],
        ],
    ];
}

// Flattened, in document order — drives sidebar numbering and prev/next.
function guide_flatten_sections(array $groups): array {
    $flat = [];
    foreach ($groups as $group) {
        foreach ($group['sections'] as $section) {
            $section['group_id'] = $group['id'];
            $flat[] = $section;
        }
    }
    return $flat;
}

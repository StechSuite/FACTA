<?php
/**
 * FACTA — Light Morphology / Root Matching (AR, ID, EN)
 *
 * Heuristic word-formation matching, NOT verified linguistic morphology
 * (same spirit as the well-known ISRI/Khoja Arabic light stemmers, and
 * a simplified approximation of Nazief-Adriani for Indonesian). Used to
 * search "derived forms of a base word/root" dynamically at query time —
 * no root-to-word link is ever precomputed or stored.
 */

// ============================================================
// Arabic
// ============================================================

const AR_DIACRITICS_CLASS = '\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06DC}\x{06DF}-\x{06E8}\x{06EA}-\x{06ED}\x{0640}\x{08D3}-\x{08FF}\x{FEFF}\x{200E}\x{200F}';
const AR_DIACRITICS_RE = '/[' . AR_DIACRITICS_CLASS . ']/u';

function ar_normalize(string $text): string {
    $t = preg_replace(AR_DIACRITICS_RE, '', $text);
    $t = preg_replace('/[إأآٱ]/u', 'ا', $t);
    $t = str_replace('ة', 'ه', $t);
    return trim($t);
}

// Iterative single-clitic prefixes/suffixes (Arabic), longest-first per pass.
const AR_PREFIXES = ['ال', 'و', 'ف', 'ب', 'ك', 'ل', 'س', 'ي', 'ت', 'ن', 'ا'];
const AR_SUFFIXES = [
    'كما', 'هما', 'تين', 'ون', 'ين', 'ات', 'ان', 'يه', 'ها', 'هم', 'هن',
    'كم', 'كن', 'نا', 'تم', 'تن', 'وا', 'ني', 'يه', 'ي', 'ت', 'ه', 'ك',
];

/**
 * Strip up to $maxStrips clitics from one end of a word, returning the
 * set of all intermediate residuals (not just the final one) — an
 * ambiguous short clitic (e.g. "ك") can be a false strip, so every
 * partial residual is kept as its own candidate rather than committing
 * greedily to one interpretation.
 */
function ar_strip_candidates(string $word, array $clitics, bool $fromStart, int $maxStrips, int $minLen): array {
    $candidates = [$word];
    $frontier = [$word];
    for ($i = 0; $i < $maxStrips; $i++) {
        $next = [];
        foreach ($frontier as $w) {
            foreach ($clitics as $c) {
                $len = mb_strlen($c, 'UTF-8');
                if (mb_strlen($w, 'UTF-8') - $len < $minLen) continue;
                if ($fromStart) {
                    if (mb_substr($w, 0, $len, 'UTF-8') === $c) {
                        $stripped = mb_substr($w, $len, null, 'UTF-8');
                        $next[] = $stripped;
                        $candidates[] = $stripped;
                    }
                } else {
                    if (mb_substr($w, -$len, null, 'UTF-8') === $c) {
                        $stripped = mb_substr($w, 0, -$len, 'UTF-8');
                        $next[] = $stripped;
                        $candidates[] = $stripped;
                    }
                }
            }
        }
        if (!$next) break;
        $frontier = $next;
    }
    return array_unique($candidates);
}

// Root radicals as an in-order (gap-allowed) subsequence of $residual —
// derivational templates interleave extra letters (ا و ي ت م ن س) between
// root radicals, but the radicals themselves always keep their relative
// order, so subsequence matching (not exact/contiguous) is what lets a
// pattern like "مكتوب" (م-ك-ت-و-ب) still match root ك-ت-ب.
function ar_subsequence_match(string $residual, string $root): bool {
    $ri = 0;
    $residualLen = mb_strlen($residual, 'UTF-8');
    $rootLen = mb_strlen($root, 'UTF-8');
    for ($i = 0; $i < $residualLen && $ri < $rootLen; $i++) {
        if (mb_substr($residual, $i, 1, 'UTF-8') === mb_substr($root, $ri, 1, 'UTF-8')) {
            $ri++;
        }
    }
    return $ri === $rootLen;
}

/**
 * Does $wordClean plausibly derive from $rootClean? Both must already be
 * ar_normalize()'d. Heuristic/approximate — see file header.
 */
function ar_word_matches_root(string $wordClean, string $rootClean): bool {
    $rootLen = mb_strlen($rootClean, 'UTF-8');
    if ($rootLen < 2) return false;

    $prefixStripped = ar_strip_candidates($wordClean, AR_PREFIXES, true, 2, $rootLen);
    $maxResidual = $rootLen + 4;

    foreach ($prefixStripped as $afterPrefix) {
        $suffixStripped = ar_strip_candidates($afterPrefix, AR_SUFFIXES, false, 2, $rootLen);
        foreach ($suffixStripped as $residual) {
            $len = mb_strlen($residual, 'UTF-8');
            if ($len < $rootLen || $len > $maxResidual) continue;
            if (ar_subsequence_match($residual, $rootClean)) return true;
        }
    }
    return false;
}

// Best-effort "root-like" residual for one word, used only to seed a
// follow-up root search when the user clicks a word — returns null
// unless a clean 3-4 letter residual was found (never surfaced as "the
// root", only as a starting point for another search; see Step 8 UI).
function ar_guess_residual(string $wordClean): ?string {
    $best = null;
    foreach (ar_strip_candidates($wordClean, AR_PREFIXES, true, 2, 2) as $afterPrefix) {
        foreach (ar_strip_candidates($afterPrefix, AR_SUFFIXES, false, 2, 2) as $residual) {
            $len = mb_strlen($residual, 'UTF-8');
            if ($len === 3 || $len === 4) {
                if ($best === null || $len < mb_strlen($best, 'UTF-8')) $best = $residual;
            }
        }
    }
    return $best;
}

// ============================================================
// Indonesian (simplified Nazief-Adriani-style approximation)
// ============================================================

const ID_PARTICLES = ['lah', 'kah', 'tah', 'pun'];
const ID_POSSESSIVES = ['ku', 'mu', 'nya'];
const ID_SUFFIXES = ['kan', 'an', 'i'];
// prefix => [literal-strip-consonant-count, restored consonant to try re-adding]
const ID_PREFIX_RULES = [
    'meng' => null, 'meny' => 's', 'mem' => 'p', 'men' => 't', 'me' => null,
    'peng' => null, 'peny' => 's', 'pem' => 'p', 'pen' => 't', 'pe' => null,
    'ber' => null, 'bel' => null, 'be' => null,
    'ter' => null, 'di' => null, 'ke' => null, 'se' => null,
];

function id_stem_candidates(string $word): array {
    $w = mb_strtolower(trim($word), 'UTF-8');
    $candidates = [$w];

    foreach (ID_PARTICLES as $p) {
        if (str_ends_with($w, $p) && mb_strlen($w, 'UTF-8') - mb_strlen($p, 'UTF-8') >= 3) {
            $w = mb_substr($w, 0, -mb_strlen($p, 'UTF-8'), 'UTF-8');
            break;
        }
    }
    foreach (ID_POSSESSIVES as $p) {
        if (str_ends_with($w, $p) && mb_strlen($w, 'UTF-8') - mb_strlen($p, 'UTF-8') >= 3) {
            $w = mb_substr($w, 0, -mb_strlen($p, 'UTF-8'), 'UTF-8');
            break;
        }
    }
    $candidates[] = $w;
    foreach (ID_SUFFIXES as $p) {
        if (str_ends_with($w, $p) && mb_strlen($w, 'UTF-8') - mb_strlen($p, 'UTF-8') >= 2) {
            $candidates[] = mb_substr($w, 0, -mb_strlen($p, 'UTF-8'), 'UTF-8');
        }
    }

    // Iterative (up to 2 rounds) so circumfixes like ke-...-an / ber-...-an
    // (e.g. "keadilan", "berkeadilan") reduce all the way to the base word,
    // not just after a single prefix round.
    $prefixed = [];
    foreach ($candidates as $base) {
        $frontier = [$base];
        $prefixed[] = $base;
        for ($round = 0; $round < 2; $round++) {
            $next = [];
            foreach ($frontier as $w) {
                // ID_PREFIX_RULES is already ordered longest-first within each
                // ambiguous family (meng/meny before mem/men before me, etc.)
                foreach (ID_PREFIX_RULES as $prefix => $restore) {
                    $plen = mb_strlen($prefix, 'UTF-8');
                    if (str_starts_with($w, $prefix) && mb_strlen($w, 'UTF-8') - $plen >= 2) {
                        $stripped = mb_substr($w, $plen, null, 'UTF-8');
                        $next[] = $stripped;
                        $prefixed[] = $stripped;
                        if ($restore !== null) $prefixed[] = $restore . $stripped;
                    }
                }
            }
            if (!$next) break;
            $frontier = $next;
        }
    }
    return array_values(array_unique($prefixed));
}

// ============================================================
// English (Porter-lite)
// ============================================================

const EN_SUFFIXES = ['tion', 'ment', 'ness', 'able', 'ible', 'ing', 'ies', 'ed', 'es', 'ly', 'er', 'est', 's'];

function en_stem_candidates(string $word): array {
    $w = strtolower(trim($word));
    $candidates = [$w];
    foreach (EN_SUFFIXES as $suf) {
        $len = strlen($suf);
        if (str_ends_with($w, $suf) && strlen($w) - $len >= 3) {
            $stem = substr($w, 0, -$len);
            $candidates[] = $stem;
            if (($suf === 'ing' || $suf === 'ed') && strlen($stem) >= 2 && $stem[-1] === $stem[-2]) {
                $candidates[] = substr($stem, 0, -1); // undo doubled final consonant (running -> run)
            }
        }
    }
    return array_values(array_unique($candidates));
}

// ============================================================
// Dispatcher
// ============================================================

function word_stems_match(string $wordA, string $wordB, string $lang): bool {
    switch ($lang) {
        case 'id':
            return (bool)array_intersect(id_stem_candidates($wordA), id_stem_candidates($wordB));
        case 'en':
            return (bool)array_intersect(en_stem_candidates($wordA), en_stem_candidates($wordB));
        default:
            return ar_normalize($wordA) === ar_normalize($wordB);
    }
}

// ============================================================
// Gloss terms — significant words from a word-by-word gloss, used for
// contextual cross-language highlighting (skip function words that
// would light up everywhere in a translation).
// ============================================================

const ID_GLOSS_STOPWORDS = [
    'yang', 'dan', 'dengan', 'dari', 'di', 'ke', 'itu', 'ini', 'mereka',
    'kami', 'kamu', 'dia', 'ia', 'adalah', 'untuk', 'pada', 'atas', 'tidak',
    'apa', 'siapa', 'akan', 'telah', 'sungguh', 'kecuali', 'atau', 'lalu',
    'maka', 'agar', 'jika', 'kepada', 'dalam', 'orang', 'bagi', 'wahai',
    'sesungguhnya', 'apakah', 'kalian', 'engkau', 'nya', 'pun', 'saja',
];
const EN_GLOSS_STOPWORDS = [
    'the', 'of', 'and', 'to', 'in', 'is', 'are', 'was', 'were', 'for',
    'on', 'it', 'its', 'they', 'them', 'you', 'we', 'he', 'she', 'his',
    'her', 'their', 'your', 'our', 'who', 'whom', 'what', 'will', 'shall',
    'be', 'been', 'not', 'no', 'do', 'did', 'does', 'have', 'has', 'had',
    'from', 'with', 'or', 'as', 'that', 'this', 'those', 'these', 'indeed',
    'all', 'upon', 'so', 'then', 'there', 'when', 'which', 'but', 'him',
];

// Ultra-frequent Arabic function/name words (clean forms) excluded from
// the co-occurrence "association" list — they appear alongside nearly
// every word and would drown out meaningful associations.
const AR_ASSOC_STOPWORDS = [
    'الله', 'من', 'ما', 'لا', 'ان', 'الا', 'في', 'علي', 'على', 'الي', 'إلى',
    'الذين', 'الذي', 'التي', 'هو', 'هي', 'هم', 'كان', 'كانوا', 'قال', 'قالوا',
    'قل', 'يا', 'ايها', 'ثم', 'او', 'لم', 'لن', 'قد', 'كل', 'لهم', 'لكم', 'لك',
    'له', 'لها', 'به', 'بها', 'بهم', 'عليه', 'عليها', 'عليهم', 'عليكم', 'ومن',
    'وما', 'ولا', 'وان', 'فان', 'اذا', 'اذ', 'ذلك', 'هذا', 'هذه', 'شيء', 'بعد',
    'قبل', 'عند', 'حتي', 'حتى', 'بين', 'دون', 'غير', 'مع', 'عن', 'كما', 'انما',
    'فيها', 'فيه', 'منها', 'منهم', 'منكم', 'اليه', 'اليهم', 'ولهم', 'وهو', 'وهم',
    'مما', 'منه', 'لما', 'فلما', 'بما', 'فيما', 'اليها', 'انه', 'انهم', 'وله',
    'ولم', 'فلا', 'الي', 'لدي', 'نحن', 'انا', 'انت', 'انتم', 'كنتم', 'يكون',
    'ومما', 'فمما', 'لمن', 'فمن', 'كمن', 'ولمن', 'اولئك', 'كذلك', 'وكذلك',
    'ذلكم', 'تلك', 'اولاء', 'ايا', 'اين', 'كيف', 'متي', 'متى', 'لولا', 'لعل',
    'الذى', 'التى', 'الى', 'والله', 'فالله', 'لله', 'بالله', 'تالله',
];

function gloss_terms(?string $gloss, string $lang): array {
    if (!$gloss) return [];
    $stops = $lang === 'en' ? EN_GLOSS_STOPWORDS : ID_GLOSS_STOPWORDS;
    $terms = preg_split('/[^\p{L}\'-]+/u', mb_strtolower($gloss, 'UTF-8'), -1, PREG_SPLIT_NO_EMPTY);
    $keep = [];
    foreach ($terms as $t) {
        if (mb_strlen($t, 'UTF-8') >= 3 && !in_array($t, $stops, true)) $keep[] = $t;
    }
    return $keep;
}

// ============================================================
// Search-term highlighting
// ============================================================

// Regex fragment matching one Arabic base letter, tolerating the
// diacritics/tatweel that appear between letters in Uthmani text, and
// the letter variants ar_normalize() collapses (alef forms, ta-marbuta).
function ar_letter_pattern(string $ch): string {
    if ($ch === 'ا') return '[اأإآٱ]';
    if ($ch === 'ه') return '[هة]';
    return preg_quote($ch, '/');
}

/**
 * Wrap occurrences of $needle in <mark> within $haystack. For Arabic,
 * $needle is matched letter-by-letter against $haystack tolerating any
 * diacritics between letters (so a plain-typed query like "الرحمن"
 * still highlights inside fully-vocalized Uthmani text). For en/id,
 * a plain case-insensitive substring match. Text is otherwise emitted
 * as-is (matches this codebase's existing convention of not escaping
 * trusted DB-sourced Quran text/translations).
 */
function highlight_text(string $haystack, string $needle, string $lang = 'ar'): string {
    return highlight_words($haystack, [$needle], $lang);
}

// Wrap occurrences of any word in $words in <mark>, within a single
// preg_replace pass (one alternation pattern) — sequential per-word
// passes would risk one word's pattern matching text already inside a
// <mark> tag from a previous pass (nested/corrupted markup), especially
// when several matched words share letters, as root-search results
// often do.
function highlight_words(string $haystack, array $words, string $lang = 'ar', bool $wholeWord = false): string {
    $words = array_values(array_unique(array_filter(array_map('trim', $words))));
    if (!$words) return $haystack;

    $alternatives = [];
    foreach ($words as $w) {
        if ($lang === 'ar') {
            $letters = preg_split('//u', ar_normalize($w), -1, PREG_SPLIT_NO_EMPTY);
            if (!$letters) continue;
            $gap = '[' . AR_DIACRITICS_CLASS . ']*';
            $alternatives[] = implode($gap, array_map('ar_letter_pattern', $letters));
        } else {
            $alternatives[] = preg_quote($w, '/');
        }
    }
    if (!$alternatives) return $haystack;

    // Longest alternatives first so a longer match wins over a shorter
    // one that happens to be its prefix (PHP's PCRE alternation is
    // first-match, not longest-match).
    usort($alternatives, fn($a, $b) => mb_strlen($b, 'UTF-8') <=> mb_strlen($a, 'UTF-8'));
    $flags = $lang === 'ar' ? 'u' : 'iu';
    // Whole-word (letter-bounded) matching guards auto-derived gloss
    // terms against false hits like "beri" lighting up inside "beriman".
    $body = '(' . implode('|', $alternatives) . ')';
    $pattern = $wholeWord && $lang !== 'ar'
        ? '/(?<!\p{L})' . $body . '(?!\p{L})/' . $flags
        : '/' . $body . '/' . $flags;
    $out = preg_replace($pattern, '<mark>$1</mark>', $haystack);
    return $out ?? $haystack;
}

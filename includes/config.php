<?php
/**
 * FACTA — Application Configuration
 * Offline-ready, cPanel-friendly
 */

define('APP_NAME', 'FACTA');
define('APP_VERSION', '1.0.0');
define('APP_URL', ''); // auto-detected below

// Database
$DB_PATH = __DIR__ . '/../data/smartquran.db';

// Auto-detect base URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '/');
$basePath = rtrim(str_replace('\\', '/', $scriptDir), '/');
define('BASE_URL', $protocol . '://' . $host . $basePath);

// Security / Auth — no real secret ships as a fallback default here
// (this is a public repo). Set these via environment variables for any
// real deployment; without them, the admin gate/session signing use an
// obviously-insecure local-dev placeholder, and Google OAuth is simply
// disabled rather than falling back to a hardcoded credential.
// 1. Admin cookie secret (for local dev / fallback)
define('ADMIN_SECRET', getenv('FACTA_ADMIN_SECRET') ?: 'change-me-local-dev-only');

// 2. Session / token signing
//    Used for auth_token cookie and state param. Change in production.
define('APP_SECRET', getenv('FACTA_APP_SECRET') ?: 'change-me-in-production-' . md5(__DIR__));

// 3. Google OAuth 2.0 — bring your own credentials from Google Cloud
//    Console; OAuth login is disabled until both env vars are set.
$googleClientId     = getenv('GOOGLE_CLIENT_ID') ?: '';
$googleClientSecret = getenv('GOOGLE_CLIENT_SECRET') ?: '';

// redirect_uri harus EXACT MATCH dengan yang terdaftar di Google Console.
// Pakai root path (bukan BASE_URL) karena api/auth.php dipanggil langsung.
$hostNoPort = strtok($host, ':');
$isLocal    = ($hostNoPort === 'localhost' || $hostNoPort === '127.0.0.1');
$GOOGLE_OAUTH = [
    'enabled'       => $googleClientId !== '' && $googleClientSecret !== '',
    'client_id'     => $googleClientId,
    'client_secret' => $googleClientSecret,
    'redirect_uri'  => ($isLocal ? 'http://localhost:8885' : ('https://' . $host)) . '/index.php',
    'scopes'        => ['openid', 'email', 'profile'],
];

// API Settings (for AI Chat placeholder)
$API_CONFIG = [
    'providers' => [
        'ollama' => [
            'enabled' => true,
            'base_url' => 'http://localhost:11434',
            'default_model' => 'llama3',
            'api_key' => null,
        ],
        'openrouter' => [
            'enabled' => false,
            'base_url' => 'https://openrouter.ai/api/v1',
            'default_model' => 'meta-llama/llama-3-8b-instruct',
            'api_key' => '', // set in admin
        ],
        'openai' => [
            'enabled' => false,
            'base_url' => 'https://api.openai.com/v1',
            'default_model' => 'gpt-4o-mini',
            'api_key' => '', // set in admin
        ],
    ],
    'system_prompt' => 'You are a helpful Quran assistant. Answer questions based on Quran and Islamic knowledge. When referencing verses, use format [Surah:Ayah].'
];

// Audio CDN sources
$AUDIO_SOURCES = [
    [
        'reciter_name' => 'Mishari Rashid Alafasy',
        'reciter_name_ar' => 'مشاري راشد العفاسي',
        'base_url' => 'https://everyayah.com/data/Alafasy_128kbps/',
        'format' => 'mp3',
    ],
    [
        'reciter_name' => 'Abdul Basit Murattal',
        'reciter_name_ar' => 'عبد الباسط عبد الصمد',
        'base_url' => 'https://everyayah.com/data/Abdul_Basit_Murattal_192kbps/',
        'format' => 'mp3',
    ],
];

// Supported UI languages
$UI_LANGUAGES = [
    'ar' => ['name' => 'العربية', 'dir' => 'rtl'],
    'en' => ['name' => 'English', 'dir' => 'ltr'],
    'id' => ['name' => 'Indonesia', 'dir' => 'ltr'],
    'su' => ['name' => 'Basa Sunda', 'dir' => 'ltr'],
    'jv' => ['name' => 'Basa Jawa', 'dir' => 'ltr'],
];

// Default settings
$DEFAULT_SETTINGS = [
    'theme' => ['value' => 'dark', 'type' => 'string'],
    'font_size_ar' => ['value' => '28', 'type' => 'int'],
    'font_size_translation' => ['value' => '16', 'type' => 'int'],
    'translation_lang' => ['value' => 'id', 'type' => 'string'],
    'audio_reciter' => ['value' => '1', 'type' => 'int'],
    'show_tajweed' => ['value' => '0', 'type' => 'bool'],
    'show_arabic' => ['value' => '1', 'type' => 'bool'],
    'show_translation' => ['value' => '1', 'type' => 'bool'],
    'show_transliteration' => ['value' => '0', 'type' => 'bool'],
    'ui_language' => ['value' => 'id', 'type' => 'string'],
];

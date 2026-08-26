<?php
/**
 * FACTA — API: AI Chat (Placeholder)
 * Supports Ollama (local), OpenRouter, OpenAI
 */

require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
$message = trim($input['message'] ?? '');
$provider = $input['provider'] ?? 'ollama';

if (empty($message)) {
    json_response(['error' => 'Pesan kosong']);
}

global $API_CONFIG;
$config = $API_CONFIG['providers'][$provider] ?? null;

if (!$config || !$config['enabled']) {
    json_response([
        'error' => "Provider '{$provider}' belum dikonfigurasi atau dinonaktifkan.",
        'hint' => "Edit includes/config.php untuk mengatur API key dan mengaktifkan provider."
    ]);
}

// Try to call provider
$response = null;
$error = null;

try {
    if ($provider === 'ollama') {
        // Ollama local API
        $ch = curl_init($config['base_url'] . '/api/chat');
        $payload = json_encode([
            'model' => $config['default_model'],
            'messages' => [
                ['role' => 'system', 'content' => $API_CONFIG['system_prompt']],
                ['role' => 'user', 'content' => $message]
            ],
            'stream' => false
        ]);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 30
        ]);
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $result) {
            $data = json_decode($result, true);
            $response = $data['message']['content'] ?? null;
            if (!$response) $error = 'Ollama returned empty response';
        } else {
            $error = "Ollama tidak merespons (HTTP {$httpCode}). Pastikan Ollama berjalan di {$config['base_url']}";
        }

    } elseif ($provider === 'openrouter') {
        if (empty($config['api_key'])) {
            $error = 'API key OpenRouter belum diatur di config.php';
        } else {
            $ch = curl_init($config['base_url'] . '/chat/completions');
            $payload = json_encode([
                'model' => $config['default_model'],
                'messages' => [
                    ['role' => 'system', 'content' => $API_CONFIG['system_prompt']],
                    ['role' => 'user', 'content' => $message]
                ]
            ]);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $config['api_key']
                ],
                CURLOPT_TIMEOUT => 60
            ]);
            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && $result) {
                $data = json_decode($result, true);
                $response = $data['choices'][0]['message']['content'] ?? null;
                if (!$response) $error = 'OpenRouter returned empty response';
            } else {
                $error = "OpenRouter error (HTTP {$httpCode})";
            }
        }

    } elseif ($provider === 'openai') {
        if (empty($config['api_key'])) {
            $error = 'API key OpenAI belum diatur di config.php';
        } else {
            $ch = curl_init($config['base_url'] . '/chat/completions');
            $payload = json_encode([
                'model' => $config['default_model'],
                'messages' => [
                    ['role' => 'system', 'content' => $API_CONFIG['system_prompt']],
                    ['role' => 'user', 'content' => $message]
                ]
            ]);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $config['api_key']
                ],
                CURLOPT_TIMEOUT => 60
            ]);
            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && $result) {
                $data = json_decode($result, true);
                $response = $data['choices'][0]['message']['content'] ?? null;
                if (!$response) $error = 'OpenAI returned empty response';
            } else {
                $error = "OpenAI error (HTTP {$httpCode})";
            }
        }
    }

} catch (Exception $e) {
    $error = $e->getMessage();
}

if ($response) {
    json_response(['reply' => $response, 'provider' => $provider]);
} else {
    json_response([
        'error' => $error ?? 'Gagal mendapatkan respons dari AI',
        'provider' => $provider
    ]);
}

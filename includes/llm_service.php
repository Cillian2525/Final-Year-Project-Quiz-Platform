<?php

/**
 * LLM service wrapper for adaptive question generation.
 *
 * Expects an OpenAI-compatible Chat Completions API.
 * - API key is loaded from the OPENAI_API_KEY environment variable.
 * - You can change the endpoint or model as needed.
 */

const LLM_API_ENDPOINT = 'https://api.openai.com/v1/chat/completions';
const LLM_MODEL        = 'gpt-4.1-mini';

/**
 * Generate adaptive multiple-choice questions using an LLM.
 *
 * @param string $topic
 * @param string $difficulty
 * @param array  $performanceSummary Arbitrary associative array with performance info
 * @return array Decoded JSON structure from the LLM (or empty array on error)
 */
function generateAdaptiveQuestions(string $topic, string $difficulty, array $performanceSummary): array
{
    $apiKey = getenv('OPENAI_API_KEY');
    if (!$apiKey) {
        // No API key configured, fail fast but do not crash the app.
        return [];
    }

    $systemPrompt = 'You are an educational quiz question generator. '
        . 'You must respond with JSON only, no explanations or extra text.';

    // Basic prompt for now; will be refined in later sections.
    $userPrompt = json_encode([
        'instruction' => 'Generate adaptive multiple choice questions in strict JSON format.',
        'topic' => $topic,
        'difficulty' => $difficulty,
        'performance_summary' => $performanceSummary,
    ], JSON_UNESCAPED_UNICODE);

    $payload = [
        'model' => LLM_MODEL,
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ],
        // Try to encourage JSON-only responses
        'temperature' => 0.7,
    ];

    $ch = curl_init(LLM_API_ENDPOINT);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => 20,
    ]);

    $rawResponse = curl_exec($ch);
    if ($rawResponse === false) {
        curl_close($ch);
        return [];
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode < 200 || $httpCode >= 300) {
        return [];
    }

    $decoded = json_decode($rawResponse, true);
    if (!is_array($decoded)) {
        return [];
    }

    $content = $decoded['choices'][0]['message']['content'] ?? null;
    if (!is_string($content) || $content === '') {
        return [];
    }

    $json = json_decode($content, true);
    if (!is_array($json)) {
        return [];
    }

    // At this stage we simply return whatever JSON structure we got.
    return $json;
}

// Optional: simple manual test hook (not used in normal flow)
if (php_sapi_name() !== 'cli' && isset($_GET['llm_test']) && $_GET['llm_test'] === '1') {
    header('Content-Type: application/json; charset=utf-8');
    $result = generateAdaptiveQuestions('Database Basics', 'easy', [
        'average_score' => 75,
        'last_score' => 80,
    ]);
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}


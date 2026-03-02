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
        $configFile = __DIR__ . '/../config/llm_config.php';
        if (file_exists($configFile)) {
            $cfg = @include $configFile;
            $apiKey = (is_array($cfg) && isset($cfg['OPENAI_API_KEY'])) ? trim((string)$cfg['OPENAI_API_KEY']) : '';
        }
    }
    if (!$apiKey) {
        // No API key configured, fail fast but do not crash the app.
        return [];
    }

    $averageScore = isset($performanceSummary['average_score']) ? (float)$performanceSummary['average_score'] : null;
    $lastScore    = isset($performanceSummary['last_score']) ? (float)$performanceSummary['last_score'] : null;

    $systemPrompt = 'You are an educational quiz question generator. '
        . 'You must respond with JSON only, no explanations or extra text.';

    // Basic prompt for now; will be refined in later sections.
    $userPrompt = "Generate exactly 5 multiple choice questions for a quiz.\n"
        . "Topic: {$topic}\n"
        . "Difficulty level: {$difficulty}\n";

    if ($averageScore !== null) {
        $userPrompt .= "Student average score: {$averageScore}%\n";
    }
    if ($lastScore !== null) {
        $userPrompt .= "Student last score: {$lastScore}%\n";
    }

    $userPrompt .= "\nEach question must:\n"
        . "- be a single clear stem\n"
        . "- have four options: option_a, option_b, option_c, option_d\n"
        . "- have correct_answer as one of 'A', 'B', 'C', or 'D'\n\n"
        . "Respond in STRICT JSON only, no prose, using this schema:\n"
        . "{\n"
        . "  \"questions\": [\n"
        . "    {\n"
        . "      \"question\": \"\",\n"
        . "      \"option_a\": \"\",\n"
        . "      \"option_b\": \"\",\n"
        . "      \"option_c\": \"\",\n"
        . "      \"option_d\": \"\",\n"
        . "      \"correct_answer\": \"A\"\n"
        . "    }\n"
        . "  ]\n"
        . "}\n";

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
        // Local MAMP workaround: disable SSL verification so self-signed / missing CA certs don't break the call
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
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

    // Validate expected structure: questions array with exactly 5 items
    if (!isset($json['questions']) || !is_array($json['questions']) || count($json['questions']) !== 5) {
        return [];
    }

    $validAnswers = ['A', 'B', 'C', 'D'];
    $cleanQuestions = [];

    foreach ($json['questions'] as $q) {
        if (
            !is_array($q) ||
            !isset($q['question'], $q['option_a'], $q['option_b'], $q['option_c'], $q['option_d'], $q['correct_answer'])
        ) {
            return [];
        }

        $answer = strtoupper(trim((string)$q['correct_answer']));
        if (!in_array($answer, $validAnswers, true)) {
            return [];
        }

        $cleanQuestions[] = [
            'question' => (string)$q['question'],
            'option_a' => (string)$q['option_a'],
            'option_b' => (string)$q['option_b'],
            'option_c' => (string)$q['option_c'],
            'option_d' => (string)$q['option_d'],
            'correct_answer' => $answer,
        ];
    }

    return $cleanQuestions;
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


<?php
// path: app/Services/AiService.php

namespace App\Services;

class AiService
{
    private string $provider; // 'ollama', 'gemini', or 'openai-chat'
    private string $apiKey;
    private string $endpoint;
    private string $model;

    public function __construct(string $provider, array $config)
    {
        $this->provider = trim($provider);
        $this->apiKey   = trim((string)($config['api_key'] ?? ''));
        $this->endpoint = trim((string)($config['endpoint'] ?? ''));
        $this->model    = trim((string)($config['model'] ?? ''));
    }

    /**
     * Generate incident explanation/resolution update.
     */
    public function generateIncidentReport(string $serviceName, string $errorDetails): string
    {
        // Strict validation: do not proceed if required parameters are missing
        if (empty($this->model)) {
            error_log("AI Service Error: Model name is not configured.");
            return 'AI service is not configured: model name is missing in settings.';
        }

        if ($this->provider === 'gemini') {
            if (empty($this->apiKey)) {
                error_log("AI Service Error: Gemini API key is not configured.");
                return 'AI service is not configured: Gemini API key is missing in settings.';
            }
        } else {
            if (empty($this->endpoint)) {
                error_log("AI Service Error: Endpoint URL is not configured for provider [{$this->provider}].");
                return 'AI service is not configured: endpoint URL is missing in settings.';
            }
        }

        $prompt = "You are an infrastructure system administrator. Provide a concise, professional public incident report status update (2-3 sentences) explaining that we are investigating issues with {$serviceName}. Raw error detail: {$errorDetails}. Do not mention sensitive data.";

        return match ($this->provider) {
            'ollama'      => $this->callOllama($prompt),
            'gemini'      => $this->callGemini($prompt),
            'openai-chat' => $this->callOpenAiChat($prompt),
            default       => "AI provider [{$this->provider}] is not supported."
        };
    }

    private function callOllama(string $prompt): string
    {
        $url = rtrim($this->endpoint, '/') . '/api/generate';
        $data = [
            'model'  => $this->model,
            'prompt' => $prompt,
            'stream' => false
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10
        ]);

        $response = curl_exec($ch);
        $curlErr  = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlErr || $httpCode !== 200) {
            error_log("Ollama Call Error on [{$url}]: HTTP $httpCode, Curl: $curlErr, Response: " . substr((string)$response, 0, 300));
            return 'AI service generation failed.';
        }

        $json = json_decode((string)$response, true);
        return $json['response'] ?? 'AI service generation failed.';
    }

    private function callGemini(string $prompt): string
    {
        $endpoint = !empty($this->endpoint) ? rtrim($this->endpoint, '/') : 'https://generativelanguage.googleapis.com';
        $url = "{$endpoint}/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}";
        
        $data = [
            'contents' => [
                ['parts' => [['text' => $prompt]]]
            ]
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 10
        ]);

        $response = curl_exec($ch);
        $curlErr  = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlErr || $httpCode !== 200) {
            error_log("Gemini Call Error on [{$url}]: HTTP $httpCode, Curl: $curlErr, Response: " . substr((string)$response, 0, 300));
            return 'AI service generation failed.';
        }

        $json = json_decode((string)$response, true);
        return $json['candidates'][0]['content']['parts'][0]['text'] ?? 'AI service generation failed.';
    }

    /**
     * Call OpenAI-Compatible Chat Completions API (MLX-LM, LocalAI, vLLM, OpenAI)
     */
    private function callOpenAiChat(string $prompt): string
    {
        $url = $this->endpoint;
        $data = [
            'model'       => $this->model,
            'messages'    => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'stream'      => false,
            'temperature' => 0.7,
            'max_tokens'  => 512
        ];

        $headers = ['Content-Type: application/json'];
        if (!empty($this->apiKey)) {
            $headers[] = 'Authorization: Bearer ' . $this->apiKey;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 45,
            CURLOPT_CONNECTTIMEOUT => 10
        ]);

        $response = curl_exec($ch);
        $curlErr  = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlErr || $httpCode !== 200) {
            error_log("OpenAI-Chat Call Error on [{$url}]: HTTP $httpCode, Curl: $curlErr, Response: " . substr((string)$response, 0, 300));
            return 'AI service generation failed.';
        }

        $json = json_decode((string)$response, true);
        return $json['choices'][0]['message']['content'] ?? 'AI service generation failed.';
    }
}

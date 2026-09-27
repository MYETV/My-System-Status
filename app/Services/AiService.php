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
        $this->provider = $provider;
        $this->apiKey   = $config['api_key'] ?? '';
        $this->endpoint = $config['endpoint'] ?? match($provider) {
            'ollama'      => 'http://localhost:11434',
            'openai-chat' => 'http://localhost:11435/v1/chat/completions',
            default       => 'https://generativelanguage.googleapis.com'
        };
        $this->model    = $config['model'] ?? match($provider) {
            'ollama'      => 'llama3',
            'openai-chat' => 'gpt-4o',
            default       => 'gemini-1.5-flash'
        };
    }

    /**
     * Generate incident explanation/resolution update.
     */
    public function generateIncidentReport(string $serviceName, string $errorDetails): string
    {
        $prompt = "You are an infrastructure system administrator. Provide a concise, professional public incident report status update (2-3 sentences) explaining that we are investigating issues with {$serviceName}. Raw error detail: {$errorDetails}. Do not mention sensitive data.";

        return match ($this->provider) {
            'ollama'      => $this->callOllama($prompt),
            'gemini'      => $this->callGemini($prompt),
            'openai-chat' => $this->callOpenAiChat($prompt),
            default       => "Incident currently under investigation by engineers."
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
            CURLOPT_TIMEOUT        => 30
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $json = json_decode((string)$response, true);
        return $json['response'] ?? 'AI service generation failed.';
    }

    private function callGemini(string $prompt): string
    {
        $url = "{$this->endpoint}/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}";
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
            CURLOPT_TIMEOUT        => 20
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

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
            CURLOPT_TIMEOUT        => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$response || $httpCode !== 200) {
            return 'AI service generation failed.';
        }

        $json = json_decode((string)$response, true);
        return $json['choices'][0]['message']['content'] ?? 'AI service generation failed.';
    }
}

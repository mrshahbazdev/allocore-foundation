<?php

namespace Modules\Ai\Support;

use Illuminate\Support\Facades\Http;
use Modules\Ai\Contracts\AnalysisProvider;

/**
 * OpenAI-kompatibler LLM-Provider (AI_BASE_URL, AI_API_KEY, AI_MODEL).
 * Antwort wird als JSON {summary, findings[]} erwartet; fällt zurück auf rohen Text als summary.
 */
class LlmAnalysisProvider implements AnalysisProvider
{
    public function name(): string
    {
        return 'llm:'.config('services.ai.model', 'gpt-4o-mini');
    }

    public function analyze(array $context): array
    {
        $base = rtrim((string) config('services.ai.base_url', 'https://api.openai.com/v1'), '/');
        $key = (string) config('services.ai.api_key', '');

        $response = Http::withToken($key)
            ->timeout(60)
            ->post($base.'/chat/completions', [
                'model' => config('services.ai.model', 'gpt-4o-mini'),
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Du bist ein Management-Coach für eine deutsche Unternehmensgruppe. '
                            .'Analysiere die Kennzahlen und antworte als JSON: '
                            .'{"summary": "kurze Zusammenfassung auf Deutsch", '
                            .'"findings": [{"severity": "critical|warning|info", "message": "...", "link": "/app/<sektion>"}]}. '
                            .'Maximal 5 Findings, konkret und umsetzbar.',
                    ],
                    [
                        'role' => 'user',
                        'content' => 'Kennzahlen des Mandanten: '.json_encode($context, JSON_UNESCAPED_UNICODE),
                    ],
                ],
            ])
            ->throw();

        $text = (string) ($response->json('choices.0.message.content') ?? '');
        $decoded = json_decode($text, true);

        if (is_array($decoded) && isset($decoded['summary'])) {
            return [
                'summary' => (string) $decoded['summary'],
                'findings' => array_values(array_slice($decoded['findings'] ?? [], 0, 5)),
            ];
        }

        return ['summary' => $text ?: 'Keine Antwort vom Provider.', 'findings' => []];
    }
}

<?php

namespace App\Services\Nlg\Rephrasers\Concerns;

trait BuildsWeatherRephrasePrompts
{
    /**
     * @param  array<string, mixed>  $toneCfg
     */
    private function buildWeatherSystemPrompt(string $tone, array $toneCfg): string
    {
        $maxSentences = (int) ($toneCfg['max_sentences'] ?? 2);
        $styleNotes = (string) ($toneCfg['style_notes'] ?? 'Keep it natural and concise.');

        return
            "Rewrite the forecast using ONLY the provided facts. " .
            "Do not add numbers, events, certainty, or timing that are not supported by the facts. " .
            "Use idiomatic forecast language in the target language and avoid literal translations or awkward adjective-noun combinations. " .
            "Use natural weather phrasing, not raw labels. " .
            "Lead with the main takeaway before supporting detail. " .
            "Never write contradictory precipitation phrases such as 'dry rain', 'droog regen', or similar combinations. " .
            "If precipitation is negligible or absent, say 'dry', 'mostly dry', 'little or no rain', or 'a slight chance of rain later' when supported, but never combine 'dry' with a precipitation noun. " .
            "If it stays mostly cloudy without much precipitation, say 'cloudy and mostly dry' or an equivalent natural phrase. " .
            "If rain is only possible later, phrase it as a chance or risk later in the day. " .
            $this->toneSpecificGuidance($tone) . ' ' .
            "Return plain text only. " .
            "Max sentences: {$maxSentences}. " .
            $styleNotes;
    }

    /**
     * @param  array<string, mixed>  $facts
     * @param  array<string, mixed>  $toneCfg
     */
    private function buildOllamaPrompt(string $draft, array $facts, string $tone, array $toneCfg): string
    {
        return $this->buildWeatherSystemPrompt($tone, $toneCfg) .
            "\n\nFacts JSON: " . json_encode($facts, JSON_UNESCAPED_SLASHES) .
            "\n\nDraft: {$draft}\n\nRewrite:";
    }

    private function toneSpecificGuidance(string $tone): string
    {
        return match ($tone) {
            'friendly' => "Sound like a clear local weather presenter. Prefer 2-4 complete sentences with smoother transitions. Mention how conditions change through the day when the facts support it.",
            'formal' => "Sound like a concise professional forecast. Prefer 2-4 complete sentences. Present the dominant conditions first, then precipitation timing, wind, and temperature in a measured order.",
            default => "Keep it very short. Prefer one clear lead sentence and at most one short follow-up sentence. Drop secondary detail before becoming wordy.",
        };
    }

    private function containsInvalidWeatherPhrase(string $text): bool
    {
        $patterns = [
            '/\bdry\s+(rain|showers|drizzle|snow|sleet|hail|precipitation)\b/i',
            '/\bdroog(?:e)?\s+(regen|bui(?:en)?|buien|motregen|sneeuw|hagel|neerslag)\b/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return true;
            }
        }

        return false;
    }
}

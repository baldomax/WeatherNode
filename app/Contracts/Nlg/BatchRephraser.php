<?php

namespace App\Contracts\Nlg;

/**
 * Optional capability for rephrasers that can rewrite several drafts in a single
 * provider request. Collapsing a whole locale's forecast days into one call keeps
 * the request rate far below strict free-tier per-minute quotas (e.g. Cerebras 5 RPM).
 *
 * Implementations MUST return one entry per input id, falling back to the original
 * draft for any id the model omits or phrases invalidly — the contract never drops days.
 */
interface BatchRephraser
{
    /**
     * Rewrite a batch of deterministic drafts in one request.
     *
     * @param  array<string, array{draft: string, facts: array}>  $items  Keyed by a caller id (e.g. the date).
     * @param  string  $tone  Tone preset (brief, friendly, formal).
     * @param  (callable(): bool)|null  $reserveSlot  Called before every provider request (initial + each retry).
     *                                                Return false to abort and keep the drafts — this is how the
     *                                                caller's request budget gates and paces retries instead of
     *                                                letting them bypass the quota. Null skips gating entirely.
     * @param  string|null  $locale  Target locale (e.g. nl-nl) so the model keeps the draft's language.
     * @return array<string, string> Keyed by the same ids; each value is the rewritten text or the original draft.
     */
    public function rewriteBatch(array $items, string $tone = 'brief', ?callable $reserveSlot = null, ?string $locale = null): array;
}

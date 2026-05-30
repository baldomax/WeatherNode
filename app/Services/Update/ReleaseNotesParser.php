<?php

namespace App\Services\Update;

use Illuminate\Support\Str;

class ReleaseNotesParser
{
    /**
     * Parse release notes markdown and extract key sections
     */
    public function parse(string $markdown): array
    {
        if (empty($markdown)) {
            return [
                'summary' => null,
                'sections' => [],
                'has_breaking' => false,
                'formatted' => $markdown,
            ];
        }

        $hasBreaking = $this->hasBreakingChanges($markdown);
        $sections = $this->extractSections($markdown);
        $summary = $this->extractSummary($markdown);

        return [
            'summary' => $summary,
            'sections' => $sections,
            'has_breaking' => $hasBreaking,
            'formatted' => $markdown,
        ];
    }

    /**
     * Check if release notes contain breaking changes
     */
    private function hasBreakingChanges(string $markdown): bool
    {
        $indicators = [
            'BREAKING',
            'breaking change',
            '⚠️',
            '🚨',
            'breaking:',
            '[BREAKING]',
        ];

        foreach ($indicators as $indicator) {
            if (stripos($markdown, $indicator) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract summary (first paragraph or first few lines)
     */
    private function extractSummary(string $markdown): ?string
    {
        $lines = explode("\n", trim($markdown));
        
        // Skip header if present
        $startIndex = 0;
        if (isset($lines[0]) && strpos($lines[0], '#') === 0) {
            $startIndex = 1;
        }

        // Get first non-empty paragraph
        $summary = [];
        for ($i = $startIndex; $i < min(count($lines), $startIndex + 5); $i++) {
            $line = trim($lines[$i]);
            if (empty($line) || strpos($line, '#') === 0) {
                break;
            }
            $summary[] = $line;
        }

        return !empty($summary) ? implode(' ', $summary) : null;
    }

    /**
     * Extract sections from markdown (## headings)
     */
    private function extractSections(string $markdown): array
    {
        $sections = [];
        $lines = explode("\n", $markdown);
        $currentSection = null;
        $currentContent = [];

        foreach ($lines as $line) {
            // Check for section header (## or ###)
            if (preg_match('/^#{2,3}\s+(.+)$/', $line, $matches)) {
                // Save previous section
                if ($currentSection !== null) {
                    $sections[] = [
                        'title' => $currentSection,
                        'content' => trim(implode("\n", $currentContent)),
                    ];
                }
                
                // Start new section
                $currentSection = trim($matches[1]);
                $currentContent = [];
            } else {
                $currentContent[] = $line;
            }
        }

        // Save last section
        if ($currentSection !== null) {
            $sections[] = [
                'title' => $currentSection,
                'content' => trim(implode("\n", $currentContent)),
            ];
        }

        return $sections;
    }

    /**
     * Convert markdown to HTML (basic conversion)
     */
    public function toHtml(string $markdown): string
    {
        if ($markdown === '') {
            return '';
        }

        try {
            return Str::markdown($markdown, [
                // Release notes come from GitHub and must never execute raw HTML.
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
                'max_nesting_level' => 20,
            ]);
        } catch (\Throwable $e) {
            // Fail closed if markdown conversion fails.
            return '<p>' . nl2br(e($markdown)) . '</p>';
        }
    }
}

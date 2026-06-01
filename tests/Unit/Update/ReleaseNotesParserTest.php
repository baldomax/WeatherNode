<?php

declare(strict_types=1);

namespace Tests\Unit\Update;

use App\Services\Update\ReleaseNotesParser;
use Tests\TestCase;

class ReleaseNotesParserTest extends TestCase
{
    public function test_to_html_strips_raw_html_tags_and_handlers(): void
    {
        $parser = new ReleaseNotesParser();
        $html = $parser->toHtml(<<<'MD'
# Release
<script>alert('xss')</script>
<img src="x" onerror="alert('xss')">
MD);

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringContainsString('<h1>Release</h1>', $html);
    }

    public function test_to_html_blocks_javascript_links(): void
    {
        $parser = new ReleaseNotesParser();
        $html = $parser->toHtml('[run](javascript:alert(1))');

        $this->assertStringNotContainsString('javascript:', $html);
    }

    public function test_to_html_keeps_safe_https_links(): void
    {
        $parser = new ReleaseNotesParser();
        $html = $parser->toHtml('[docs](https://example.com/docs)');

        $this->assertStringContainsString('href="https://example.com/docs"', $html);
    }
}


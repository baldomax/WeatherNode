<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class LegalPageController extends Controller
{
    private const PAGE_MAP = [
        'privacy' => [
            'title' => 'Privacy Policy',
            'description' => 'How WeatherNode collects, uses, stores, and protects data.',
            'path' => 'docs/PRIVACY.md',
            'format' => 'markdown',
        ],
        'terms' => [
            'title' => 'Terms of Service',
            'description' => 'Terms and acceptable use rules for WeatherNode.',
            'path' => 'docs/TERMS.md',
            'format' => 'markdown',
        ],
        'about' => [
            'title' => 'About',
            'description' => 'About WeatherNode, the project mission, and open-source model.',
            'path' => 'docs/ABOUT.md',
            'format' => 'markdown',
        ],
        'license' => [
            'title' => 'License',
            'description' => 'WeatherNode software license.',
            'path' => 'LICENSE.txt',
            'format' => 'text',
        ],
        'disclaimer' => [
            'title' => 'Disclaimer',
            'description' => 'Important information about weather data accuracy and usage.',
            'path' => 'docs/DISCLAIMER.md',
            'format' => 'markdown',
            'prepend_setting' => 'contact.disclaimer',
        ],
        'notices' => [
            'title' => 'Notices',
            'description' => 'Third-party notices and acknowledgements.',
            'path' => 'docs/NOTICE.md',
            'format' => 'markdown',
        ],
    ];

    public function show(string $page)
    {
        $page = Str::lower(trim($page));
        $config = self::PAGE_MAP[$page] ?? null;

        if (!$config) {
            throw new NotFoundHttpException();
        }

        $fullPath = base_path($config['path']);
        if (!File::exists($fullPath)) {
            throw new NotFoundHttpException();
        }

        $format = $config['format'] ?? 'markdown';
        $raw = File::get($fullPath);

        $prependSettingKey = $config['prepend_setting'] ?? null;
        if (is_string($prependSettingKey) && $prependSettingKey !== '') {
            $extra = trim((string) Setting::getValue($prependSettingKey, ''));
            if ($extra !== '') {
                $raw = "## Local Disclaimer\n\n{$extra}\n\n---\n\n" . $raw;
            }
        }

        $html = null;
        $text = null;

        if ($format === 'text') {
            $text = $raw;
        } else {
            $html = Str::markdown($raw, [
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ]);
        }

        return view('weather.legal-page', [
            'pageTitle' => $config['title'],
            'metaDescription' => $config['description'],
            'pageContentHtml' => $html,
            'pageContentText' => $text,
            'lastUpdated' => date('Y-m-d', File::lastModified($fullPath)),
        ]);
    }
}

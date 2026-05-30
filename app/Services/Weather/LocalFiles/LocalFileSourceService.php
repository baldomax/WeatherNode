<?php

namespace App\Services\Weather\LocalFiles;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;

class LocalFileSourceService
{
    private ClientrawParser $clientraw;
    private RealtimeTxtParser $realtime;

    public function __construct(ClientrawParser $clientraw, RealtimeTxtParser $realtime)
    {
        $this->clientraw = $clientraw;
        $this->realtime = $realtime;
    }

    public function fetchCurrent(): ?array
    {
        $format = Setting::getValue('livedata.format', '');
        $filePath = Setting::getValue('livedata.file_path', '');
        $fetchMode = Setting::getValue('livedata.fetch_mode', 'file');
        $apiUrl = Setting::getValue('livedata.api_url', '');

        if ($filePath === '' && $apiUrl === '') {
            return null;
        }

        $content = null;
        $resolvedPath = $this->resolvePath($filePath);

        if ($fetchMode === 'local_api' && $apiUrl !== '') {
            $content = $this->fetchRemote($apiUrl);
        } elseif ($this->isHttpUrl($filePath)) {
            $content = $this->fetchRemote($filePath);
        }

        if (in_array($format, ['wd', 'meteohub', 'wswin'], true)) {
            if ($content !== null) {
                return $this->clientraw->parseContent($content);
            }
            return $this->clientraw->parse($resolvedPath);
        }

        if (in_array($format, ['cumulus', 'weathercat', 'weewx', 'weatherlink', 'wifilogger', 'MB_rt'], true)) {
            if ($content !== null) {
                return $this->realtime->parseContent($content, $format);
            }
            return $this->realtime->parse($resolvedPath, $format);
        }

        return null;
    }

    private function resolvePath(string $path): string
    {
        if (str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return $path;
        }

        return base_path($path);
    }

    private function fetchRemote(string $url): ?string
    {
        try {
            $response = Http::timeout(10)->get($url);
            if (!$response->successful()) {
                return null;
            }
            return $response->body();
        } catch (\Exception $e) {
            return null;
        }
    }

    private function isHttpUrl(string $value): bool
    {
        return str_starts_with($value, 'http://') || str_starts_with($value, 'https://');
    }
}

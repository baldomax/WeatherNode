<?php

namespace App\Services\Astronomy;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Setting;

class AuroraService
{
    private const NOAA_KP_URL = 'https://services.swpc.noaa.gov/products/noaa-planetary-k-index.json';
    private const CACHE_KEY = 'noaa_kp_index';
    private const CACHE_DURATION = 300; // 5 minutes

    /**
     * Get current Kp-index and aurora information
     */
    public function getKpIndex(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_DURATION, function () {
            try {
                $response = Http::timeout(10)->get(self::NOAA_KP_URL);

                if (!$response->successful()) {
                    Log::warning('NOAA Kp-index API returned non-200 status', [
                        'status' => $response->status(),
                    ]);
                    return $this->getDefaultResponse();
                }

                $data = $response->json();

                if (empty($data) || count($data) < 2) {
                    return $this->getDefaultResponse();
                }

                // Skip header row, get last entry
                $lastEntry = end($data);
                $kp = (float) ($lastEntry[1] ?? 0);
                
                // Get timestamp
                $timestamp = $lastEntry[0] ?? null;
                $updatedAt = $timestamp ? strtotime($timestamp) : null;

                return $this->buildResponse($kp, $updatedAt);

            } catch (\Exception $e) {
                Log::error('Failed to fetch NOAA Kp-index', [
                    'error' => $e->getMessage(),
                ]);
                return $this->getDefaultResponse();
            }
        });
    }

    /**
     * Build complete response with aurora interpretation
     */
    private function buildResponse(float $kp, ?int $updatedAt): array
    {
        $latitude = Setting::latitude();
        
        return [
            'kp' => round($kp, 1),
            'kp_int' => (int) round($kp),
            'updated_at' => $updatedAt ? date('Y-m-d H:i:s', $updatedAt) : null,
            'storm' => $this->getStormLevel($kp),
            'aurora' => $this->getAuroraInfo($kp, $latitude),
            'radio' => $this->getRadioInfo($kp),
            'a_index' => $this->estimateAIndex($kp),
            'color' => $this->getKpColor($kp),
            'scale' => $this->getKpScale(),
        ];
    }

    /**
     * Get geomagnetic storm level
     */
    private function getStormLevel(float $kp): array
    {
        return match (true) {
            $kp >= 9 => [
                'level' => 'G5',
                'name' => 'Extreme Storm',
                'name_nl' => 'Extreme Geomagnetische Storm',
                'severity' => 'extreme',
            ],
            $kp >= 8 => [
                'level' => 'G4',
                'name' => 'Severe Storm',
                'name_nl' => 'Zware Geomagnetische Storm',
                'severity' => 'severe',
            ],
            $kp >= 7 => [
                'level' => 'G3',
                'name' => 'Strong Storm',
                'name_nl' => 'Sterke Geomagnetische Storm',
                'severity' => 'strong',
            ],
            $kp >= 6 => [
                'level' => 'G2',
                'name' => 'Moderate Storm',
                'name_nl' => 'Matige Geomagnetische Storm',
                'severity' => 'moderate',
            ],
            $kp >= 5 => [
                'level' => 'G1',
                'name' => 'Minor Storm',
                'name_nl' => 'Kleine Geomagnetische Storm',
                'severity' => 'minor',
            ],
            $kp >= 4 => [
                'level' => 'G0',
                'name' => 'Active',
                'name_nl' => 'Actief',
                'severity' => 'active',
            ],
            $kp >= 3.5 => [
                'level' => null,
                'name' => 'Unsettled',
                'name_nl' => 'Onrustig',
                'severity' => 'unsettled',
            ],
            default => [
                'level' => null,
                'name' => 'Quiet',
                'name_nl' => 'Rustig',
                'severity' => 'quiet',
            ],
        };
    }

    /**
     * Get aurora viewing information
     */
    private function getAuroraInfo(float $kp, float $latitude): array
    {
        // Netherlands is around 52°N, aurora typically visible at Kp >= 7-8
        $viewingProbability = match (true) {
            $kp >= 9 => 'very_high',
            $kp >= 8 => 'high',
            $kp >= 7 => 'moderate',
            $kp >= 6 => 'low',
            $kp >= 5 => 'very_low',
            default => 'none',
        };

        $descriptionNl = match (true) {
            $kp >= 8 => 'Noorderlicht mogelijk zichtbaar in Nederland bij helder weer.',
            $kp >= 7 => 'Noorderlicht mogelijk zichtbaar in Noord-Nederland/Scandinavië.',
            $kp >= 6 => 'Noorderlicht mogelijk in Scandinavië en Noord-Duitsland.',
            $kp >= 5 => 'Noorderlicht mogelijk in Noord-Scandinavië.',
            $kp >= 4 => 'Noorderlicht alleen in poolgebieden.',
            default => 'Geen noorderlicht verwacht.',
        };

        $descriptionEn = match (true) {
            $kp >= 8 => 'Aurora may be visible in the Netherlands with clear skies.',
            $kp >= 7 => 'Aurora may be visible in Northern Netherlands/Scandinavia.',
            $kp >= 6 => 'Aurora possible in Scandinavia and Northern Germany.',
            $kp >= 5 => 'Aurora possible in Northern Scandinavia.',
            $kp >= 4 => 'Aurora only visible in polar regions.',
            default => 'No aurora expected.',
        };

        return [
            'probability' => $viewingProbability,
            'visible_at_location' => $kp >= 7 && $latitude >= 50,
            'description' => $descriptionEn,
            'description_nl' => $descriptionNl,
        ];
    }

    /**
     * Get radio aurora information (for ham radio operators)
     */
    private function getRadioInfo(float $kp): array
    {
        $active = $kp >= 3.5;
        
        $descriptionNl = match (true) {
            $kp >= 8 => 'Sterke radio aurora, 28-433MHz mogelijk over 1500+ km.',
            $kp >= 7 => 'Radio aurora actief, 28-144MHz mogelijk over lange afstand.',
            $kp >= 6 => 'Radio aurora, 50-144MHz mogelijk.',
            $kp >= 5 => 'Radio aurora, 50-144MHz mogelijk op hoge breedtegraden.',
            $kp >= 4 => 'Zwakke radio aurora mogelijk op 50-144MHz.',
            $kp >= 3.5 => 'Zeer zwakke radio aurora mogelijk.',
            default => 'Geen radio aurora.',
        };

        $descriptionEn = match (true) {
            $kp >= 8 => 'Strong radio aurora, 28-433MHz possible over 1500+ km.',
            $kp >= 7 => 'Radio aurora active, 28-144MHz possible over long distance.',
            $kp >= 6 => 'Radio aurora, 50-144MHz possible.',
            $kp >= 5 => 'Radio aurora, 50-144MHz possible at high latitudes.',
            $kp >= 4 => 'Weak radio aurora possible on 50-144MHz.',
            $kp >= 3.5 => 'Very weak radio aurora possible.',
            default => 'No radio aurora.',
        };

        return [
            'active' => $active,
            'description' => $descriptionEn,
            'description_nl' => $descriptionNl,
            'frequencies' => $kp >= 7 ? '28-433 MHz' : ($kp >= 4 ? '50-144 MHz' : null),
        ];
    }

    /**
     * Estimate A-index from Kp-index
     */
    private function estimateAIndex(float $kp): int
    {
        // Approximate A-index based on Kp
        return match (true) {
            $kp >= 9 => 400,
            $kp >= 8 => 208,
            $kp >= 7 => 132,
            $kp >= 6 => 80,
            $kp >= 5 => (int) ($kp * 6),
            $kp >= 4 => (int) ($kp * 5),
            $kp >= 3 => (int) ($kp * 4),
            $kp >= 2 => (int) ($kp * 2),
            default => (int) ($kp * 2),
        };
    }

    /**
     * Get color for Kp value
     */
    private function getKpColor(float $kp): string
    {
        return match (true) {
            $kp >= 9 => '#831843', // maroon/dark red
            $kp >= 8 => '#9333ea', // purple
            $kp >= 7 => '#dc2626', // red
            $kp >= 6 => '#ea580c', // dark orange
            $kp >= 5 => '#f97316', // orange
            $kp >= 4 => '#eab308', // yellow
            $kp >= 3 => '#84cc16', // lime/yellow-green
            default => '#22c55e', // green
        };
    }

    /**
     * Get Kp scale for visualization
     */
    private function getKpScale(): array
    {
        return [
            ['value' => 0, 'color' => '#22c55e', 'label' => 'Quiet'],
            ['value' => 1, 'color' => '#22c55e', 'label' => 'Quiet'],
            ['value' => 2, 'color' => '#84cc16', 'label' => 'Quiet'],
            ['value' => 3, 'color' => '#eab308', 'label' => 'Unsettled'],
            ['value' => 4, 'color' => '#eab308', 'label' => 'Active'],
            ['value' => 5, 'color' => '#f97316', 'label' => 'G1 Minor'],
            ['value' => 6, 'color' => '#ea580c', 'label' => 'G2 Moderate'],
            ['value' => 7, 'color' => '#dc2626', 'label' => 'G3 Strong'],
            ['value' => 8, 'color' => '#9333ea', 'label' => 'G4 Severe'],
            ['value' => 9, 'color' => '#831843', 'label' => 'G5 Extreme'],
        ];
    }

    /**
     * Get default response when API fails
     */
    private function getDefaultResponse(): array
    {
        return $this->buildResponse(0, null);
    }
}

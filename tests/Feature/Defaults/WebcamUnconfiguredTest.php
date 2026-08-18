<?php

declare(strict_types=1);

namespace Tests\Feature\Defaults;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebcamUnconfiguredTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_unconfigured_webcam_says_so_rather_than_showing_someone_elses(): void
    {
        Setting::setValue('webcam.enabled', true, 'boolean', 'webcam');
        Setting::setValue('webcam.url', '', 'string', 'webcam');

        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('meteouitgeest.nl/thumbnail', false);
        $response->assertSee('Webcam not configured');
    }

    public function test_a_configured_webcam_url_is_used(): void
    {
        Setting::setValue('webcam.enabled', true, 'boolean', 'webcam');
        Setting::setValue('webcam.url', 'https://example.test/cam.jpg', 'string', 'webcam');

        $this->get('/')->assertOk()->assertSee('https://example.test/cam.jpg', false);
    }
}

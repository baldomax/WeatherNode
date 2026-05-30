<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class LocaleUnitsMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (!Route::has('test.locale-units-probe')) {
            Route::middleware('web')->get('/__test/locale-units', function () {
                return response()->json([
                    'locale' => app()->getLocale(),
                    'units' => view()->shared('activeUnits'),
                ]);
            })->name('test.locale-units-probe');
        }
    }

    public function test_accept_language_with_region_sets_language_and_units_when_auto_is_used(): void
    {
        Setting::setValue('display.language', 'auto', 'select', 'display');
        Setting::setValue('display.unit_system', 'auto', 'select', 'display');

        $response = $this
            ->withHeader('Accept-Language', 'en-GB,en;q=0.9')
            ->get('/__test/locale-units?lang=auto&units=auto');

        $response->assertOk()->assertJson([
            'locale' => 'en-gb',
            'units' => 'uk',
        ]);
    }

    public function test_unsupported_accept_language_falls_back_to_admin_defaults(): void
    {
        Setting::setValue('display.language', 'de-de', 'select', 'display');
        Setting::setValue('display.unit_system', 'scandinavia', 'select', 'display');

        $response = $this
            ->withHeader('Accept-Language', 'zz-ZZ,zz;q=0.9')
            ->get('/__test/locale-units?lang=auto&units=auto');

        $response->assertOk()->assertJson([
            'locale' => 'de-de',
            'units' => 'scandinavia',
        ]);
    }

    public function test_language_without_region_uses_admin_default_units(): void
    {
        Setting::setValue('display.language', 'auto', 'select', 'display');
        Setting::setValue('display.unit_system', 'uk', 'select', 'display');

        $response = $this
            ->withHeader('Accept-Language', 'fr,fr;q=0.9')
            ->get('/__test/locale-units?lang=auto&units=auto');

        $response->assertOk()->assertJson([
            'locale' => 'fr-fr',
            'units' => 'uk',
        ]);
    }
}

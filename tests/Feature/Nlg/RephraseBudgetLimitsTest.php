<?php

namespace Tests\Feature\Nlg;

use App\Models\Setting;
use App\Services\Nlg\RephraseBudget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RephraseBudgetLimitsTest extends TestCase
{
    use RefreshDatabase;

    private function budget(): RephraseBudget
    {
        return app(RephraseBudget::class);
    }

    public function test_falls_back_to_minute_config_when_nothing_configured(): void
    {
        config(['nlg.rephrase.max_requests_per_minute' => 4]);
        config(['nlg.limits' => []]);

        $limits = $this->budget()->effectiveLimits('cerebras');

        $this->assertSame(4, $limits['rpm']);
        $this->assertNull($limits['rph']);
        $this->assertNull($limits['rpd']);
    }

    public function test_provider_config_defaults_apply_when_no_db_override(): void
    {
        config(['nlg.limits' => ['groq' => ['rpm' => 30, 'rph' => null, 'rpd' => 1000]]]);

        $limits = $this->budget()->effectiveLimits('groq');

        $this->assertSame(30, $limits['rpm']);
        $this->assertNull($limits['rph']);
        $this->assertSame(1000, $limits['rpd']);
    }

    public function test_db_setting_overrides_config(): void
    {
        config(['nlg.limits' => ['cerebras' => ['rpm' => 30, 'rph' => null, 'rpd' => null]]]);
        Setting::setValue('nlg.limits.rpm', '5', 'string', 'nlg');
        Setting::setValue('nlg.limits.rph', '150', 'string', 'nlg');
        Setting::setValue('nlg.limits.rpd', '2400', 'string', 'nlg');

        $limits = $this->budget()->effectiveLimits('cerebras');

        $this->assertSame(5, $limits['rpm']);
        $this->assertSame(150, $limits['rph']);
        $this->assertSame(2400, $limits['rpd']);
    }

    public function test_zero_or_blank_db_value_means_unlimited(): void
    {
        config(['nlg.limits' => ['cerebras' => ['rpd' => 2400]]]);
        Setting::setValue('nlg.limits.rpd', '0', 'string', 'nlg');

        $limits = $this->budget()->effectiveLimits('cerebras');

        $this->assertNull($limits['rpd'], '0 in the admin field means no daily limit');
    }
}

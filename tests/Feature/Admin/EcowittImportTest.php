<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\WeatherReading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class EcowittImportTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_import_requires_authentication(): void
    {
        $response = $this->post(route('admin.settings.ecowitt.import'));

        $response->assertRedirect(route('login'));
    }

    public function test_import_requires_admin_role(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $response = $this->actingAs($user)
            ->post(route('admin.settings.ecowitt.import'));

        $response->assertRedirect();
    }

    public function test_import_requires_file(): void
    {
        $response = $this->actingAs($this->adminUser())
            ->post(route('admin.settings.ecowitt.import'), []);

        $response->assertSessionHasErrors('arr_file');
    }

    public function test_import_rejects_non_arr_extension(): void
    {
        $file = UploadedFile::fake()->createWithContent('data.txt', 'not valid');

        $response = $this->actingAs($this->adminUser())
            ->post(route('admin.settings.ecowitt.import'), ['arr_file' => $file]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_import_rejects_corrupt_data(): void
    {
        $file = UploadedFile::fake()->createWithContent('ecco_lcl.arr', 'not serialized data');

        $response = $this->actingAs($this->adminUser())
            ->post(route('admin.settings.ecowitt.import'), ['arr_file' => $file]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_import_rejects_serialized_objects(): void
    {
        $content = serialize(new \stdClass());
        $file = UploadedFile::fake()->createWithContent('ecco_lcl.arr', $content);

        $response = $this->actingAs($this->adminUser())
            ->post(route('admin.settings.ecowitt.import'), ['arr_file' => $file]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_import_stores_valid_arr_file(): void
    {
        $payload = [
            'dateutc' => '2024-01-15 12:00:00',
            'tempf' => '68.0',
            'humidity' => '55',
            'baromrelin' => '29.92',
            'windspeedmph' => '5.0',
            'winddir' => '180',
        ];
        $content = serialize($payload);
        $file = UploadedFile::fake()->createWithContent('ecco_lcl.arr', $content);

        $response = $this->actingAs($this->adminUser())
            ->post(route('admin.settings.ecowitt.import'), ['arr_file' => $file]);

        $response->assertRedirect(route('admin.settings.group', 'ecowitt'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('weather_readings', []);
        $reading = WeatherReading::latest('id')->first();
        $this->assertNotNull($reading);
        $this->assertEqualsWithDelta(20.0, $reading->temperature, 0.5);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Notifications;

use App\Models\Setting;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotificationDispatcherTest extends TestCase
{
    use RefreshDatabase;

    /** Messages captured by the array mail transport configured in phpunit.xml. */
    private function sentCount(): int
    {
        return Mail::mailer()->getSymfonyTransport()->messages()->count();
    }

    private function configureEmail(string $address = 'ops@example.com'): void
    {
        Setting::setValue('notifications.enabled', '1', 'boolean', 'notifications');
        Setting::setValue('notifications.method', 'email', 'select', 'notifications');
        Setting::setValue('notifications.email', $address, 'string', 'notifications');
    }

    public function test_sends_email_when_notifications_are_configured(): void
    {
        $this->configureEmail();

        $sent = app(NotificationDispatcher::class)->send('Sensor stopped reporting', ['sensor' => 'wind']);

        $this->assertTrue($sent);
        $this->assertSame(1, $this->sentCount());
    }

    public function test_does_not_send_when_notifications_are_disabled(): void
    {
        $this->configureEmail();
        Setting::setValue('notifications.enabled', '0', 'boolean', 'notifications');

        $sent = app(NotificationDispatcher::class)->send('Sensor stopped reporting', []);

        $this->assertFalse($sent);
        $this->assertSame(0, $this->sentCount());
    }

    public function test_does_not_send_when_no_recipient_is_configured(): void
    {
        $this->configureEmail('');

        $sent = app(NotificationDispatcher::class)->send('Sensor stopped reporting', []);

        $this->assertFalse($sent);
        $this->assertSame(0, $this->sentCount());
    }

    public function test_respects_per_type_toggle(): void
    {
        $this->configureEmail();
        Setting::setValue('notifications.sensor_offline', '0', 'boolean', 'notifications');

        $sent = app(NotificationDispatcher::class)->send('Sensor stopped reporting', [], 'sensor_offline');

        $this->assertFalse($sent);
        $this->assertSame(0, $this->sentCount());
    }

    public function test_rate_limits_repeat_alerts_for_the_same_key(): void
    {
        $this->configureEmail();
        $dispatcher = app(NotificationDispatcher::class);

        $this->assertTrue($dispatcher->send('Sensor stopped reporting', ['sensor' => 'wind'], 'sensor_offline'));
        $this->assertFalse($dispatcher->send('Sensor stopped reporting', ['sensor' => 'wind'], 'sensor_offline'));

        $this->assertSame(1, $this->sentCount());
    }

    public function test_recovery_notice_is_not_blocked_by_the_failure_rate_limit(): void
    {
        $this->configureEmail();
        $dispatcher = app(NotificationDispatcher::class);

        $dispatcher->send('Sensor stopped reporting', ['sensor' => 'wind'], 'sensor_offline');
        $sent = $dispatcher->send('Sensor reporting again', ['sensor' => 'wind'], 'sensor_offline');

        $this->assertTrue($sent);
        $this->assertSame(2, $this->sentCount());
    }
}

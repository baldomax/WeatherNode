<?php

namespace App\Services\Notifications;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Single delivery path for operational alerts (email and/or webhook), driven by
 * the notifications.* settings that Admin → Settings → Notifications writes.
 *
 * Every producer of operational alerts should go through here, so that a
 * recipient configured once in the admin panel applies to all of them.
 */
class NotificationDispatcher
{
    /**
     * Returns true when the alert was delivered by at least one channel.
     * A false return is a normal outcome (disabled, unconfigured, rate limited)
     * and is always logged.
     */
    public function send(string $subject, array $details = [], ?string $type = null): bool
    {
        if (!Setting::getValue('notifications.enabled', false)) {
            Log::warning("Alert (notifications disabled): {$subject}", $details);
            return false;
        }

        if ($type && !Setting::getValue("notifications.{$type}", true)) {
            Log::info("Alert suppressed (type disabled): {$subject}", $details);
            return false;
        }

        $method = Setting::getValue('notifications.method', 'email');
        $email = (string) Setting::getValue('notifications.email', '');
        $webhookUrl = (string) Setting::getValue('notifications.webhook_url', '');

        $sendEmail = in_array($method, ['email', 'both'], true) && $email !== '';
        $sendWebhook = in_array($method, ['webhook', 'both'], true) && $webhookUrl !== '';

        if (!$sendEmail && !$sendWebhook) {
            Log::warning("Alert (no notification method configured): {$subject}", $details);
            return false;
        }

        $alertKey = 'alert_' . md5($subject . serialize($details));
        $lastAlert = Cache::get($alertKey);
        if ($lastAlert && $lastAlert->diffInMinutes(now()) < 60) {
            Log::info("Alert suppressed (rate limit): {$subject}", $details);
            return false;
        }

        $success = false;

        if ($sendEmail) {
            $success = $this->sendEmail($subject, $details, $email) || $success;
        }

        if ($sendWebhook) {
            $success = $this->sendWebhook($subject, $details, $type, $webhookUrl) || $success;
        }

        if ($success) {
            Cache::put($alertKey, now(), now()->addHours(1));
        }

        return $success;
    }

    private function sendEmail(string $subject, array $details, string $email): bool
    {
        try {
            $body = "Weather Station Alert: {$subject}\n\n";
            if ($details !== []) {
                $body .= "Details:\n";
                foreach ($details as $key => $value) {
                    $body .= "  {$key}: " . (is_array($value) ? json_encode($value) : $value) . "\n";
                }
            }
            $body .= "\nTime: " . now()->toDateTimeString() . "\n";

            Mail::raw($body, function ($mail) use ($email, $subject) {
                $mail->to($email)->subject("[WeatherNode] {$subject}");
            });

            Log::info("Alert email sent: {$subject}", ['email' => $email, 'details' => $details]);
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send alert email', [
                'error' => $e->getMessage(),
                'subject' => $subject,
                'email' => $email,
            ]);
            return false;
        }
    }

    private function sendWebhook(string $subject, array $details, ?string $type, string $webhookUrl): bool
    {
        try {
            $payload = [
                'subject' => $subject,
                'details' => $details,
                'timestamp' => now()->toIso8601String(),
                'alert_type' => $type,
            ];

            $ch = curl_init($webhookUrl);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'User-Agent: WeatherNode/1.0',
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($httpCode >= 200 && $httpCode < 300) {
                Log::info("Alert webhook sent: {$subject}", ['webhook' => $webhookUrl, 'details' => $details]);
                return true;
            }

            Log::error('Webhook returned error', [
                'http_code' => $httpCode,
                'response' => $response,
                'error' => $error,
                'subject' => $subject,
                'webhook' => $webhookUrl,
            ]);
            return false;
        } catch (\Exception $e) {
            Log::error('Failed to send webhook', [
                'error' => $e->getMessage(),
                'subject' => $subject,
                'webhook' => $webhookUrl,
            ]);
            return false;
        }
    }
}

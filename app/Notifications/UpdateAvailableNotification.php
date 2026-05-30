<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UpdateAvailableNotification extends Notification
{
    use Queueable;

    private array $release;

    public function __construct(array $release)
    {
        $this->release = $release;
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $version = $this->release['tag'] ?? 'unknown';
        $appUrl = config('app.url');
        $updatesUrl = $appUrl . '/admin/settings/updates';

        $message = (new MailMessage)
            ->subject('WeatherNode Update Available: ' . $version)
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('A new version of WeatherNode is available: **' . $version . '**')
            ->line('You can update your installation from the admin panel.');

        if (isset($this->release['body']) && !empty($this->release['body'])) {
            // Extract summary (first paragraph)
            $body = $this->release['body'];
            $lines = explode("\n", trim($body));
            $summary = '';
            foreach (array_slice($lines, 0, 3) as $line) {
                $line = trim($line);
                if (!empty($line) && strpos($line, '#') !== 0) {
                    $summary .= $line . ' ';
                }
            }
            if (!empty($summary)) {
                $message->line('**What\'s new:** ' . trim($summary));
            }
        }

        $message->action('View Updates', $updatesUrl)
            ->line('Thank you for using WeatherNode!');

        return $message;
    }
}

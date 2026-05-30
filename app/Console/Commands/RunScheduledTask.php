<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class RunScheduledTask extends Command
{
    protected $signature = 'scheduler:run-task
                            {--task= : Human-readable task id (used in log prefix)}
                            {--artisan= : Artisan command to execute, e.g. "weather:fetch --save"}
                            {--log= : Log filename under storage/logs (e.g. weather-fetch.log)}';

    protected $description = 'Run a scheduled artisan task and write timestamped output lines to a dedicated log file';

    public function handle(): int
    {
        $task = trim((string) $this->option('task'));
        $artisan = trim((string) $this->option('artisan'));
        $logFile = trim((string) $this->option('log'));

        if ($task === '' || $artisan === '' || $logFile === '') {
            return self::FAILURE;
        }

        $logPath = storage_path('logs/' . ltrim($logFile, '/'));
        File::ensureDirectoryExists(dirname($logPath));

        $this->writeLine($logPath, $task, 'INFO', "START {$artisan}");

        $tokens = preg_split('/\s+/', $artisan) ?: [];
        $tokens = array_values(array_filter($tokens, static fn ($token) => $token !== ''));

        $process = new Process([
            PHP_BINARY,
            base_path('artisan'),
            ...$tokens,
        ], base_path());

        $process->setTimeout(null);

        $stdoutBuffer = '';
        $stderrBuffer = '';

        $process->run(function (string $type, string $chunk) use (&$stdoutBuffer, &$stderrBuffer, $logPath, $task): void {
            if ($type === Process::ERR) {
                $stderrBuffer .= $chunk;
                $stderrBuffer = $this->flushCompleteLines($stderrBuffer, $logPath, $task, 'ERROR');
                return;
            }

            $stdoutBuffer .= $chunk;
            $stdoutBuffer = $this->flushCompleteLines($stdoutBuffer, $logPath, $task, 'INFO');
        });

        if ($stdoutBuffer !== '') {
            $this->flushRemainder($stdoutBuffer, $logPath, $task, 'INFO');
        }

        if ($stderrBuffer !== '') {
            $this->flushRemainder($stderrBuffer, $logPath, $task, 'ERROR');
        }

        $exitCode = $process->getExitCode() ?? self::FAILURE;
        $this->writeLine($logPath, $task, $exitCode === 0 ? 'INFO' : 'ERROR', "END exit_code={$exitCode}");

        return $exitCode;
    }

    private function flushCompleteLines(string $buffer, string $logPath, string $task, string $level): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $buffer);

        if (!str_contains($normalized, "\n")) {
            return $normalized;
        }

        $parts = explode("\n", $normalized);
        $remainder = array_pop($parts);

        foreach ($parts as $line) {
            $this->writeLine($logPath, $task, $level, $line);
        }

        return (string) $remainder;
    }

    private function flushRemainder(string $buffer, string $logPath, string $task, string $level): void
    {
        $line = str_replace(["\r\n", "\r"], "\n", $buffer);
        $line = trim($line);

        if ($line === '') {
            return;
        }

        $this->writeLine($logPath, $task, $level, $line);
    }

    private function writeLine(string $logPath, string $task, string $level, string $message): void
    {
        $message = preg_replace('/\x1b\[[0-9;]*[A-Za-z]/', '', $message) ?? $message;
        $message = trim($message);

        if ($message === '') {
            return;
        }

        $timestamp = now()->format('Y-m-d H:i:s');
        File::append($logPath, sprintf('[%s] [%s] [%s] %s', $timestamp, $level, $task, $message) . PHP_EOL);
    }
}

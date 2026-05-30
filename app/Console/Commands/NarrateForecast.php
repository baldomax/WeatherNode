<?php

namespace App\Console\Commands;

use App\Contracts\Nlg\Narrator;
use Illuminate\Console\Command;

class NarrateForecast extends Command
{
    protected $signature = 'forecast:narrate {file : Path to JSON file}';
    protected $description = 'Generate human-readable forecast text from JSON input';

    public function handle(Narrator $narrator): int
    {
        $file = $this->argument('file');
        if (!is_file($file)) {
            $this->error("File not found: {$file}");
            return self::FAILURE;
        }

        $json = file_get_contents($file);
        $data = json_decode($json, true);

        if (!is_array($data)) {
            $this->error("Invalid JSON.");
            return self::FAILURE;
        }

        $text = $narrator->narrate($data);

        $this->line($text);
        return self::SUCCESS;
    }
}

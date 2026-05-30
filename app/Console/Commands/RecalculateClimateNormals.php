<?php

namespace App\Console\Commands;

use App\Models\ClimateRecord;
use App\Models\DailySummary;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RecalculateClimateNormals extends Command
{
    protected $signature = 'weather:recalculate-normals';
    protected $description = 'Recalculate climate normals (averages) from all historical daily summaries';

    public function handle(): int
    {
        $this->info('Recalculating climate normals from daily summaries...');

        $driver = DB::connection()->getDriverName();
        $updated = 0;

        for ($month = 1; $month <= 12; $month++) {
            $daysInMonth = $month === 2 ? 29 : (in_array($month, [4, 6, 9, 11]) ? 30 : 31);

            for ($day = 1; $day <= $daysInMonth; $day++) {
                if ($driver === 'sqlite') {
                    $summaries = DailySummary::whereRaw('CAST(strftime("%m", date) AS INTEGER) = ?', [$month])
                        ->whereRaw('CAST(strftime("%d", date) AS INTEGER) = ?', [$day])
                        ->get();
                } else {
                    $summaries = DailySummary::whereRaw('MONTH(date) = ?', [$month])
                        ->whereRaw('DAY(date) = ?', [$day])
                        ->get();
                }

                if ($summaries->isEmpty()) {
                    continue;
                }

                $record = ClimateRecord::firstOrNew([
                    'month' => $month,
                    'day' => $day,
                ]);

                $record->avg_high = round($summaries->avg('temp_high'), 2);
                $record->avg_low = round($summaries->avg('temp_low'), 2);
                $record->avg_temp = round($summaries->avg('temp_avg'), 2);
                $record->avg_precipitation = round($summaries->avg('rain_total'), 2);
                $record->save();

                $updated++;
            }
        }

        $this->info("Updated {$updated} climate normal records.");

        return Command::SUCCESS;
    }
}

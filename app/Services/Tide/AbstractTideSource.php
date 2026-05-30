<?php

namespace App\Services\Tide;

abstract class AbstractTideSource implements TideSourceInterface
{
    /**
     * Detect high and low tide events using a sliding-window local-extremum search.
     */
    protected function detectTideEvents(array $series, int $nowMs): array
    {
        $events = [];
        $count  = count($series);
        $window = 6;

        for ($i = $window; $i < $count - $window; $i++) {
            $current = $series[$i]['value'];
            $isMax   = true;
            $isMin   = true;

            for ($j = $i - $window; $j <= $i + $window; $j++) {
                if ($j === $i) {
                    continue;
                }
                if ($series[$j]['value'] > $current) {
                    $isMax = false;
                }
                if ($series[$j]['value'] < $current) {
                    $isMin = false;
                }
            }

            if ($isMax || $isMin) {
                $events[] = [
                    'timestamp'      => $series[$i]['timestamp'],
                    'timestamp_unix' => $series[$i]['timestamp_unix'],
                    'type'           => $isMax ? 'high' : 'low',
                    'level_cm'       => $series[$i]['value'],
                ];
            }
        }

        // Enforce a minimum 3-hour gap between consecutive events to filter duplicates.
        $filtered  = [];
        $lastEvent = null;

        foreach ($events as $event) {
            $gapMs = $lastEvent
                ? abs($event['timestamp_unix'] - $lastEvent['timestamp_unix'])
                : PHP_INT_MAX;

            if ($gapMs >= 3 * 3_600_000) {
                $filtered[] = $event;
                $lastEvent  = $event;
            }
        }

        return $filtered;
    }

    /**
     * Determine whether the tide is currently rising or falling.
     * Compares water level 1.5 h before now to 1.5 h after now.
     */
    protected function determineTrend(array $series, int $nowMs): string
    {
        $windowMs = 90 * 60 * 1000;
        $before   = null;
        $after    = null;

        foreach ($series as $point) {
            if ($point['timestamp_unix'] <= $nowMs - $windowMs) {
                $before = $point['value'];
            }
            if ($point['timestamp_unix'] >= $nowMs + $windowMs && $after === null) {
                $after = $point['value'];
            }
        }

        if ($before === null || $after === null) {
            return 'steady';
        }

        $diff = $after - $before;

        if ($diff > 5) {
            return 'rising';
        }
        if ($diff < -5) {
            return 'falling';
        }

        return 'steady';
    }

    /**
     * Merge two point arrays, deduplicate by timestamp, and sort chronologically.
     */
    protected function mergeAndSort(array $points): array
    {
        $seen   = [];
        $unique = [];

        foreach ($points as $p) {
            $key = $p['timestamp_unix'];
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $unique[]   = $p;
            }
        }

        usort($unique, static fn ($a, $b) => $a['timestamp_unix'] <=> $b['timestamp_unix']);

        return $unique;
    }
}

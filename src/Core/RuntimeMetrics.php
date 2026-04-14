<?php

declare(strict_types=1);

namespace Marwa\DebugBar\Core;

final class RuntimeMetrics
{
    public static function requestDurationMs(DebugState $state): float
    {
        $endTimestamp = self::lastMarkTimestamp($state) ?? microtime(true);

        return round(($endTimestamp - $state->requestStart) * 1000, 2);
    }

    public static function memoryPeakMb(): float
    {
        return round(memory_get_peak_usage(true) / 1048576, 2);
    }

    public static function responseBytes(): int
    {
        if (ob_get_level() === 0) {
            return 0;
        }

        $length = 0;
        foreach (ob_get_status(true) as $buffer) {
            $length += (int) ($buffer['buffer_used'] ?? 0);
        }

        return $length;
    }

    /**
     * @return list<array{from_label:string,to_label:string,delta_ms:float}>
     */
    public static function timelineSpans(DebugState $state): array
    {
        if (count($state->marks) < 2) {
            return [];
        }

        $marks = $state->marks;
        usort($marks, static fn(array $left, array $right): int => $left['t'] <=> $right['t']);

        $spans = [];
        for ($index = 1, $count = count($marks); $index < $count; $index++) {
            $previous = $marks[$index - 1];
            $current = $marks[$index];

            $spans[] = [
                'from_label' => $previous['label'],
                'to_label' => $current['label'],
                'delta_ms' => round(($current['t'] - $previous['t']) * 1000, 2),
            ];
        }

        return $spans;
    }

    private static function lastMarkTimestamp(DebugState $state): ?float
    {
        if ($state->marks === []) {
            return null;
        }

        $marks = $state->marks;
        $last = end($marks);

        return $last['t'];
    }
}

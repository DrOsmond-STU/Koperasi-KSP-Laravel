<?php

namespace App\Support;

/**
 * Shared helpers for the inline-SVG dashboard/report charts — smoothing the
 * trend line and building the tooltip payload every chart's [data-tooltip]
 * attribute expects. The hover/legend behaviour itself lives in the shared
 * JS/CSS in layouts/app.blade.php (any <div class="chart-wrap"> gets it for
 * free); this class just keeps the PHP side of that contract in one place
 * instead of re-implementing it per dashboard view.
 */
class DashboardChart
{
    /**
     * Catmull-Rom -> cubic Bezier, so a trend line curves smoothly through
     * every point instead of the sharp corners a plain polyline draws.
     *
     * @param  array<int, array{0: float|int, 1: float|int}>  $points
     */
    public static function smoothPath(array $points): string
    {
        $n = count($points);

        if ($n < 2) {
            return '';
        }

        $d = 'M '.$points[0][0].','.$points[0][1];

        for ($i = 0; $i < $n - 1; $i++) {
            $p0 = $points[$i - 1] ?? $points[$i];
            $p1 = $points[$i];
            $p2 = $points[$i + 1];
            $p3 = $points[$i + 2] ?? $p2;

            $cp1x = $p1[0] + ($p2[0] - $p0[0]) / 6;
            $cp1y = $p1[1] + ($p2[1] - $p0[1]) / 6;
            $cp2x = $p2[0] - ($p3[0] - $p1[0]) / 6;
            $cp2y = $p2[1] - ($p3[1] - $p1[1]) / 6;

            $d .= " C {$cp1x},{$cp1y} {$cp2x},{$cp2y} {$p2[0]},{$p2[1]}";
        }

        return $d;
    }

    /**
     * @param  array<int, array{label: string, value: string, color: string, strong?: bool}>  $rows
     */
    public static function tooltip(string $title, array $rows): string
    {
        return json_encode(['title' => $title, 'rows' => $rows], JSON_UNESCAPED_UNICODE);
    }

    public static function money(float|int|string $amount): string
    {
        return 'Rp '.number_format((float) $amount, 0, ',', '.');
    }
}

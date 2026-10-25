<?php
/**
 * Small server-side SVG charts. They need no JavaScript and no outside library.
 */

/**
 * A bar chart with labels. $labels and $values have the same length.
 * Each bar has a <title> so the number shows on hover and reads aloud.
 */
function svg_bar_chart(array $labels, array $values, string $title, string $unit = ''): string
{
    $count = count($values);
    if ($count === 0 || $count !== count($labels)) {
        return '';
    }
    $w = 640;
    $h = 220;
    $padLeft = 36;
    $padBottom = 34;
    $padTop = 16;
    $plotW = $w - $padLeft - 8;
    $plotH = $h - $padBottom - $padTop;
    $max = max(1, (int)ceil(max($values)));
    // Round the top of the axis to a tidy number
    $step = max(1, (int)ceil($max / 4));
    $top = $step * 4;

    $slot = $plotW / $count;
    $barW = max(4, $slot * 0.62);
    $out = '<svg class="chart" viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-label="' . e($title) . '" xmlns="http://www.w3.org/2000/svg">';
    $out .= '<title>' . e($title) . '</title>';

    for ($i = 0; $i <= 4; $i++) {
        $value = $step * $i;
        $y = $padTop + $plotH - ($value / $top) * $plotH;
        $out .= '<line x1="' . $padLeft . '" y1="' . round($y, 1) . '" x2="' . ($w - 8) . '" y2="' . round($y, 1) . '" class="chart-grid"/>';
        $out .= '<text x="' . ($padLeft - 6) . '" y="' . round($y + 4, 1) . '" class="chart-axis" text-anchor="end">' . $value . '</text>';
    }

    $every = $count > 16 ? 3 : ($count > 8 ? 2 : 1);
    foreach ($values as $i => $value) {
        $barH = ($value / $top) * $plotH;
        $x = $padLeft + $i * $slot + ($slot - $barW) / 2;
        $y = $padTop + $plotH - $barH;
        $out .= '<rect x="' . round($x, 1) . '" y="' . round($y, 1) . '" width="' . round($barW, 1) . '" height="' . round(max($barH, $value > 0 ? 1 : 0), 1) . '" rx="3" class="chart-bar"><title>'
            . e($labels[$i] . ': ' . $value . ($unit !== '' ? ' ' . $unit : '')) . '</title></rect>';
        if ($i % $every === 0) {
            $out .= '<text x="' . round($x + $barW / 2, 1) . '" y="' . ($h - 12) . '" class="chart-axis" text-anchor="middle">' . e($labels[$i]) . '</text>';
        }
    }
    return $out . '</svg>';
}

/** Counts per day for the last $days days, ending today. Returns [labels, values]. */
function daily_counts(mysqli $conn, string $table, string $column, int $days = 14): array
{
    $allowed = ['medicine_orders' => 'created_at', 'appointments' => 'created_at', 'ambulance_requests' => 'created_at'];
    if (($allowed[$table] ?? null) !== $column) {
        throw new InvalidArgumentException('Unknown chart source');
    }
    $from = (new DateTimeImmutable('today'))->modify('-' . ($days - 1) . ' days');
    $stmt = $conn->prepare("SELECT DATE($column) AS d, COUNT(*) AS c FROM $table WHERE $column >= ? GROUP BY DATE($column)");
    $fromText = $from->format('Y-m-d');
    $stmt->bind_param("s", $fromText);
    $stmt->execute();
    $found = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'c', 'd');

    $labels = [];
    $values = [];
    for ($i = 0; $i < $days; $i++) {
        $day = $from->modify("+$i days");
        $labels[] = $day->format('j M');
        $values[] = (int)($found[$day->format('Y-m-d')] ?? 0);
    }
    return [$labels, $values];
}

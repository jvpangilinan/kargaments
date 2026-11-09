<?php
// includes/report_helpers.php — small helpers shared by the report pages (Step 8, v1).
// Needs config/config.php (url(), e()) to be loaded first; every report page loads it via auth_check.php.

/** Tab bar at the top of the Manager reports. $active is 'low', 'levels' or 'sales'. */
function report_tabs(string $active): void
{
    $tabs = [
        'low'    => ['reports/low_stock_report.php',     'Low Stock'],
        'levels' => ['reports/stock_levels_report.php',  'Stock Levels'],
        'sales'  => ['reports/sales_history_report.php', 'Sales History'],
    ];
    echo '<div class="report-tabs">';
    foreach ($tabs as $key => [$path, $label]) {
        echo '<a href="' . e(url($path)) . '" class="' . ($key === $active ? 'active' : '') . '">' . e($label) . '</a>';
    }
    echo '</div>';
}

/** Returns $value if it is a real YYYY-MM-DD date, otherwise $default. */
function report_clean_date(string $value, string $default = ''): string
{
    $d = DateTime::createFromFormat('Y-m-d', $value);
    return ($d && $d->format('Y-m-d') === $value) ? $value : $default;
}

/** True when the page was opened with ?export=csv. */
function report_wants_csv(): bool
{
    return ($_GET['export'] ?? '') === 'csv';
}

/** Link to the same report (same filters) with ?export=csv added. */
function report_csv_url(string $path): string
{
    $q = $_GET;
    $q['export'] = 'csv';
    return url($path) . '?' . http_build_query($q);
}

/**
 * Sends a CSV download and stops the script. Call it BEFORE any HTML is printed.
 * Text cells that start with = + - @ are prefixed so spreadsheets do not run them as formulas.
 * Pass numbers as int/float (not strings) so negative numbers stay numbers.
 */
function report_send_csv(string $filename, array $headers, array $rows): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");   // UTF-8 BOM so Excel shows the peso sign correctly
    fputcsv($out, $headers);
    foreach ($rows as $row) {
        $clean = array_map(function ($cell) {
            if (is_string($cell) && $cell !== '' && strpbrk($cell[0], "=+-@\t\r") !== false) {
                return "'" . $cell;
            }
            return $cell;
        }, $row);
        fputcsv($out, $clean);
    }
    fclose($out);
    exit;
}

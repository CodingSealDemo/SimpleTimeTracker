<?php

require_once __DIR__ . '/../autoload.php';

use App\Utils\Session;
use App\ViewModels\WorktimeViewModel;

Session::start();
if (!Session::isAuthenticated()) {
    header('Location: /login.php');
    exit;
}

$month = $_GET['month'] ?? date('Y-m');
$vm = new WorktimeViewModel($month);
$monthlyData = $vm->getMonthlyRecords();

$filename = "Arbeitszeiten_" . $month . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Output UTF-8 BOM for proper rendering in Microsoft Excel
echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');

// CSV Headers matching user prompt spec: Datum | Arbeitsanfang | Arbeitsende | Geleistete Stunden
fputcsv($output, ['Datum', 'Arbeitsanfang', 'Arbeitsende', 'Geleistete Stunden'], ';');

foreach ($monthlyData['entries'] as $entry) {
    fputcsv($output, [
        $entry['formatted_date'],
        $entry['formatted_start'],
        $entry['formatted_end'],
        $entry['hours_formatted'],
    ], ';');
}

// Summary Row
fputcsv($output, [], ';');
fputcsv($output, ['Gesamtstunden', '', '', $monthlyData['total_hours_formatted']], ';');

fclose($output);
exit;

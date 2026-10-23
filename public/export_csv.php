<?php
require_once __DIR__ . '/../autoload.php';

use App\Utils\Session;
use App\ViewModels\WorktimeViewModel;

Session::start();

if (!Session::isAuthenticated()) {
    header('Location: login.php');
    exit;
}

$selectedMonth = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
    $selectedMonth = date('Y-m');
}

$vm = new WorktimeViewModel($selectedMonth);
$monthlyData = $vm->getMonthlyRecords();

$filename = "arbeitszeiten_{$selectedMonth}.csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// UTF-8 BOM for Excel compatibility
fprintf($output, "\xEF\xBB\xBF");

// CSV Header
fputcsv($output, ['Datum', 'Arbeitsanfang', 'Arbeitsende', 'Geleistete Stunden']);

// Data Rows
foreach ($monthlyData['entries'] as $entry) {
    fputcsv($output, [
        $entry['formatted_date'],
        $entry['formatted_start'],
        $entry['formatted_end'],
        $entry['hours_formatted']
    ]);
}

// Total Hours Summary Row
fputcsv($output, []);
fputcsv($output, ['Gesamtstunden', '', '', $monthlyData['total_hours_formatted']]);

fclose($output);
exit;

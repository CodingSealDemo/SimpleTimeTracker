<?php

namespace App\ViewModels;

use App\Models\WorktimeModel;
use App\Models\PtoModel;
use App\Utils\StreakCalculator;
use DateTime;

class WorktimeViewModel
{
    private string $selectedMonth;

    public function __construct(?string $month = null)
    {
        $this->selectedMonth = $month ?: date('Y-m');
        if (!preg_match('/^\d{4}-\d{2}$/', $this->selectedMonth)) {
            $this->selectedMonth = date('Y-m');
        }
    }

    public function getSelectedMonth(): string
    {
        return $this->selectedMonth;
    }

    public function getActiveClockIn(): ?array
    {
        return WorktimeModel::getActiveClockIn();
    }

    public function isClockedIn(): bool
    {
        return $this->getActiveClockIn() !== null;
    }

    public function getCurrentStreak(): int
    {
        return StreakCalculator::calculateCurrentStreak();
    }

    public function getMonthlyRecords(): array
    {
        $raw = WorktimeModel::getWorkForMonth($this->selectedMonth);
        $records = [];
        $totalMinutes = 0;

        foreach ($raw as $entry) {
            $date = $entry['date_date'];
            $start = $entry['start_time'];
            $end = $entry['end_time'];

            $formattedDate = $this->formatGermanDate($date);
            $formattedStart = $start ? date('H:i', strtotime($start)) . ' Uhr' : '-';
            $formattedEnd = $end ? date('H:i', strtotime($end)) . ' Uhr' : '-';

            $hoursNum = 0.0;
            $hoursFormatted = '-';

            if ($start && $end) {
                $dtStart = new DateTime($start);
                $dtEnd = new DateTime($end);
                $diff = $dtStart->diff($dtEnd);
                $minutes = ($diff->h * 60) + $diff->i;
                $totalMinutes += $minutes;
                $hoursNum = round($minutes / 60, 2);
                $hoursFormatted = number_format($hoursNum, 2, ',', '.') . ' Std.';
            } elseif ($start && !$end) {
                $hoursFormatted = 'Aktiv';
            }

            $records[] = [
                'id' => $entry['id'],
                'date_date' => $date,
                'formatted_date' => $formattedDate,
                'start_time' => $start,
                'formatted_start' => $formattedStart,
                'end_time' => $end,
                'formatted_end' => $formattedEnd,
                'hours_num' => $hoursNum,
                'hours_formatted' => $hoursFormatted,
            ];
        }

        $totalHoursNum = round($totalMinutes / 60, 2);
        $totalHoursFormatted = number_format($totalHoursNum, 2, ',', '.') . ' Std.';

        return [
            'entries' => $records,
            'total_hours_num' => $totalHoursNum,
            'total_hours_formatted' => $totalHoursFormatted,
        ];
    }

    public function getPtoMonthData(): array
    {
        return PtoModel::getPtoDaysForMonth($this->selectedMonth);
    }

    private function formatGermanDate(string $dateStr): string
    {
        $dt = new DateTime($dateStr);
        $days = [1 => 'Mo', 2 => 'Di', 3 => 'Mi', 4 => 'Do', 5 => 'Fr', 6 => 'Sa', 7 => 'So'];
        $dayOfWeek = $days[(int)$dt->format('N')];
        return $dt->format('d.m.Y') . " ({$dayOfWeek})";
    }
}

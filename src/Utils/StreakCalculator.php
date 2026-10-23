<?php

namespace App\Utils;

use App\Models\WorktimeModel;
use App\Models\PtoModel;
use DateTime;
use DateInterval;

class StreakCalculator
{
    /**
     * Calculates the current streak of work days where the user clocked in and out.
     * Weekends (Saturday, Sunday) and PTO days do NOT break the streak.
     */
    public static function calculateCurrentStreak(?string $refDate = null): int
    {
        $completedWorkDays = WorktimeModel::getAllCompletedWorkDays();
        $ptoDays = PtoModel::getAllPtoDates();

        $workSet = array_flip($completedWorkDays);
        $ptoSet = array_flip($ptoDays);

        $todayStr = $refDate ?? date('Y-m-d');
        $currentDate = new DateTime($todayStr);

        // If today is a weekend or PTO day or not yet completed today, check if yesterday was worked or if streak carries over.
        // First check if today is completed:
        $todayFormatted = $currentDate->format('Y-m-d');

        // If today is not worked, check if today is in progress or if today is weekend/PTO
        $isTodayWorked = isset($workSet[$todayFormatted]);
        $isTodayWeekendOrPto = self::isWeekendOrPto($currentDate, $ptoSet);

        // If today is not worked and not a weekend/PTO, we start checking from yesterday.
        if (!$isTodayWorked && !$isTodayWeekendOrPto) {
            $currentDate->sub(new DateInterval('P1D'));
        }

        $streak = 0;
        $maxLookback = 365; // Prevent infinite loop safety check

        while ($maxLookback > 0) {
            $dateStr = $currentDate->format('Y-m-d');

            if (isset($workSet[$dateStr])) {
                $streak++;
                $currentDate->sub(new DateInterval('P1D'));
            } else if (self::isWeekendOrPto($currentDate, $ptoSet)) {
                // Weekend or PTO doesn't add to count, but maintains the streak bridge
                $currentDate->sub(new DateInterval('P1D'));
            } else {
                // First unworked workday breaks streak
                break;
            }

            $maxLookback--;
        }

        return $streak;
    }

    private static function isWeekendOrPto(DateTime $date, array $ptoSet): bool
    {
        $dayOfWeek = (int)$date->format('N'); // 6 = Sat, 7 = Sun
        if ($dayOfWeek >= 6) {
            return true;
        }

        $dateStr = $date->format('Y-m-d');
        return isset($ptoSet[$dateStr]);
    }
}

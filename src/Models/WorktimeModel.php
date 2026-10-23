<?php

namespace App\Models;

use App\Utils\Database;
use App\Utils\Crypto;

class WorktimeModel
{
    public static function getActiveClockIn(): ?array
    {
        $db = Database::getConnection();
        $today = date('Y-m-d');
        $stmt = $db->prepare('SELECT * FROM work WHERE date_date = :today AND end_time IS NULL ORDER BY id DESC LIMIT 1');
        $stmt->bindValue(':today', $today, SQLITE3_TEXT);
        $res = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

        if (!$res) {
            return null;
        }

        return [
            'id' => $res['id'],
            'date_date' => $res['date_date'],
            'start_time' => Crypto::decrypt($res['start_time']),
            'end_time' => $res['end_time'] ? Crypto::decrypt($res['end_time']) : null,
        ];
    }

    public static function clockIn(): bool
    {
        // Enforce once per day / only when not currently clocked in
        $active = self::getActiveClockIn();
        if ($active !== null) {
            return false;
        }

        $db = Database::getConnection();
        $today = date('Y-m-d');
        $nowTime = date('Y-m-d H:i:s');

        // Check if already clocked in and out today
        $stmtCheck = $db->prepare('SELECT COUNT(*) as count FROM work WHERE date_date = :today');
        $stmtCheck->bindValue(':today', $today, SQLITE3_TEXT);
        $res = $stmtCheck->execute()->fetchArray(SQLITE3_ASSOC);
        if ($res['count'] > 0) {
            // User can only clock in once per day
            return false;
        }

        $encryptedStart = Crypto::encrypt($nowTime);
        $stmt = $db->prepare('INSERT INTO work (date_date, start_time, end_time) VALUES (:date_date, :start_time, NULL)');
        $stmt->bindValue(':date_date', $today, SQLITE3_TEXT);
        $stmt->bindValue(':start_time', $encryptedStart, SQLITE3_TEXT);
        return (bool)$stmt->execute();
    }

    public static function clockOut(): bool
    {
        $active = self::getActiveClockIn();
        if ($active === null) {
            return false;
        }

        $db = Database::getConnection();
        $nowTime = date('Y-m-d H:i:s');
        $encryptedEnd = Crypto::encrypt($nowTime);

        $stmt = $db->prepare('UPDATE work SET end_time = :end_time WHERE id = :id');
        $stmt->bindValue(':end_time', $encryptedEnd, SQLITE3_TEXT);
        $stmt->bindValue(':id', $active['id'], SQLITE3_INTEGER);
        return (bool)$stmt->execute();
    }

    public static function getWorkForMonth(string $yearMonth): array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT * FROM work WHERE date_date LIKE :ym ORDER BY date_date DESC, id DESC');
        $stmt->bindValue(':ym', $yearMonth . '-%', SQLITE3_TEXT);
        $result = $stmt->execute();

        $rows = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $rows[] = [
                'id' => $row['id'],
                'date_date' => $row['date_date'],
                'start_time' => Crypto::decrypt($row['start_time']),
                'end_time' => $row['end_time'] ? Crypto::decrypt($row['end_time']) : null,
            ];
        }

        return $rows;
    }

    public static function getAllCompletedWorkDays(): array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT date_date FROM work WHERE start_time IS NOT NULL AND end_time IS NOT NULL ORDER BY date_date ASC');
        $result = $stmt->execute();

        $dates = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $dates[] = $row['date_date'];
        }

        return array_unique($dates);
    }
}

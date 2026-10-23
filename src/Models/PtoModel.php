<?php

namespace App\Models;

use App\Utils\Database;
use App\Utils\Crypto;

class PtoModel
{
    public static function getPtoDaysForMonth(string $yearMonth): array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT * FROM pto WHERE date_date LIKE :ym ORDER BY date_date ASC');
        $stmt->bindValue(':ym', $yearMonth . '-%', SQLITE3_TEXT);
        $result = $stmt->execute();

        $rows = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $rows[$row['date_date']] = [
                'id' => $row['id'],
                'date_date' => $row['date_date'],
                'note' => $row['note'] ? Crypto::decrypt($row['note']) : '',
            ];
        }

        return $rows;
    }

    public static function getAllPtoDates(): array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT date_date FROM pto ORDER BY date_date ASC');
        $result = $stmt->execute();

        $dates = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $dates[] = $row['date_date'];
        }

        return $dates;
    }

    public static function isPtoDay(string $date): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT COUNT(*) as count FROM pto WHERE date_date = :date_date');
        $stmt->bindValue(':date_date', $date, SQLITE3_TEXT);
        $res = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

        return ($res['count'] ?? 0) > 0;
    }

    public static function togglePtoDay(string $date, string $note = 'Urlaub'): bool
    {
        if (self::isPtoDay($date)) {
            $db = Database::getConnection();
            $stmt = $db->prepare('DELETE FROM pto WHERE date_date = :date_date');
            $stmt->bindValue(':date_date', $date, SQLITE3_TEXT);
            return (bool)$stmt->execute();
        } else {
            $db = Database::getConnection();
            $encNote = Crypto::encrypt($note);
            $stmt = $db->prepare('INSERT INTO pto (date_date, note) VALUES (:date_date, :note)');
            $stmt->bindValue(':date_date', $date, SQLITE3_TEXT);
            $stmt->bindValue(':note', $encNote, SQLITE3_TEXT);
            return (bool)$stmt->execute();
        }
    }
}

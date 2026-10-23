<?php

namespace App\Utils;

use SQLite3;

class Database
{
    private static ?SQLite3 $instance = null;

    public static function getConnection(): SQLite3
    {
        if (self::$instance === null) {
            $dbPath = __DIR__ . '/../../data/data.db';
            $dbDir = dirname($dbPath);
            if (!is_dir($dbDir)) {
                mkdir($dbDir, 0755, true);
            }

            self::$instance = new SQLite3($dbPath);
            self::$instance->busyTimeout(5000);
            self::initTables();
        }

        return self::$instance;
    }

    private static function initTables(): void
    {
        $db = self::$instance;

        // Users table
        $db->exec('CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL
        )');

        // Seed default user if none exists
        $stmt = $db->prepare('SELECT COUNT(*) as count FROM users');
        $result = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        if ($result['count'] == 0) {
            $defaultHash = password_hash('admin', PASSWORD_DEFAULT);
            $seedStmt = $db->prepare('INSERT INTO users (username, password_hash) VALUES (:username, :hash)');
            $seedStmt->bindValue(':username', 'admin', SQLITE3_TEXT);
            $seedStmt->bindValue(':hash', $defaultHash, SQLITE3_TEXT);
            $seedStmt->execute();
        }

        // Work records table
        $db->exec('CREATE TABLE IF NOT EXISTS work (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            date_date DATE NOT NULL,
            start_time TEXT,
            end_time TEXT
        )');

        // PTO (Vacation/Urlaub) table
        $db->exec('CREATE TABLE IF NOT EXISTS pto (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            date_date DATE UNIQUE NOT NULL,
            note TEXT
        )');
    }
}

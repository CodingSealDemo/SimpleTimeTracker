<?php

namespace App\Models;

use App\Utils\Database;
use App\Utils\Crypto;

class UserModel
{
    public static function findByUsername(string $username): ?array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT * FROM users WHERE username = :username LIMIT 1');
        $stmt->bindValue(':username', $username, SQLITE3_TEXT);
        $result = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

        return $result ?: null;
    }

    public static function verifyPassword(string $username, string $password): bool
    {
        $user = self::findByUsername($username);
        if (!$user) {
            return false;
        }
        return password_verify($password, $user['password_hash']);
    }

    public static function updatePassword(string $username, string $newPassword): bool
    {
        $db = Database::getConnection();
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $db->prepare('UPDATE users SET password_hash = :hash WHERE username = :username');
        $stmt->bindValue(':hash', $hash, SQLITE3_TEXT);
        $stmt->bindValue(':username', $username, SQLITE3_TEXT);
        return (bool)$stmt->execute();
    }
}

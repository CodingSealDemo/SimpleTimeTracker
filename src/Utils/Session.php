<?php

namespace App\Utils;

class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start([
                'cookie_httponly' => true,
                'cookie_samesite' => 'Strict',
            ]);
        }
    }

    public static function isAuthenticated(): bool
    {
        self::start();
        return !empty($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }

    public static function login(string $username): void
    {
        self::start();
        session_regenerate_id(true);
        $_SESSION['logged_in'] = true;
        $_SESSION['username'] = $username;
    }

    public static function logout(): void
    {
        self::start();
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }
        session_destroy();
    }
}

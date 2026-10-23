<?php

namespace App\Utils;

class Crypto
{
    private static ?string $key = null;

    private static function getKey(): string
    {
        if (self::$key !== null) {
            return self::$key;
        }

        $keyPath = __DIR__ . '/../../data/secret.key';
        if (!file_exists($keyPath)) {
            $keyDir = dirname($keyPath);
            if (!is_dir($keyDir)) {
                mkdir($keyDir, 0755, true);
            }
            $newKey = random_bytes(32);
            file_put_contents($keyPath, $newKey);
            chmod($keyPath, 0600);
        }

        self::$key = file_get_contents($keyPath);
        return self::$key;
    }

    public static function encrypt(string $data): string
    {
        if ($data === '') {
            return '';
        }
        $key = self::getKey();
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $encrypted);
    }

    public static function decrypt(string $data): string
    {
        if ($data === '') {
            return '';
        }
        $key = self::getKey();
        $raw = base64_decode($data);
        if (strlen($raw) < 17) {
            return $data; // Return original if not encrypted format
        }
        $iv = substr($raw, 0, 16);
        $cipher = substr($raw, 16);
        $decrypted = openssl_decrypt($cipher, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        return $decrypted !== false ? $decrypted : $data;
    }
}

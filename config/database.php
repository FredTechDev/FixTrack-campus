<?php
/**
 * FixTrack Campus - Database Connection
 * PDO connection provider with environment fallback support
 */

class Database {
    private static ?PDO $instance = null;

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $host = getenv('DB_HOST') ?: '127.0.0.1';
            $port = getenv('DB_PORT') ?: (getenv('DB_HOST') === 'db' ? '3306' : '3307');
            $db   = getenv('DB_NAME') ?: 'fixtrack_db';
            $user = getenv('DB_USER') ?: 'fixtrack_user';
            $pass = getenv('DB_PASS') ?: 'fixtrack_password';
            $charset = 'utf8mb4';

            $dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset} COLLATE utf8mb4_unicode_ci"
            ];

            try {
                self::$instance = new PDO($dsn, $user, $pass, $options);
            } catch (PDOException $e) {
                // If connecting through localhost with root fallback in testing
                try {
                    $fallbackDsn = "mysql:host=127.0.0.1;port=3307;dbname={$db};charset={$charset}";
                    self::$instance = new PDO($fallbackDsn, $user, $pass, $options);
                } catch (PDOException $inner) {
                    die("Database connection failure: " . htmlspecialchars($e->getMessage()));
                }
            }
        }

        return self::$instance;
    }
}

function get_db(): PDO {
    return Database::getConnection();
}

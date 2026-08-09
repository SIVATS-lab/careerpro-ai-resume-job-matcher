<?php
declare(strict_types=1);

/**
 * ============================================================================
 * CareerPro Suite - Database Connection (PDO Singleton)
 * ============================================================================
 *
 * Usage (from any PHP file):
 *   require_once __DIR__ . '/../includes/db.php';       // adjust path depth
 *   $db = Database::getInstance()->getConnection();
 *
 * All credentials are read from environment variables when available,
 * falling back to the constants below for local XAMPP development.
 * On a production server set the env vars instead of editing this file.
 * ============================================================================
 */

// -----------------------------------------------------------------
// LOCAL DEVELOPMENT DEFAULTS  (change to match your XAMPP setup)
// -----------------------------------------------------------------
define('DB_HOST',    getenv('DB_HOST')    ?: '127.0.0.1');
define('DB_PORT',    getenv('DB_PORT')    ?: '3306');
define('DB_NAME',    getenv('DB_NAME')    ?: 'careerpro_db');
define('DB_USER',    getenv('DB_USER')    ?: 'root');
define('DB_PASS',    getenv('DB_PASS')    ?: '');          // default XAMPP root has no password
define('DB_CHARSET', 'utf8mb4');

class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;

    /**
     * Private constructor — opens and configures the PDO connection.
     * @throws RuntimeException if the connection fails.
     */
    private function __construct()
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // throw PDOException on errors
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,         // always return assoc arrays
            PDO::ATTR_EMULATE_PREPARES   => false,                     // use real prepared statements
            PDO::ATTR_PERSISTENT         => false,                     // no persistent connections
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ];

        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Log the real error server-side; never expose credentials to the browser
            error_log('CareerPro DB Connection Error: ' . $e->getMessage());
            throw new RuntimeException(
                'Database connection failed. Please check your configuration or contact the administrator.'
            );
        }
    }

    /**
     * Returns the single Database instance (creates it on first call).
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Returns the underlying PDO object.
     */
    public function getConnection(): PDO
    {
        return $this->pdo;
    }

    // Prevent cloning and unserialization of the singleton
    private function __clone() {}
    public function __wakeup(): void
    {
        throw new \Exception('Cannot unserialize a singleton.');
    }
}

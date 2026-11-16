<?php
declare(strict_types=1);


const DB_HOST = '127.0.0.1';
const DB_PORT = 3306;
const DB_NAME = 'product_ordering';
const DB_USER = 'root';
const DB_PASS = '';


const CURRENCY = '₱';
const PAYMENT_METHODS = ['Cash', 'GCash'];


function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            DB_HOST,
            DB_PORT,
            DB_NAME
        );

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            http_response_code(500);
            exit(
                '<div style="font-family:system-ui,sans-serif;max-width:640px;margin:80px auto;padding:24px;'
                . 'border:1px solid #e5e7eb;border-radius:12px;">'
                . '<h2 style="margin-top:0;">Database connection failed</h2>'
                . '<p>Make sure MySQL is running in XAMPP and that <code>database/schema.sql</code> '
                . 'has been imported. Then check the credentials in <code>config/db.php</code>.</p>'
                . '</div>'
            );
        }
    }

    return $pdo;
}

<?php
/**
 * config/database.php
 * Koneksi database dengan PDO (OOP)
 */

class Database {
    private $host     = 'localhost';
    private $dbname   = 'inventaris';
    private $username = 'root';
    private $password = '';
    private $conn;

    public function getConnection(): PDO {
        $this->conn = null;
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset=utf8mb4";
            $this->conn = new PDO($dsn, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE,            PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->conn->setAttribute(PDO::ATTR_EMULATE_PREPARES,   false);
        } catch (PDOException $e) {
            die('<div style="font-family:sans-serif;padding:20px;background:#7f1d1d;color:#fff;border-radius:8px;">
                 <strong>Koneksi Database Gagal!</strong><br>' . $e->getMessage() . '</div>');
        }
        return $this->conn;
    }
}

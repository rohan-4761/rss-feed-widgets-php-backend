<?php

require_once __DIR__.'/../vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();


class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $charset;
    private $port;
    public $conn;

    public function __construct() {
        $this->host     = $_ENV['DB_HOST'];
        $this->db_name  = $_ENV['DB_NAME'];
        $this->username = $_ENV['DB_USER'];
        $this->password = $_ENV['DB_PASS'];
        $this->charset  = $_ENV['DB_CHARSET'] ?? 'utf8mb4';
        $this->port     = $_ENV['DB_PORT'] ?? '3306';
    }

    public function connect() {
        $this->conn = null;
        try {
            $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->db_name};charset={$this->charset}";
            $this->conn = new PDO($dsn, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $e) {
            echo json_encode(['error' => 'Connection failed: ' . $e->getMessage()]);
        }
        return $this->conn;
    }
}
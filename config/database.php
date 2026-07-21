<?php
class Database {
    private $host     = 'sql104.infinityfree.com';
    private $db_name  = 'if0_42456750_email_system';
    private $username = 'if0_42456750';
    private $password = '8k1wXAOIZkrES';
    private $port     = '3306';
    public  $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host={$this->host};
                 port={$this->port};
                 dbname={$this->db_name};
                 charset=utf8mb4",
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(
                PDO::ATTR_ERRMODE,
                PDO::ERRMODE_EXCEPTION
            );
        } catch(PDOException $e) {
            die("خطأ في الاتصال: " . $e->getMessage());
        }
        return $this->conn;
    }
}
?>
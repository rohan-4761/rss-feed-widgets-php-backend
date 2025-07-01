<?php
 
class User {
    private $conn;
    private $table = "users";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getUser($email) {
        $query = "SELECT * FROM {$this->table} WHERE user_email = :email";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":email", $email);
        $stmt->execute(); 
        return $stmt->fetch(PDO::FETCH_ASSOC); 

    }

    public function createUser($name, $email, $password) {
        
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $query = "INSERT INTO {$this->table} (user_name, user_email, password) VALUES (:name, :email, :hashedPassword)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":name", $name);
        $stmt->bindParam(":email", $email);
        $stmt->bindParam(":hashedPassword", $hashedPassword);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }
}
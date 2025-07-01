<?php

class Feed {
    private $conn;
    private $table = "news";

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public function getAllNews() {
        $query = "SELECT * FROM {$this->table} LIMIT 5";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    public function filterAndGetNews($filter){
        $query = "SELECT * FROM {$this->table} WHERE topic = :filter OR source = :filter OR author = :filter";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":filter", $filter);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
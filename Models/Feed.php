<?php

class Feed
{
    private $conn;
    private $table = "feeds";

    public function __construct($db)
    {
        $this->conn = $db;
    }
    
    public function getFeeds($options = [], $apply_options = true)
    {
        $query = "SELECT * FROM {$this->table}";
        $conditions = [];
        $params = [];
        if ($apply_options) {

            if (!empty($options['search'])) {
                $conditions[] = "(title LIKE :search OR source LIKE :search OR author LIKE :search)";
                $params[':search'] = '%' . $options['search'] . '%';
            }

            if (!empty($options['topic'])) {
                $conditions[] = "topic = :topic";
                $params[':topic'] = $options['topic'];
            }

            if (!empty($options['source'])) {
                $conditions[] = "source = :source";
                $params[':source'] = $options['source'];
            }

            if (!empty($options['author'])) {
                $conditions[] = "author = :author";
                $params[':author'] = $options['author'];
            }

            if ($conditions) {
                $query .= " WHERE " . implode(" AND ", $conditions);
            }
        }

        $query .= " ORDER BY published_at DESC";
        if(!$conditions) {
            $query .= " LIMIT 20"; // Default limit and offset
        }

        $stmt = $this->conn->prepare($query);

        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTopics()
    {
        $query = "SELECT DISTINCT topic FROM {$this->table} ORDER BY topic";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

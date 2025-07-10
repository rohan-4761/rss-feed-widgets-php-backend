<?php

class Widget
{
    private $conn;
    private $table = "widgets";

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public function getWidgets($user_id, $widget_id = null)
    {
        if ($widget_id) {
            $query = "SELECT * FROM {$this->table} WHERE user_id = :user_id AND id = :widget_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":user_id", $user_id);
            $stmt->bindParam(":widget_id", $widget_id);
        } else {
            $query = "SELECT * FROM {$this->table} WHERE user_id = :user_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":user_id", $user_id);
        }
        $stmt->execute();

        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Decode JSON fields
        foreach ($results as &$row) {
            if (!empty($row['widget_data'])) {
                $row['widget_data'] = json_decode($row['widget_data'], true);
            }
        }

        return $results;
    }


    public function createWidget($user_id, $widget_data)
    {
        $json_data = json_encode($widget_data);

        $query = "INSERT INTO {$this->table} (user_id, widget_data) VALUES (:user_id, :widget_data)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $user_id, PDO::PARAM_STR);
        $stmt->bindParam(":widget_data", $json_data, PDO::PARAM_STR);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }

    public function updateWidget($user_id, $widget_id, $widget_data)
    {
        $json_data = json_encode($widget_data);

        $query = "UPDATE {$this->table} SET widget_data = :widget_data WHERE id = :widget_id AND user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":widget_data", $json_data, PDO::PARAM_STR);
        $stmt->bindParam(":widget_id", $widget_id, PDO::PARAM_STR);
        $stmt->bindParam(":user_id", $user_id, PDO::PARAM_STR);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }

    public function deleteWidget($user_id, $widget_id)
    {
        $query = "DELETE FROM {$this->table} WHERE id = :widget_id AND user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":widget_id", $widget_id, PDO::PARAM_STR);
        $stmt->bindParam(":user_id", $user_id, PDO::PARAM_STR);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }
}

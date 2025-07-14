<?php

class Widget
{
    private $conn;
    private $table = "widgets";

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public function getWidgets($user_id=null, $widget_id = null)
    {   

        $query = "SELECT * FROM {$this->table}";
        $conditions = [];
        $params = [];
        
        if (!is_null($user_id)) {
            $conditions[] = "user_id = :user_id";
            $params[":user_id"] = $user_id;
        }
        if (!is_null($widget_id)){
            $conditions[] = "id = :widget_id";
            $params[":widget_id"] = $widget_id;
        }
        if ($conditions) {
            $query .= " WHERE " . implode(" AND ", $conditions);
        }

        $stmt = $this->conn->prepare($query);
        foreach($params as $key => $val){
            $stmt->bindValue($key, $val);
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


    public function createWidget($user_id, $widget_data, $widget_title)
    {
        $json_data = json_encode($widget_data);

        $query = "INSERT INTO {$this->table} (user_id, widget_title, widget_data) 
              VALUES (:user_id, :widget_title, :widget_data)";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $user_id, PDO::PARAM_STR);
        $stmt->bindParam(":widget_title", $widget_title, PDO::PARAM_STR);
        $stmt->bindParam(":widget_data", $json_data, PDO::PARAM_STR);

        return $stmt->execute();
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

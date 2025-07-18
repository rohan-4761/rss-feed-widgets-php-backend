<?php

class Widget
{
    private $conn;
    private $table = "widgets";

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public function selectWidgetsByUserId($userId)
    {
        $query = "SELECT id, widgetTitle, feedURL, rssFeed, createdAt, updatedAt 
                    FROM {$this->table}
                    WHERE userId = :userId";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':userId', $userId);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function selectWidgetsById($widgetId)
    {
        $query = "SELECT * FROM {$this->table} WHERE id=:widgetId";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':widgetId', $widgetId);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $result['general'] = json_decode($result['general'], true);
        $result['feedTitle'] = json_decode($result['feedTitle'], true);
        $result['feedContent'] = json_decode($result['feedContent'], true);

        return $result;
    }

    public function create($data)
    {
        $query = "INSERT INTO {$this->table}
            (userId, widgetTitle, feedURL, topic, rssFeed, widgetLayout, general, feedTitle, feedContent) 
            VALUES 
            (:userId, :widgetTitle, :feedURL, :topic, :rssFeed, :widgetLayout, :general, :feedTitle, :feedContent)";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':userId', $data['userId']);
        $stmt->bindParam(':widgetTitle', $data['widgetTitle']);
        $stmt->bindParam(':feedURL', $data['feedURL']);
        $stmt->bindParam(':topic', $data['topic']);
        $stmt->bindParam(':rssFeed', $data['rssFeed']);
        $stmt->bindParam(':widgetLayout', $data['widgetLayout']);
        $stmt->bindParam(':general', $data['general']);
        $stmt->bindParam(':feedTitle', $data['feedTitle']);
        $stmt->bindParam(':feedContent', $data['feedContent']);

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

    public function update($widgetId, $updatedFields, $updatedData)
    {
        $setClause = [];
        foreach ($updatedFields as $field) {
            $setClause[] = "`$field` = :$field";
        }

        $setString = implode(', ', $setClause);

        $query = "UPDATE " . $this->table . " SET $setString WHERE widgetId = :widgetId";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':widgetId', $widgetId, PDO::PARAM_INT);

        foreach ($updatedFields as $field) {
            $value = $updatedData[$field];

            if (in_array($field, ['general', 'feedTitle', 'feedContent']) && is_array($value)) {
                $value = json_encode($value);
            }

            $stmt->bindValue(":$field", $value);
        }

        return $stmt->execute();
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

<?php

require_once './Models/Feed.php';

class FeedController {
    private $db;
    private $feedModel;

    public function __construct($db)
    {
        $this->db = $db;
        $this->feedModel = new Feed($db);

    }

    public function getAllNews(){
        $feeds = $this->feedModel->getAllNews();

        if (!$feeds){
            echo json_encode([
                "success" => false,
                "message" => "Unable to find feeds for you."
            ]);
            return ;
        } else {
            echo json_encode([
                "success"=> true,
                "message"=> "Feeds for you",
                "feeds"=>$feeds
            ]);
        }
    }

    public function filterAndGetNews($filter){
        
        $feeds = $this->feedModel->filterAndGetNews($filter);

        if(!$feeds) {
            echo json_encode([
                "success" => false,
                "message" => "Unable to find feeds for you."
            ]);
            return ;
        } else {
            echo json_encode([
                "success"=> true,
                "message"=> "Feeds for you",
                "feeds"=>$feeds
            ]);        
        }
    }

}
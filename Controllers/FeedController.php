<?php

require_once './Models/Feed.php';
require_once './Controllers/BaseController.php';

class FeedController extends BaseController
{
    private $db;
    private $feedModel;

    public function __construct($db)
    {
        $this->db = $db;
        $this->feedModel = new Feed($db);
    }


    public function getFeeds()
    {   
        $user = $this->verifyToken();
        // $data = json_decode(file_get_contents("php://input"), true);
        $options = [
            'search' => !empty($_GET['search']) ?  htmlspecialchars($_GET["search"]) : null,
            'topic' => !empty($_GET['topic']) ?  htmlspecialchars($_GET["topic"]) : null,
            'source' => !empty($_GET['source']) ?  htmlspecialchars($_GET["source"]) : null,
            'author' => !empty($_GET['author']) ?  htmlspecialchars($_GET["author"]) : null,
        ];
        try {

            $feeds = $this->feedModel->getFeeds($options);

            if (!$feeds) {
                $allFeeds = $this->feedModel->getFeeds($options, false);
                if (!$allFeeds) {
                    header('Content-Type: application/json');

                    echo json_encode([
                        "success" => false,
                        'filter_applied' => $options,
                        "message" => "Unable to find feeds for you."
                    ]);
                    return;
                } else {
                    header('Content-Type: application/json');

                    echo json_encode([
                        'success' => true,
                        'data' => $allFeeds,
                        'count' => count($allFeeds),
                        'filter_applied' => $options,
                        'message' => 'No results found for your search. Showing all feeds instead.',
                        'is_fallback' => true
                    ]);
                }
            } else {
                header('Content-Type: application/json');

                echo json_encode([
                    'success' => true,
                    'data' => $feeds,
                    'count' => count($feeds),
                    'filter_applied' => $options,
                    'message' => 'Filtered feeds retrieved successfully',
                    'is_fallback' => false
                ]);
            }
        } catch (Exception $e) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error filtering feeds: ' . $e->getMessage()
            ]);
        }
    }

    public function getTopics()
    {   
        $user = $this->verifyToken();
        try {
            $topics = $this->feedModel->getTopics();
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $topics,
                'count' => count($topics),
                'message' => 'Topics retrieved successfully'
            ]);
        } catch (Exception $e) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error retrieving topics: ' . $e->getMessage()
            ]);
        }
    }
}

<?php
require_once './Database/config.php';
require_once './Controllers/UserController.php';
require_once './Controllers/FeedController.php';

$db = (new Database())->connect();
$userController = new UserController($db);
$feedController = new FeedController($db);

$requestMethod = $_SERVER["REQUEST_METHOD"];
$requestUri = $_SERVER["REQUEST_URI"];

if (strpos($requestUri, '/signup') !== false) {
    if ($requestMethod === 'POST') {
        $userController->createUser();
    } else {
        echo json_encode(["message" => "Method not allowed"]);
    }
} else if (strpos($requestUri, '/login') !== false){
    if($requestMethod === "POST") {
        $userController->getUser();
    }else {
        echo json_encode(["message" => "Method not allowed"]);
    }
} else if((strpos($requestUri, '/feeds') !== false) && $requestMethod === "GET") {
    if(empty($_GET) || !isset($_GET['filter'])) {
        $feedController->getAllNews();
    } else if ($_GET['filter']){
        $filter = htmlspecialchars($_GET['filter']);
        $feedController->filterAndGetNews($filter);
    }
}
else {
    echo json_encode(["message" => "Route not found"]);
}

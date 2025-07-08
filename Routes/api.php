<?php
require_once './Database/config.php';
require_once './Controllers/UserController.php';
require_once './Controllers/FeedController.php';


$db = (new Database())->connect();
$userController = new UserController($db);
$feedController = new FeedController($db);

$requestMethod = $_SERVER["REQUEST_METHOD"];
$requestUri = $_SERVER["REQUEST_URI"];

$route = '';
if (strpos($requestUri, '/signup') !== false) {
    $route = 'signup';
} elseif (strpos($requestUri, '/login') !== false) {
    $route = 'login';
} elseif (strpos($requestUri, '/feeds/topics') !== false) {
    $route = 'topics';
} elseif (strpos($requestUri, '/feeds') !== false) {
    $route = 'feeds';
} else {
    echo json_encode(["message" => "Route not found"]);
    exit;
}

$routeMethod = $route . '_' . $requestMethod;

switch ($routeMethod) {
    case 'signup_POST':
        $userController->createUser();
        break;

    case 'login_POST':
        $userController->getUser();
        break;

    case 'feeds_GET':
        $feedController->getFeeds();
        break;
    case 'topics_GET':
        $feedController->getTopics();
        break;
    default:
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(["message" => "Route not found"]);
        break;
}
?>
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
// echo "Entered api.php, $requestMethod; $requestUri";
if (strpos($requestUri, '/signup') !== false) {
    $route = 'signup';
} elseif (strpos($requestUri, '/login') !== false) {
    $route = 'login';
} elseif (strpos($requestUri, '/feeds') !== false) {
    $route = 'feeds';
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

    default:
        echo json_encode(["message" => "Route not found"]);
        break;
}
?>
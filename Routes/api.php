<?php
require_once './Config/db.php';
require_once './Controllers/UserController.php';
require_once './Controllers/FeedController.php';
require_once './Controllers/WidgetController.php';

$db = (new Database())->connect();
$userController = new UserController($db);
$feedController = new FeedController($db);
$widgetController = new WidgetController($db);

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
} elseif (strpos($requestUri, '/widgets') !== false) {
    $route = 'widgets';
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
    case 'widgets_GET':
        $widgetController->getWidgets();
        break;
    case 'widgets_POST':
        $widgetController->createWidget();
        break;
    case 'widgets_PUT':
        $widgetController->updateWidget();
        break;
    case 'widgets_DELETE':
        $widgetController->deleteWidget();
        break;
    default:
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(["message" => "Route not found"]);
        break;
}

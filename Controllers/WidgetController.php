<?php

require_once './Models/Widget.php';
require_once './Controllers/BaseController.php';
require_once './Utils/cipherID.php';

class WidgetController extends BaseController {
    private $db;
    private $widgetModel;

    public function __construct($db) {
        $this->db = $db;
        $this->widgetModel = new Widget($db);
    }

    public function getWidgets() {
        try{
            $user = $this->verifyToken();
            if (!$user) {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                return;
            }
            if (empty($user['sub'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'User ID is invalid or missing']);
                return;
            }
            $userId = decryptCipherID($user['sub']);
            if (empty($_GET['widget_id'])) {
                $widgets = $this->widgetModel->getWidgets($userId);
            } else {
                $encryptedWidgetId = htmlspecialchars($_GET['widget_id']);
                $widgetId = decryptCipherID($encryptedWidgetId);
                if (!$widgetId) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Invalid widget ID']);
                    return;
                }
                $widgets = $this->widgetModel->getWidgets($userId, $widgetId);
            }
            if ($widgets) {
                foreach ($widgets as &$widget) {
                    $widget['id'] = generateCipherID($widget['id']);
                    unset($widget['user_id']); // Remove user_id from the response
                }
                header('Content-Type: application/json');
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'Widgets retrieved successfully',
                    'method' => empty($_GET['widget_id']) ? 'get_all' : 'get_single',
                    'widgets' => $widgets
                ]);
            } else {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'No widgets found Click on the button above to create a new widget.'
                ]);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Internal Server Error']);
            return;
        }
            
    }

    public function createWidget()
    {
        try {
            $user = $this->verifyToken();
            if (!$user) {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                return;
            }
            if (empty($user['sub'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'User ID is invalid or missing']);
                return;
            }

            $userId = decryptCipherID($user['sub']);

            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['widget_data']) || empty($data['widget_data']['widgetTitle'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Widget data and title are required']);
                return;
            }

            $widgetData = $data['widget_data'];
            $widgetTitle = $widgetData['widgetTitle'];
            unset($widgetData['widgetTitle']);

            if ($this->widgetModel->createWidget($userId, $widgetData, $widgetTitle)) {
                http_response_code(201);
                echo json_encode(['success' => true, 'message' => 'Widget created successfully']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to create widget']);
            }

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Internal Server Error']);
        }
    }

    public function updateWidget() {
        try {
            $user = $this->verifyToken();
            if (!$user) {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                return;
            }
            if (empty($user['sub'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'User ID is invalid or missing']);
                return;
            }
            $userId = decryptCipherID($user['sub']);
            $data = json_decode(file_get_contents('php://input'), true);
            if (empty($data['widget_id']) || empty($data['widget_data'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Widget ID and data are required']);
                return;
            }
            $widgetId = decryptCipherID($data['widget_id']);
            $widgetData = $data['widget_data'];
            if ($this->widgetModel->updateWidget($userId, $widgetId, $widgetData)) {
                http_response_code(200);
                echo json_encode(['success' => true, 'message' => 'Widget updated successfully']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to update widget']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Internal Server Error']);
        }
    }

    public function deleteWidget() {
        try {
            $user = $this->verifyToken();
            if (!$user) {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                return;
            }
            if (empty($user['sub'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'User ID is invalid or missing']);
                return;
            }
            $userId = decryptCipherID($user['sub']);
            $data = json_decode(file_get_contents('php://input'), true);
            if (empty($data['widget_id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Widget ID is required']);
                return;
            }
            $widgetId = decryptCipherID($data['widget_id']);
            if ($this->widgetModel->deleteWidget($userId, $widgetId)) {
                http_response_code(200);
                echo json_encode(['success' => true, 'message' => 'Widget deleted successfully', 'method' => 'delete']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to delete widget']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Internal Server Error']);
        }
    }
}
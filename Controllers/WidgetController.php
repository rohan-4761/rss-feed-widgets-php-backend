<?php

require_once './Models/Widget.php';
require_once './Controllers/BaseController.php';
require_once './Utils/cipherID.php';

class WidgetController extends BaseController
{
    private $db;
    private $widgetModel;

    public function __construct($db)
    {
        $this->db = $db;
        $this->widgetModel = new Widget($db);
    }

    public function getWidgetsByUser()
    {
        $widgets = null;
        try {
            $user = $this->verifyToken();
            if (!$user) {
                http_response_code(401);
                echo json_encode(['success' => false, 'errorMessage' => 'Unauthorized']);
                return;
            }
            if (empty($user['sub'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'errorMessage' => 'User ID is invalid or missing']);
                return;
            }
            $userId = decryptCipherID($user['sub']);
            $widgets = $this->widgetModel->selectWidgetsByUserId($userId);
            if ($widgets) {
                foreach ($widgets as &$widget) {
                    $widget['id'] = generateCipherID($widget['id']);
                    unset($widget['userId']);
                }
                header('Content-Type: application/json');
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'Widgets retrieved successfully',
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
            echo json_encode(['success' => false, 'errorMessage' => $e->getMessage()]);
            return;
        }
    }

    public function getWidgetById()
    {
        try {
            $widgets = null;
            $userId = null;
            $skipVerification = false;
            if (isset($_SERVER['HTTP_X_EMBED_REQUEST']) && $_SERVER['HTTP_X_EMBED_REQUEST'] === 'true') {
                $skipVerification = true;
            }
            if (!$skipVerification) {
                $user = $this->verifyToken();
                if (!$user) {
                    http_response_code(401);
                    echo json_encode(['success' => false, 'errorMessage' => 'Unauthorized']);
                    return;
                }
                if (empty($user['sub'])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'errorMessage' => 'User ID is invalid or missing']);
                    return;
                }
                $userId = decryptCipherID($user['sub']);
            } else {
                if (empty($_GET['widget_id'])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'errorMessage' => 'Widget ID is invalid or missing']);
                    return;
                }
            }

            $encryptedWidgetId = htmlspecialchars($_GET['widget_id']);
            $widgetId = decryptCipherID($encryptedWidgetId);
            if (!$widgetId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'errorMessage' => 'Invalid widget ID']);
                return;
            }
            if (!is_null($userId)) {
                $widgetBelongsToUser = false;
                $userWidgets = $this->widgetModel->selectWidgetsByUserId($userId);
                foreach ($userWidgets as $userWidget) {
                    if ($userWidget['id'] == $widgetId) {
                        $widgetBelongsToUser = true;
                    }
                }
                if (!$widgetBelongsToUser) {
                    http_response_code(403);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Requested Widget is not accessible.'
                    ]);
                }
            }
            $widgets = $this->widgetModel->selectWidgetsById($widgetId);
            if ($widgets) {
                $widgets['id'] = generateCipherID($widgets['id']);
                unset($widget['user_id']);
                header('Content-Type: application/json');
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'Widgets retrieved successfully',
                    'widget' => $widgets
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
            echo json_encode(['success' => false, 'errorMessage' =>  $e->getMessage()]);
            return;
        }
    }

    public function createWidget()
    {
        try {
            $user = $this->verifyToken();
            if (!$user) {
                http_response_code(401);
                echo json_encode(['success' => false, 'errorMessage' => 'Unauthorized']);
                return;
            }
            if (empty($user['sub'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'errorMessage' => 'User ID is invalid or missing']);
                return;
            }

            $userId = decryptCipherID($user['sub']);

            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['widget_data'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'errorMessage' => 'Widget data is required']);
                return;
            }

            $widgetData = [
                "userId" => $userId,
                'widgetTitle' => $data['widget_data']['widgetTitle'],
                'feedURL' => $data['widget_data']['feedURL'],
                'topic' => $data['widget_data']['topic'] ?? null,
                'rssFeed' => $data['widget_data']['rssFeed'] ?? null,
                'widgetLayout' => $data['widget_data']['widgetLayout'],
                'general' => json_encode($data['widget_data']['general']),
                'feedTitle' => json_encode($data['widget_data']['feedTitle']),
                'feedContent' => json_encode($data['widget_data']['feedContent'])
            ];

            if ($this->widgetModel->create($widgetData)) {
                http_response_code(201);
                echo json_encode(['success' => true, 'message' => 'Widget created successfully']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'errorMessage' => 'Failed to create widget']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'errorMessage' =>  $e->getMessage()]);
        }
    }

    public function updateWidget()
    {
        try {
            $user = $this->verifyToken();
            if (!$user) {
                http_response_code(401);
                echo json_encode(['success' => false, 'errorMessage' => 'Unauthorized']);
                return;
            }
            if (empty($user['sub'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'errorMessage' => 'User ID is invalid or missing']);
                return;
            }
            $userId = decryptCipherID($user['sub']);
            $data = json_decode(file_get_contents('php://input'), true);
            if (empty($data['widget_id']) || empty($data['updated_data']) || empty($data['updated_fields'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'errorMessage' => 'Widget ID and data are required']);
                return;
            }
            $widgetId = decryptCipherID($data['widget_id']);
            $updatedData = $data['updated_data'];
            $updatedFields = $data['updated_fields'];
            if ($this->widgetModel->update($userId, $widgetId, $updatedFields, $updatedData)) {
                http_response_code(200);
                echo json_encode(['success' => true, 'widgetId' => $widgetId, 'userId'=>$userId,'message' => 'Widget updated successfully']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'errorMessage' => 'Failed to update widget']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, "errorMessage"=>$e->getMessage()]);
        }
    }

    public function deleteWidget()
    {
        try {
            $user = $this->verifyToken();
            if (!$user) {
                http_response_code(401);
                echo json_encode(['success' => false, 'errorMessage' => 'Unauthorized']);
                return;
            }
            if (empty($user['sub'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'errorMessage' => 'User ID is invalid or missing']);
                return;
            }
            $userId = decryptCipherID($user['sub']);
            $data = json_decode(file_get_contents('php://input'), true);
            if (empty($data['widget_id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'errorMessage' => 'Widget ID is required']);
                return;
            }
            $widgetId = decryptCipherID($data['widget_id']);
            if ($this->widgetModel->deleteWidget($userId, $widgetId)) {
                http_response_code(200);
                echo json_encode(['success' => true, 'message' => 'Widget deleted successfully', 'method' => 'delete']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'errorMessage' => 'Failed to delete widget']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'errorMessage' => $e->getMessage()]);
        }
    }
}

<?php

require_once './Config/redis.php';
require_once './Models/Widget.php';
require_once './Controllers/BaseController.php';
require_once './Utils/cipherID.php';

class WidgetController extends BaseController
{
    private $db;
    private $widgetModel;
    private $redis;
    private $cacheExpiry = 3600; // 1 hour

    public function __construct($db)
    {
        $this->db = $db;
        $this->widgetModel = new Widget($db);
        $this->redis = Redis::getClient();
    }

    private function getUserWidgetsKey($userId)
    {
        return "user_widgets:{$userId}";
    }

    private function getWidgetKey($widgetId)
    {
        return "widget:{$widgetId}";
    }

    private function getWidgetMetaKey($widgetId)
    {
        return "widget_meta:{$widgetId}";
    }

    private function clearUserWidgetCaches($userId, $widgetId = null)
    {
        try {
            $this->redis->del($this->getUserWidgetsKey($userId));

            if ($widgetId) {
                $this->redis->del($this->getWidgetKey($widgetId));
                $this->redis->del($this->getWidgetMetaKey($widgetId));
            }
        } catch (Exception $e) {
            error_log("Cache clear failed: " . $e->getMessage());
        }
    }

    private function cacheWidgetMeta($widget)
    {
        try {
            $metaData = [
                'id' => generateCipherID($widget['id']),
                'widgetTitle' => $widget['widgetTitle'],
                'feedURL' => $widget['feedURL'],
                'rssFeed' => $widget['rssFeed']
            ];
            $this->redis->setex(
                $this->getWidgetMetaKey($widget['id']),
                $this->cacheExpiry,
                json_encode($metaData)
            );
            return $metaData;
        } catch (Exception $e) {
            error_log("Failed to cache widget meta: " . $e->getMessage());
            return [
                'id' => generateCipherID($widget['id']),
                'widgetTitle' => $widget['widgetTitle'],
                'feedURL' => $widget['feedURL']
            ];
        }
    }

    private function cacheFullWidget($widget)
    {
        try {
            $widget['id'] = generateCipherID($widget['id']);
            unset($widget['userId']);

            $this->redis->setex(
                $this->getWidgetKey(decryptCipherID($widget['id'])),
                $this->cacheExpiry,
                json_encode($widget)
            );
            return $widget;
        } catch (Exception $e) {
            error_log("Failed to cache full widget: " . $e->getMessage());
            return $widget;
        }
    }

    public function getWidgetsByUser()
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
            $userWidgetsKey = $this->getUserWidgetsKey($userId);
            $widgets = [];

            // Try to get widget IDs from cache
            try {
                if ($this->redis->exists($userWidgetsKey)) {
                    $widgetIds = $this->redis->smembers($userWidgetsKey);

                    // Get metadata for each widget
                    foreach ($widgetIds as $widgetId) {
                        $metaKey = $this->getWidgetMetaKey($widgetId);
                        $cachedMeta = $this->redis->get($metaKey);

                        if ($cachedMeta) {
                            $widgets[] = json_decode($cachedMeta, true);
                        } else {
                            // Meta not in cache, fetch from DB and cache it
                            $dbWidget = $this->widgetModel->selectWidgetsById($widgetId);
                            if ($dbWidget) {
                                $widgets[] = $this->cacheWidgetMeta($dbWidget);
                            }
                        }
                    }
                } else {
                    // Cache miss - fetch from database
                    $dbWidgets = $this->widgetModel->selectWidgetsByUserId($userId);

                    if ($dbWidgets) {
                        foreach ($dbWidgets as $widget) {
                            // Add widget ID to user's set
                            $this->redis->sadd($userWidgetsKey, $widget['id']);

                            // Cache metadata
                            $widgets[] = $this->cacheWidgetMeta($widget);
                        }

                        // Set expiry for user's widget set
                        $this->redis->expire($userWidgetsKey, $this->cacheExpiry);
                    }
                }
            } catch (Exception $e) {
                // Redis error - fallback to database
                error_log("Redis error in getWidgetsByUser: " . $e->getMessage());
                $dbWidgets = $this->widgetModel->selectWidgetsByUserId($userId);

                if ($dbWidgets) {
                    foreach ($dbWidgets as $widget) {
                        $widget['id'] = generateCipherID($widget['id']);
                        unset($widget['userId']);
                        $widgets[] = [
                            'id' => $widget['id'],
                            'widgetTitle' => $widget['widgetTitle'],
                            'feedURL' => $widget['feedURL']
                        ];
                    }
                }
            }

            if ($widgets) {
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
        }
    }

    public function getWidgetById()
    {
        try {
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
            }

            if (empty($_GET['widget_id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'errorMessage' => 'Widget ID is invalid or missing']);
                return;
            }

            $encryptedWidgetId = htmlspecialchars($_GET['widget_id']);
            $widgetId = decryptCipherID($encryptedWidgetId);

            if (!$widgetId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'errorMessage' => 'Invalid widget ID']);
                return;
            }

            // Check ownership if user is authenticated
            if (!is_null($userId)) {
                $widgetBelongsToUser = false;

                try {
                    $userWidgetsKey = $this->getUserWidgetsKey($userId);
                    if ($this->redis->exists($userWidgetsKey)) {
                        $widgetBelongsToUser = $this->redis->sismember($userWidgetsKey, $widgetId);
                    } else {
                        // Fallback to database
                        $userWidgets = $this->widgetModel->selectWidgetsByUserId($userId);
                        foreach ($userWidgets as $userWidget) {
                            if ($userWidget['id'] == $widgetId) {
                                $widgetBelongsToUser = true;
                                break;
                            }
                        }
                    }
                } catch (Exception $e) {
                    // Cache error - check database
                    $userWidgets = $this->widgetModel->selectWidgetsByUserId($userId);
                    foreach ($userWidgets as $userWidget) {
                        if ($userWidget['id'] == $widgetId) {
                            $widgetBelongsToUser = true;
                            break;
                        }
                    }
                }

                if (!$widgetBelongsToUser) {
                    http_response_code(403);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Requested Widget is not accessible.'
                    ]);
                    return;
                }
            }

            $widget = null;

            // Try to get from cache first
            try {
                $widgetKey = $this->getWidgetKey($widgetId);
                $cachedWidget = $this->redis->get($widgetKey);

                if ($cachedWidget) {
                    $widget = json_decode($cachedWidget, true);
                } else {
                    // Cache miss - fetch from database
                    $dbWidget = $this->widgetModel->selectWidgetsById($widgetId);
                    if ($dbWidget) {
                        $widget = $this->cacheFullWidget($dbWidget);
                    }
                }
            } catch (Exception $e) {
                // Redis error - fallback to database
                error_log("Redis error in getWidgetById: " . $e->getMessage());
                $dbWidget = $this->widgetModel->selectWidgetsById($widgetId);
                if ($dbWidget) {
                    $dbWidget['id'] = generateCipherID($dbWidget['id']);
                    unset($dbWidget['userId']);
                    $widget = $dbWidget;
                }
            }

            if ($widget) {
                header('Content-Type: application/json');
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'Widget retrieved successfully',
                    'widget' => $widget
                ]);
            } else {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Widget not found.'
                ]);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'errorMessage' => $e->getMessage()]);
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
                $this->clearUserWidgetCaches($userId);

                try {
                    $dbWidgets = $this->widgetModel->selectWidgetsByUserId($userId);
                    $userWidgetsKey = $this->getUserWidgetsKey($userId);

                    if ($dbWidgets) {
                        foreach ($dbWidgets as $widget) {
                            $this->redis->sadd($userWidgetsKey, $widget['id']);
                            $widgets[] = $this->cacheWidgetMeta($widget);
                        }
                        $this->redis->expire($userWidgetsKey, $this->cacheExpiry);
                    }
                } catch (Exception $e) {
                    error_log("Failed to cache new widget: " . $e->getMessage());
                }

                http_response_code(201);
                echo json_encode(['success' => true, 'message' => 'Widget created successfully']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'errorMessage' => 'Failed to create widget']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'errorMessage' => $e->getMessage()]);
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
                $this->clearUserWidgetCaches($userId, $widgetId);

                // Optionally pre-cache the updated widget
                try {
                    $updatedWidget = $this->widgetModel->selectWidgetsById($widgetId);
                    if ($updatedWidget) {
                        $this->cacheFullWidget($updatedWidget);
                        $this->cacheWidgetMeta($updatedWidget);
                    }
                } catch (Exception $e) {
                    error_log("Failed to cache updated widget: " . $e->getMessage());
                }

                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'widgetId' => $widgetId,
                    'userId' => $userId,
                    'message' => 'Widget updated successfully'
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'errorMessage' => 'Failed to update widget']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, "errorMessage" => $e->getMessage()]);
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

                $this->clearUserWidgetCaches($userId, $widgetId);

                try {
                    $userWidgetsKey = $this->getUserWidgetsKey($userId);
                    $this->redis->srem($userWidgetsKey, $widgetId);
                } catch (Exception $e) {
                    error_log("Failed to remove widget from user set: " . $e->getMessage());
                }

                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'Widget deleted successfully',
                    'method' => 'delete'
                ]);
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

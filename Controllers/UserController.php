<?php

require_once './Models/User.php';
require_once './Controllers/BaseController.php';
require_once './Utils/cipherID.php';

class UserController extends BaseController
{
    private $db;
    private $userModel;

    public function __construct($db)
    {
        $this->db = $db;
        $this->userModel = new User($db);
    }

    public function getUser()
    {
        try {
            $data = json_decode(file_get_contents("php://input"), true);
            if (empty($data['email']) || empty($data['password'])) {
                echo json_encode([
                    'success' => false,
                    'errorMessage' => 'Email and password are required'
                ]);
                return;
            }
            $email = $data['email'];
            $password = $data['password'];
            $user = $this->userModel->getUser($email);

            if (!$user) {
                echo json_encode([
                    "success" => false,
                    'errorMessage' => 'User not found.'
                ]);
                return;
            }

            if (password_verify($password, $user['password'])) {
                $user['id'] = generateCipherID($user['id']);
                unset($user['password']);
                $token = $this->generateToken($user);
                header("Access-Control-Expose-Headers: Authorization");
                header("Authorization: Bearer $token");
                header('Content-Type: application/json');
                http_response_code(200);
                echo json_encode([
                    "success" => true,
                    "message" => "Login successful",
                    "user" => $user,
                ]);
            } else {
                header('Content-Type: application/json');
                http_response_code(401);
                echo json_encode([
                    "success" => false,
                    "errorMessage" => "Invalid Credentials"
                ]);
            }
        } catch (Exception $e) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "errorMessage" => $e->getMessage()
            ]);
        }
    }


    public function createUser()
    {
        try {
            $data = json_decode(file_get_contents("php://input"), true);
            if (!empty($data['name']) && !empty($data['email']) && !empty($data['password'])) {
                if ($this->userModel->getUser($data['email'])) {
                    echo json_encode([
                        "success" => false,
                        "errorMessage" => "User email already exists."
                    ]);
                } else if ($this->userModel->createUser($data['name'], $data['email'], $data['password'])) {
                    $user = $this->userModel->getUser($data['email']);
                    $user['id'] = generateCipherID($user['id']);
                    unset($user['password']);
                    $token = $this->generateToken($user);
                    header("Access-Control-Expose-Headers: Authorization");
                    header("Authorization: Bearer $token");
                    header('Content-Type: application/json');
                    http_response_code(201);
                    echo json_encode([
                        "success" => true,
                        "message" => "User created successfully.",
                        "user" => $user,
                    ]);
                } else {
                    header('Content-Type: application/json');
                    http_response_code(500);
                    echo json_encode([
                        "success" => false,
                        "errorMessage" => "User creation failed."
                    ]);
                }
            } else {
                header('Content-Type: application/json');
                http_response_code(400);
                echo json_encode([
                    "success" => false,
                    "errorMessage" => "Invalid input."
                ]);
            }
        } catch (Exception $e) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "errorMessage" => $e->getMessage()
            ]);
        }
    }
}

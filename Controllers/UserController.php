<?php

require_once './Models/User.php';
require_once './Controllers/BaseController.php';

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
        $data = json_decode(file_get_contents("php://input"), true);
        if (empty($data['email']) || empty($data['password'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Email and password are required'
            ]);
            return;
        }
        $email = $data['email'];
        $password = $data['password'];
        $user = $this->userModel->getUser($email);

        if (!$user) {
            echo json_encode([
                "success" => false,
                'message' => 'User not found.'
            ]);
            return;
        }

        if (password_verify($password, $user['password'])) {
            $user['id'] = password_hash($user['id'], PASSWORD_BCRYPT);
            unset($user['password']);
            $token = $this->generateToken($user);
            setcookie('token', $token, [
                'expires' => time() + 3600,
                'path' => '/',
                'secure' => false,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            error_log("Cookie set: $token");
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
                "message" => "Invalid Credentials"
            ]);
        }
    }


    public function createUser()
    {
        $data = json_decode(file_get_contents("php://input"), true);
        if (!empty($data['name']) && !empty($data['email']) && !empty($data['password'])) {
            if ($this->userModel->getUser($data['email'])) {
                echo json_encode([
                    "success" => false,
                    "message" => "User email already exists."
                ]);
            } else if ($this->userModel->createUser($data['name'], $data['email'], $data['password'])) {
                $user = $this->userModel->getUser($data['email']);
                $user['id'] = password_hash($user['id'], PASSWORD_BCRYPT);
                unset($user['password']);
                $token = $this->generateToken($user);
                setcookie('token', $token, [
                    'expires' => time() + 3600,
                    'path' => '/',
                    'secure' => false,
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);
                error_log("Cookie set: $token");
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
                    "message" => "User creation failed."
                ]);
            }
        } else {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(["message" => "Invalid input."]);
        }
    }
}

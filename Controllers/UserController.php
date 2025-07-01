<?php

require_once './Models/User.php';

class UserController {
    private $db;
    private $userModel;

    public function __construct($db) {
        $this->db = $db;
        $this->userModel = new User($db);
    }
    
    public function getUser() {
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
        
        if (password_verify($password, $user['password'])){
            unset($user['password']);
            echo json_encode([
                "success" => true,
                "message" => "Login successful",
                "user" => $user
            ]);
        } else {
            echo json_encode([
                "success" => false,
                "message" => "Invalid Credentials"
            ]);
        }
    }
    
    
    public function createUser() {
        $data = json_decode(file_get_contents("php://input"), true);
        if (!empty($data['name']) && !empty($data['email']) && !empty($data['password'])) {
            if ($this->userModel->getUser($data['email'])){
                echo json_encode([
                    "success" => false,
                    "message" => "User email already exists."
                ]);
            } else if ($this->userModel->createUser($data['name'], $data['email'], $data['password'])) {
                echo json_encode([
                    "success" => true,
                    "message" => "User created successfully."
                ]);
            } else {
                echo json_encode([
                    "success" => false,
                    "message" => "User creation failed."
                ]);
            }
        } else {
            echo json_encode(["message" => "Invalid input."]);
        }
    }
}
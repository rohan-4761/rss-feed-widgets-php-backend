<?php
require_once './vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class BaseController
{
    protected function verifyToken()
    {
        $headers = function_exists('apache_request_headers')
            ? apache_request_headers()
            : [];

        $authHeader = $headers['Authorization'] ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? null);

        if (!$authHeader) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Authorization header not found']);
            exit;
        }

        // Extract token from "Bearer <token>"
        if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            $token = $matches[1];
        } else {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Malformed Authorization header']);
            exit;
        }

        try {
            $decoded = JWT::decode($token, new Key($_ENV['JWT_SECRET_KEY'], $_ENV['JWT_ALGORITHM']));
            return (array)$decoded;
        } catch (\Firebase\JWT\ExpiredException $e) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Token has expired']);
            exit;
        } catch (\Firebase\JWT\SignatureInvalidException $e) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Invalid token signature']);
            exit;
        } catch (\UnexpectedValueException $e) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Invalid token']);
            exit;
        } catch (\Exception $e) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Token verification failed']);
            exit;
        }
    }

    protected function generateToken($user)
    {
        $payload = [
            'iss' => $_ENV['JWT_ISSUER'], // Issuer
            'aud' => $_ENV['JWT_AUDIENCE'], // Audience
            'iat' => time(), // Issued at
            'exp' => time() + (60 * 60), // Expiration time (1 hour)
            'sub' => $user['id'], // Subject (user ID)
            'email' => $user['user_email'],
            'name' => $user['user_name']
        ];

        return JWT::encode($payload, $_ENV['JWT_SECRET_KEY'], $_ENV['JWT_ALGORITHM']);
    }
}

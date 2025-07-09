<?php

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

/**
 * Encode in URL-safe Base64
 */
function base64url_encode($data)
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/**
 * Decode URL-safe Base64
 */
function base64url_decode($data)
{
    $remainder = strlen($data) % 4;
    if ($remainder) {
        $padlen = 4 - $remainder;
        $data .= str_repeat('=', $padlen);
    }
    return base64_decode(strtr($data, '-_', '+/'));
}

function generateCipherID($plainID)
{
    $keyHex = $_ENV['USER_ID_SECRET_KEY'];
    $key = hex2bin($keyHex);
    $algorithm = $_ENV['USER_ID_ALGORITHM'];
    $ivLength = openssl_cipher_iv_length($algorithm);
    $iv = random_bytes($ivLength);

    $cipherText = openssl_encrypt(
        $plainID,
        $algorithm,
        $key,
        OPENSSL_RAW_DATA,
        $iv
    );

    $output = $iv . $cipherText;

    return base64url_encode($output);
}

function decryptCipherID($cipherID)
{
    $keyHex = $_ENV['USER_ID_SECRET_KEY'];
    $key = hex2bin($keyHex);
    $algorithm = $_ENV['USER_ID_ALGORITHM'];

    $binaryData = base64url_decode($cipherID);
    $ivLength = openssl_cipher_iv_length($algorithm);

    $iv = substr($binaryData, 0, $ivLength);
    $cipherText = substr($binaryData, $ivLength);

    $plainID = openssl_decrypt(
        $cipherText,
        $algorithm,
        $key,
        OPENSSL_RAW_DATA,
        $iv
    );

    return $plainID;
}

// Example usage
// $cipherID = generateCipherID('bc4ef-124-cfd09');
// echo "Cipher ID (URL-safe): $cipherID\n";

// $plainID = decryptCipherID($cipherID);
// echo "Decrypted ID: $plainID\n";

<?php

function generateBase64Secret($length = 32) {
    // Generate secure random bytes
    $randomBytes = random_bytes($length);

    // Convert to Base64
    return base64_encode($randomBytes);
}

// Example usage
$jwtSecret = generateBase64Secret();
echo "Generated JWT Secret Key: " . $jwtSecret . "\n";


$key = bin2hex(random_bytes(16));
echo "\nGenerated AES-128 Key: " . $key . "\n";

$iv = random_bytes(openssl_cipher_iv_length('aes-128-cbc'));
echo "Generated AES-128 IV: " . bin2hex($iv) . "\n";
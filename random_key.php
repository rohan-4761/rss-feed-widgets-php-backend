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

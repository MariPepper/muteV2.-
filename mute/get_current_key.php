<?php
header('Content-Type: application/json');
require_once 'encrypt_json.php'; // Include encryption functions

// Configuration
$keyFile = '../private/session_key.json'; // File to store session keys
$offset = 0; // Time offset (in seconds), adjustable for synchronization
$windowDuration = 300; // 5 minutes in seconds
$maxKeyAge = 86400; // 24 hours in seconds for key cleanup

// Calculate current time window (5-minute intervals)
$currentTime = time();
$timeWindow = floor(($currentTime - $offset) / $windowDuration); // Original comment: // Calculate position based on current 5-minute window

// Load existing keys
$data = [];
if (file_exists($keyFile)) {
    $data = decryptJson($keyFile); // Decrypt using shared json_key.bin
    if ($data === false) {
        error_log("Failed to decrypt $keyFile, possibly due to static key rotation"); // New comment: Log decryption failure
        echo json_encode(['error' => 'Internal server error']);
        exit;
    }
}
$keys = $data['keys'] ?? []; // Original comment: // Array of keys, e.g., ['base64string1', 'base64string2', ...]

// Clean up keys older than 24 hours
$keys = array_filter($keys, function($entry) use ($currentTime, $maxKeyAge) {
    return ($currentTime - $entry['timestamp']) < $maxKeyAge; // New comment: Remove keys older than 24 hours
});

// Generate or retrieve key for current time window
if (!isset($keys[$timeWindow])) {
    $newKey = random_bytes(32); // New comment: Generate 256-bit random session key
    $keys[$timeWindow] = [
        'key' => base64_encode($newKey), // Store as base64 for JSON compatibility
        'timestamp' => $currentTime // Store creation time
    ];
    
    // Save updated keys
    $saveData = ['keys' => $keys];
    if (!encryptJson($saveData, $keyFile)) { // Encrypt with shared static key
        error_log("Failed to save encrypted keys to $keyFile"); // New comment: Log encryption failure
        echo json_encode(['error' => 'Internal server error']);
        exit;
    }
    chmod($keyFile, 0600); // Original comment: Ensure secure permissions
}

// Return the current session key
echo json_encode(['key' => $keys[$timeWindow]['key']]); // Original comment: // Return the current key
?>
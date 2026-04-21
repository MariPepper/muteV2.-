<?php
// === SECURITY: Prevent direct browser access ===
if (!defined('IN_APP') && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.0 403 Forbidden');
    exit('Direct access not allowed');
}
// ===============================================
header('Content-Type: application/json');
require_once 'encrypt_json.php';

// Simple logging function
function log_action($message) {
    $logFile = '../private/key_log.txt';
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - $message\n", FILE_APPEND | LOCK_EX);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['key_hash'])) {
    $keyHash = $_POST['key_hash'];
    $saltFile = '../private/salt_key_mapping.json';
    $currentTime = time();

    try {
        // Load and clean key mappings
        $data = decryptJson($saltFile);
        // Remove keys older than 1 day (86400 seconds)
        foreach ($data as $hash => $entry) {
            if (isset($entry['created']) && ($currentTime - $entry['created']) > 86400) {
                unset($data[$hash]);
                log_action("Expired key removed for keyHash: $hash");
            }
        }

        // If no key exists for this hash, generate a new salt and derive a key
        if (!isset($data[$keyHash])) {
            $salt = random_bytes(16); // 128-bit salt
            // Derive 256-bit key using PBKDF2 with SHA-512
            $highEntropyKey = hash_pbkdf2(
                'sha512', // Use SHA-512 for quantum resistance
                $keyHash, // Use key hash as password input
                $salt,
                1000000, // High iteration count
                32, // 256-bit key
                true // Raw binary output
            );
            $data[$keyHash] = [
                'salt' => base64_encode($salt),
                'key' => base64_encode($highEntropyKey),
                'created' => $currentTime
            ];
            if (!encryptJson($data, $saltFile)) {
                log_action("Failed to save encrypted key mappings to $saltFile");
                echo json_encode(['error' => 'Failed to save key mappings']);
                exit;
            }
            log_action("Generated high-entropy key for keyHash: $keyHash");
        }

        // Return the high-entropy key
        echo json_encode(['key' => $data[$keyHash]['key']]);
    } catch (Exception $e) {
        error_log("Error in get_salt.php: " . $e->getMessage());
        echo json_encode(['error' => 'Failed to retrieve or generate key']);
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
}
?>

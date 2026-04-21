<?php
// === SECURITY: Prevent direct browser access ===
if (!defined('IN_APP') && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.0 403 Forbidden');
    exit('Direct access not allowed');
}
// ===============================================
require_once 'encrypt_json.php'; // Include encryption functions

// Centralized configuration array (avoids globals)
$config = [
    'sessionKeyFile'     => '../private/session_key.json',     // Talk Silver session keys
    'saltKeyFile'        => '../private/salt_key_mapping.json', // Talk Gold key mappings
    'staticKeyFile'      => '../private/json_key.bin',          // Shared static key file
    'rotationInterval'   => 86400,                              // 24 hours in seconds
    'checkInterval'      => 3600,                               // 1 hour in seconds for rotation check
    'logFile'            => '../private/rotation_log.txt',
    'lastRotationFile'   => '../private/last_rotation.txt',
    'lastCheckFile'      => '../private/last_rotation_check.txt',
];

// Log function (receives log path as parameter)
function log_action($message, $logFile) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - $message\n", FILE_APPEND | LOCK_EX);
}

// Main rotation function (can be called directly)
function performKeyRotation($config) {
    $currentTime = time();

    // Check if rotation check is needed
    $lastCheck = file_exists($config['lastCheckFile']) ? (int)file_get_contents($config['lastCheckFile']) : 0;

    if ($currentTime - $lastCheck >= $config['checkInterval']) {
        // Update last check timestamp
        file_put_contents($config['lastCheckFile'], $currentTime, LOCK_EX);
        
        // Check if rotation is needed
        $lastRotation = file_exists($config['lastRotationFile']) ? (int)file_get_contents($config['lastRotationFile']) : 0;
        
        if ($currentTime - $lastRotation >= $config['rotationInterval']) {
            try {
                // Load and decrypt existing files
                $sessionData = decryptJson($config['sessionKeyFile']);
                if ($sessionData === false) {
                    log_action("Failed to decrypt {$config['sessionKeyFile']} during rotation", $config['logFile']);
                    exit;
                }
                
                $saltData = decryptJson($config['saltKeyFile']);
                if ($saltData === false) {
                    log_action("Failed to decrypt {$config['saltKeyFile']} during rotation", $config['logFile']);
                    exit;
                }
                
                // Clean Talk Silver session keys (24-hour expiration)
                $keys = $sessionData['keys'] ?? [];
                $keys = array_filter($keys, function($entry) use ($currentTime, $config) {
                    return ($currentTime - $entry['timestamp']) < $config['rotationInterval'];
                });
                
                // Clean Talk Gold key mappings (24-hour expiration)
                $saltData = array_filter($saltData, function($entry) use ($currentTime, $config) {
                    return ($currentTime - $entry['created']) < $config['rotationInterval'];
                });
                
                // Generate new 256-bit static key
                $newStaticKey = random_bytes(32);
                if (!file_put_contents($config['staticKeyFile'], $newStaticKey, LOCK_EX)) {
                    log_action("Failed to write new static key to {$config['staticKeyFile']}", $config['logFile']);
                    exit;
                }
                chmod($config['staticKeyFile'], 0600);
                
                // Re-encrypt Talk Silver session keys
                $saveData = ['keys' => array_values($keys)];
                if (!encryptJson($saveData, $config['sessionKeyFile'])) {
                    log_action("Failed to re-encrypt {$config['sessionKeyFile']} with new static key", $config['logFile']);
                    exit;
                }
                chmod($config['sessionKeyFile'], 0600);
                
                // Re-encrypt Talk Gold key mappings
                if (!encryptJson($saltData, $config['saltKeyFile'])) {
                    log_action("Failed to re-encrypt {$config['saltKeyFile']} with new static key", $config['logFile']);
                    exit;
                }
                chmod($config['saltKeyFile'], 0600);
                
                // Update rotation timestamp
                file_put_contents($config['lastRotationFile'], $currentTime, LOCK_EX);
                log_action("Rotated shared static key and re-encrypted {$config['sessionKeyFile']} and {$config['saltKeyFile']}", $config['logFile']);
            } catch (Exception $e) {
                log_action("Rotation failed: " . $e->getMessage(), $config['logFile']);
                exit;
            }
        }
    }
}

// Execute the rotation (main call)
performKeyRotation($config);
?>

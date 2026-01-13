<?php
if (!defined('ENCRYPT_JSON_INCLUDED')) {
    define('ENCRYPT_JSON_INCLUDED', true);
    
    function getStaticKey() {
        $keyFile = '../private/json_key.bin';
        $logFile = '../private/key_log.txt';
        
        if (!file_exists($keyFile)) {
            $staticKey = random_bytes(32); // Generate 256-bit static key
            if (!file_put_contents($keyFile, $staticKey, LOCK_EX)) {
                file_put_contents($logFile, date('Y-m-d H:i:s') . " - Failed to write static key to $keyFile\n", FILE_APPEND | LOCK_EX);
                throw new Exception("Failed to write static key to $keyFile");
            }
            chmod($keyFile, 0600);
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - Created static key\n", FILE_APPEND | LOCK_EX);
        }
        
        $staticKey = file_get_contents($keyFile);
        if ($staticKey === false || strlen($staticKey) !== 32) {
            unlink($keyFile);
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - Corrupted json_key.bin; deleted for regeneration\n", FILE_APPEND | LOCK_EX);
            return getStaticKey();
        }
        return $staticKey;
    }
    
    function encryptJson($data, $file) {
        $json = json_encode($data, JSON_PRETTY_PRINT);
        $iv = random_bytes(16);
        $key = getStaticKey();
        $encrypted = openssl_encrypt($json, 'AES-256-GCM', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
        if ($encrypted === false) {
            throw new Exception('Encryption failed: ' . openssl_error_string());
        }
        return file_put_contents($file, base64_encode($iv . $tag . $encrypted), LOCK_EX) !== false;
    }
    
    function decryptJson($file) {
        $data = file_exists($file) ? base64_decode(file_get_contents($file)) : false;
        if ($data === false || strlen($data) < 32) {
            return [];
        }
        $iv = substr($data, 0, 16);
        $tag = substr($data, 16, 16);
        $ciphertext = substr($data, 32);
        $key = getStaticKey();
        $decrypted = openssl_decrypt($ciphertext, 'AES-256-GCM', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($decrypted === false) {
            return [];
        }
        $decoded = json_decode($decrypted, true);
        return is_array($decoded) ? $decoded : [];
    }
}
?>

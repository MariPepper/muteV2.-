<?php
define('IN_APP', true);
// Generate a random nonce for CSP
$nonce = base64_encode(random_bytes(16));

// Add server-side security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Strict-Transport-Security: max-age=31536000;');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-$nonce'; style-src 'self';");

if ((!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') && $_SERVER['HTTP_HOST'] !== 'localhost' && $_SERVER['HTTP_HOST'] !== '127.0.0.1') {
    header("Location: https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
    exit();
}

require_once 'encrypt_json.php';
require_once 'rotate_static_key.php';

$chatFile = 'temp_talk_silver.json';

function loadMessages($file)
{
    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true);
        if ($data && isset($data['messages'])) {
            $currentTime = time();
            $messages = array_filter($data['messages'], function ($msg) use ($currentTime) {
                if (!isset($msg['timestamp'])) {
                    error_log("Message missing timestamp in loadMessages: " . json_encode($msg));
                    return false;
                }
                $timeSinceMessage = $currentTime - $msg['timestamp'];
                if ($timeSinceMessage < 300) {
                    return true;
                } else {
                    error_log("Expiring message in loadMessages: " . json_encode($msg) . " (age: $timeSinceMessage seconds)"); // Original comment
                    return false;
                }
            });
            return array_map(function ($msg) {
                return [
                    'content' => $msg['content'],
                    'timestamp' => $msg['timestamp']
                ];
            }, $messages);
        } else {
            error_log("Failed to load or parse messages from $file");
        }
    } else {
        error_log("Chat file $file does not exist");
    }
    return [];
}

function saveMessages($file, $messages, $timestamp)
{
    $messagesForStorage = array_map(function ($msg) {
        return [
            'content' => $msg['content'],
            'timestamp' => $msg['timestamp']
        ];
    }, $messages);

    $messagesForStorage = array_slice($messagesForStorage, -200);
    $data = [
        'messages' => $messagesForStorage,
        'last_activity' => $timestamp
    ];

    if (!file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX)) {
        error_log("Failed to write messages to $file");
        return false;
    }
    chmod($file, 0600);
    error_log("Saved messages: " . count($messagesForStorage) . " at " . date('Y-m-d H:i:s', $timestamp));
    return true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    if ($_POST['action'] === 'get_key') {
        // Include simplified get_current_key.php to handle key retrieval
        require_once 'get_current_key.php'; // New: Directly call simplified key generation
        exit; // get_current_key.php handles response
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['encrypted_message'])) {
    header('Content-Type: application/json');
    $encrypted_message = trim($_POST['encrypted_message']);
    error_log("Received encrypted message: '$encrypted_message'");

    if (empty($encrypted_message)) {
        error_log("Empty encrypted message received");
        echo json_encode(['success' => false, 'error' => 'Empty message']);
        exit;
    }

    try {
        $data = file_exists($chatFile) ? json_decode(file_get_contents($chatFile), true) : ['messages' => []];
        if ($data === null) {
            error_log("Failed to decode JSON from $chatFile");
            echo json_encode(['success' => false, 'error' => 'Failed to load chat data']);
            exit;
        }

        $messages = $data['messages'] ?? [];
        $currentTime = time();
        $messages[] = [
            'content' => $encrypted_message,
            'timestamp' => $currentTime
        ];

        $messages = array_filter($messages, function ($msg) use ($currentTime) {
            if (!isset($msg['timestamp'])) {
                error_log("Message missing timestamp in POST handler: " . json_encode($msg));
                return false;
            }
            $timeSinceMessage = $currentTime - $msg['timestamp'];
            if ($timeSinceMessage < 300) {
                return true;
            } else {
                error_log("Expiring message in POST handler: " . json_encode($msg) . " (age: $timeSinceMessage seconds)"); // Original comment
                return false;
            }
        });

        $messages = array_slice($messages, -200);
        if (!saveMessages($chatFile, $messages, $currentTime)) {
            error_log("Failed to save messages to $chatFile in POST handler");
            echo json_encode(['success' => false, 'error' => 'Failed to save message']);
            exit;
        }

        error_log("Message saved successfully: '$encrypted_message'");
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        error_log("Error in POST handler: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
    }
    exit;
}

$messages = loadMessages($chatFile);
?>
<!DOCTYPE html>
<html lang="en-US">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Multi-User Encrypted Timed Chat</title>
    <link rel="stylesheet" type="text/css" href="style-7.css">
</head>
<body>
    <div class="container">
        <div class="header">Multi-User Timed Encrypted Chat <sup>MUTE</sup></div>
        <div class="content">
            <div class="chat-box" id="chat-box">
                <div id="loading-indicator">Loading...</div>
            </div>
            <form class="chat-form" id="chat-form" onsubmit="return false;">
                <div class="form-group">
                    <input type="text" id="message-input" placeholder="Tap on the keyboard..." required>
                    <input type="hidden" name="encrypted_message" id="encrypted-message">
                </div>
                <div class="button-group">
                    <button type="submit" class="submit-btn">Send</button>
                    <button type="button" class="clear-btn" id="clear-btn">Clear</button>
                    <button type="button" class="open-btn" id="go-private-btn">Go Private</button>
                </div>
            </form>
            <div id="consent-box">
                <p>We use local and session storage for essential chat functionality.</p>
                <button id="accept-consent">Accept</button>
                <button id="reject-consent">Reject</button>
                <span class="consent-link">
                    <a href="cookie_policy.html">Cookie Policy</a>
                </span>
            </div>
        </div>
    </div>
    <script nonce="<?php echo $nonce; ?>">
        const initialMessages = <?php echo json_encode($messages); ?>;
    </script>
    <script src="silver.js"></script>
</body>
</html>

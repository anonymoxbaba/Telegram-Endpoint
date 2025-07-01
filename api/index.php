<?php
header('Content-Type: application/json');

// --- Diagnostic Section ---
// Temporarily dump environment variables and extension status to logs
error_log("--- Vercel PHP Function Diagnostics ---");
error_log("PHP_EXTENSIONS variable: " . (getenv('PHP_EXTENSIONS') ?: 'NOT SET'));
error_log("cURL function exists: " . (function_exists('curl_init') ? 'YES' : 'NO'));
error_log("--- End Diagnostics ---");
// --- End Diagnostic Section ---


// --- Configuration ---
$botToken = getenv('TELEGRAM_BOT_TOKEN');
$chatId = getenv('TELEGRAM_CHAT_ID');

// Function to send a message to Telegram
function sendTelegramMessage($message, $botToken, $chatId) {
    // Moved the diagnostic cURL check outside for initial debugging
    // This check is already implicitly handled by the function_exists check above

    if (empty($botToken) || empty($chatId)) {
        error_log("Telegram credentials missing. Cannot send message.");
        return false;
    }

    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
    $data = [
        'chat_id' => $chatId,
        'text' => $message,
        'parse_mode' => 'HTML'
    ];

    // The original check for cURL is here, but we added a global one for clarity
    if (!function_exists('curl_init')) {
        error_log("cURL extension is NOT available within sendTelegramMessage. This should not happen if global check passed.");
        return false;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $errno = curl_errno($ch);

    curl_close($ch);

    if ($response === false) {
        error_log("cURL Error ({$errno}): {$error}");
        return false;
    }

    $decodedResponse = json_decode($response, true);
    if (!isset($decodedResponse['ok']) || !$decodedResponse['ok']) {
        error_log("Telegram API Error: " . ($response ? $response : 'No response from Telegram API'));
        return false;
    }

    return true;
}

$response = [];

// --- Process Incoming Request ---
$requestMethod = $_SERVER['REQUEST_METHOD'];
$requestData = [];
$telegramMessage = "<b>New Incoming Request:</b>\n";
$telegramMessage .= "Method: " . $requestMethod . "\n";

if ($requestMethod === 'GET') {
    $requestData = $_GET;
    $telegramMessage .= "Type: GET\n";
    $telegramMessage .= "Query Params: " . json_encode($requestData, JSON_PRETTY_PRINT) . "\n";
} elseif ($requestMethod === 'POST') {
    $rawBody = file_get_contents('php://input');
    $requestData = json_decode($rawBody, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        parse_str($rawBody, $requestData);
        if (empty($requestData)) {
            $requestData = $rawBody;
        }
    }
    $telegramMessage .= "Type: POST\n";
    $telegramMessage .= "Body: " . json_encode($requestData, JSON_PRETTY_PRINT) . "\n";
} else {
    http_response_code(405);
    $response['error'] = 'Method not allowed.';
    $telegramMessage .= "Error: Method Not Allowed\n";
    sendTelegramMessage($telegramMessage, $botToken, $chatId);
    echo json_encode($response);
    exit;
}

// --- Get IP Address (as accurately as possible for serverless) ---
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $ipAddress = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
} elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
    $ipAddress = $_SERVER['HTTP_CLIENT_IP'];
}
$telegramMessage .= "IP Address is: " . $ipAddress . "\n";

// --- Send Data to Telegram ---
$telegramSuccess = sendTelegramMessage($telegramMessage, $botToken, $chatId);

if ($telegramSuccess) {
    $response['status'] = 'success';
    $response['message'] = 'Request processed and data sent to Telegram.';
    $response['received_data'] = $requestData;
} else {
    $response['status'] = 'error';
    $response['message'] = 'Request processed but failed to send data to Telegram.';
    $response['received_data'] = $requestData;
}

echo json_encode($response);

?>
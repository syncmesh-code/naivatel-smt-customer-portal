<?php
header("Content-Type: application/json");

// Load environment variables
$env = include("../src/.env.php");

$smartolt_api_url  = rtrim($env['SMARTOLT_API_URL'], '/') . '/';
$smartolt_api_token = $env['SMARTOLT_API_TOKEN'];

/*
|--------------------------------------------------------------------------
| Validate Request
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode([
        "status" => false,
        "message" => "Invalid request method"
    ]);
    exit;
}

$onu_serial = $_POST['barcode'] ?? '';
$ssid        = $_POST['wifi_username'] ?? '';
$password    = $_POST['wifi_password'] ?? '';

if (empty($onu_serial) || empty($ssid) || empty($password)) {
    echo json_encode([
        "status" => false,
        "message" => "Missing required parameters"
    ]);
    exit;
}

// Sanitization
$ssid = trim($ssid);
$password = trim($password);

// SSID & Password validation
if (strlen($ssid) < 3 || strlen($ssid) > 32) {
    echo json_encode([
        "status" => false,
        "message" => "WiFi name must be between 3 and 32 characters"
    ]);
    exit;
}

if (strlen($password) < 8 || strlen($password) > 32) {
    echo json_encode([
        "status" => false,
        "message" => "WiFi password must be between 8 and 32 characters"
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| SmartOLT API Call
|--------------------------------------------------------------------------
*/

$endpoint = "{$smartolt_api_url}onu/set_wifi_port_lan/{$onu_serial}";

$post_fields = [
    "wifi_port" => "wifi_0/1",   // primary WiFi
    "dhcp" => "No control",
    "ssid" => $ssid,
    "password" => $password,
    "authentication_mode" => "WPA2"
];

$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => $endpoint,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $post_fields,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_HTTPHEADER => [
        "X-Token: $smartolt_api_token"
    ]
]);

$response  = curl_exec($curl);
$error      = curl_error($curl);
$http_code  = curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

/*
|--------------------------------------------------------------------------
| Connection / HTTP Error Handling
|--------------------------------------------------------------------------
*/
if ($error) {
    echo json_encode([
        "status" => false,
        "message" => "Unable to reach SmartOLT server",
        "details" => $error
    ]);
    exit;
}

if ($http_code !== 200) {
    echo json_encode([
        "status" => false,
        "message" => "SmartOLT returned an error",
        "http_code" => $http_code
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| Parse SmartOLT Response
|--------------------------------------------------------------------------
*/

$data = json_decode($response, true);

if (isset($data['status']) && $data['status'] === true) {
    echo json_encode([
        "status" => true,
        "message" => "Main WiFi updated successfully!"
    ]);
    exit;
}

// Fallback error message
$error_message = $data['message'] ?? "Unknown SmartOLT error";

echo json_encode([
    "status" => false,
    "message" => "Failed to update WiFi: $error_message",
    "response" => $data
]);
exit;
?>

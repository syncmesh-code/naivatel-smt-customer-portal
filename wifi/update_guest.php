<?php
$env = include("../src/.env.php");
$smartolt_api_url = rtrim($env['SMARTOLT_API_URL'], '/') . '/';
$smartolt_api_token = $env['SMARTOLT_API_TOKEN'];

header('Content-Type: application/json');

// Validate Inputs
$onu_serial = $_POST['barcode'] ?? null;
$wifi_username = $_POST['wifi_username'] ?? null;
$wifi_password = $_POST['wifi_password'] ?? null;
$wifi_port = $_POST['wifi_port'] ?? "wifi_0/2"; // Guest WiFi

if (!$onu_serial || !$wifi_username || !$wifi_password) {
    echo json_encode([
        "status" => false,
        "message" => "Missing required fields"
    ]);
    exit;
}

// SmartOLT Endpoint
$url = "{$smartolt_api_url}onu/set_wifi_port_lan/{$onu_serial}";

// Payload (JSON)
$payload = [
    "wifi_port" => $wifi_port,
    "dhcp" => "No control",
    "ssid" => $wifi_username,
    "password" => $wifi_password,
    "authentication_mode" => "WPA2"
];

// Send Request
$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_HTTPHEADER => [
        "Content-Type: application/json",
        "X-Token: {$smartolt_api_token}"
    ],
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_TIMEOUT => 15
]);

$response = curl_exec($curl);
$http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

$decoded = json_decode($response, true);

// SUCCESS CASE
if ($http_code == 200 && isset($decoded['status']) && $decoded['status'] === true) {
    echo json_encode([
        "status" => true,
        "message" => "Guest WiFi updated successfully!",
        "details" => $decoded
    ]);
    exit;
}

// FAILURE CASE
echo json_encode([
    "status" => false,
    "message" => "Failed to update Guest WiFi",
    "http_code" => $http_code,
    "response" => $decoded ?: $response
]);
?>
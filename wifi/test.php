<?php

// Load environment variables
$env = include("../src/.env.php");

$smartolt_api_url = rtrim($env['SMARTOLT_API_URL'], '/') . '/';
$smartolt_api_token = $env['SMARTOLT_API_TOKEN'];

// Get the barcode and customer ID from the URL
$barcode = $_GET['barcode'] ?? '';
$customer_id = $_GET['customer_id'] ?? '';

// Function to get manufacturer name from MAC address prefix.
function getManufacturerName($macAddress) {
    static $manufacturerData = null; // Static variable to store manufacturer data
    static $cache = []; // Static cache for manufacturer lookups
    $cacheFile = __DIR__ . '/oui_cache.php'; // Cache file in the same directory as the script
    $macPrefix = strtoupper(substr(str_replace(':', '', $macAddress), 0, 6)); // First 6 chars without ':'

    // Check if the manufacturer is already in the lookup cache
    if (isset($cache[$macPrefix])) {
        return $cache[$macPrefix]; // Return cached result
    }

    // Load manufacturer data from cache file if it exists
    if ($manufacturerData === null) {
        if (file_exists($cacheFile) && is_readable($cacheFile)) {
            $manufacturerData = include($cacheFile);
        } else {
            $manufacturerData = []; // Start with an empty array if cache file doesn't exist
        }

        if (!is_array($manufacturerData)) {
            $manufacturerData = []; // Ensure manufacturerData is an array
        }
    }

    // If manufacturer data is not loaded, load it from the URL
    if (empty($manufacturerData)) {
        $url = 'https://www.wireshark.org/download/automated/data/manuf';
        $fileContent = @file_get_contents($url); // Use @ to suppress warnings

        if ($fileContent !== false) {
            $manufacturerData = [];
            $lines = explode("\n", $fileContent);
            foreach ($lines as $line) {
                if (preg_match('/^([0-9A-F]{6})\s+(.+)/', $line, $matches)) {
                    $prefix = strtoupper($matches[1]);
                    $manufacturerName = trim($matches[2]);
                    $manufacturerData[$prefix] = $manufacturerName;
                }
            }

            // Save the manufacturer data to the cache file (if writable)
            if (is_writable(__DIR__)) {
                $cacheContent = "<?php\nreturn " . var_export($manufacturerData, true) . ";\n";
                file_put_contents($cacheFile, $cacheContent);
            }

            if (!is_array($manufacturerData)) {
                error_log('Failed to parse manufacturer data from ' . $url);
                $cache[$macPrefix] = 'Manufacturer data parse error';
                return $cache[$macPrefix]; // Return cached error
            }
        } else {
            error_log('Failed to download manufacturer data from ' . $url);
            $cache[$macPrefix] = 'Failed to retrieve manufacturer data';
            return $cache[$macPrefix]; // Return cached error
        }
    }

    // Lookup manufacturer and store in cache.
    if (isset($manufacturerData[$macPrefix])) {
        $cache[$macPrefix] = $manufacturerData[$macPrefix];
        return $cache[$macPrefix];
    } else {
        $cache[$macPrefix] = 'Manufacturer not found';
        return $cache[$macPrefix];
    }
}

// API request URL
$url = "{$smartolt_api_url}onu/get_onu_full_status_info/{$barcode}";

// cURL request (same as before)
$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTPHEADER => [
        "X-Token: $smartolt_api_token",
    ],
]);
$response = curl_exec($curl);
$http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

$data = ($http_code == 200) ? json_decode($response, true) : null;

if ($data && isset($data['full_status_json'])) { // Add a check to make sure $data and the key exist

    //  ONU Details
    $router_status_raw = $data['full_status_json']['ONU details']['Run state'] ?? 'Unknown';
    $display_status = ucfirst($router_status_raw); // Online or Offline

    // Get the IP address from the first WAN interface
    $ip_address = $data['full_status_json']['ONU WAN Interfaces'][1]['IPv4 address'] ?? 'N/A';

    // Count connected devices from the "MACs on OLT from this ONU" array
    $connected_devices = count($data['full_status_json']['MACs on OLT from this ONU'] ?? []);

    // Use "Online Duration" from ONU details for uptime
    $uptime = $data['full_status_json']['ONU details']['ONT online duration'] ?? 'N/A';

    // Optical signals
    $rx_signal = $data['full_status_json']['Optical status']['Rx optical power(dBm)'] ?? 'N/A';
    $tx_signal = $data['full_status_json']['Optical status']['Tx optical power(dBm)'] ?? 'N/A';
    $olt_rx_signal = $data['full_status_json']['Optical status']['OLT Rx ONT optical power(dBm)'] ?? 'N/A';

    // Last down cause and times
    $last_down_cause = $data['full_status_json']['ONU details']['Last down cause'] ?? 'N/A';
    $last_uptime = $data['full_status_json']['ONU details']['Last up time'] ?? 'N/A';
    $last_gasp_time = $data['full_status_json']['ONU details']['Last dying gasp time'] ?? 'N/A';

    // Extract and display Online MAC Addresses
    echo "<b>Online MAC Addresses:</b><br>";
    $online_macs = $data['full_status_json']['Online MACs on this ONU'] ?? []; //Get the MAC array

    if (!empty($online_macs)) { // Check if the array is not empty
        foreach ($online_macs as $mac_info) { //Loop and print each MAC address
            $mac_address = $mac_info['MAC address'] ?? 'N/A';
            $port = $mac_info['Port'] ?? 'N/A';
             $manufacturer = getManufacturerName($mac_address); // Look up manufacturer

            echo htmlspecialchars("Port: $port, MAC Address: $mac_address, Manufacturer: $manufacturer") . "<br>";
        }
    } else {
        echo "No online MAC addresses found.<br>";
    }


} else {
    echo "Error: Could not retrieve or parse data from the API.";
}

?>

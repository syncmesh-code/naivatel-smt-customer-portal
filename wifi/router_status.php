<?php

// Load environment variables
$env = include("../src/.env.php");

$smartolt_api_url = rtrim($env['SMARTOLT_API_URL'], '/') . '/';
$smartolt_api_token = $env['SMARTOLT_API_TOKEN'];

// Sanitize GET parameters
$barcode = trim($_GET['barcode'] ?? '');
$customer_id = trim($_GET['customer_id'] ?? '');
$customer_login = trim($_GET['customer_login'] ?? '');

// SmartOLT API call
$url = "{$smartolt_api_url}onu/get_onu_full_status_info/{$barcode}";

$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_HTTPHEADER => ["X-Token: $smartolt_api_token"]
]);
$response = curl_exec($curl);
curl_close($curl);

// Decode SmartOLT response
$decoded = json_decode($response, true);
$data = ($decoded && isset($decoded['status']) && $decoded['status'] === true) ? $decoded : null;


// Default values
$display_status = "Unknown";
$uptime = "N/A";
$ip_address = "N/A";
$rx_signal = "N/A";
$tx_signal = "N/A";
$olt_rx_signal = "N/A";
$online_macs = [];
$online_mac_count = 0;
$last_down_cause = "N/A";
$last_uptime = "N/A";

// If SmartOLT data is valid
if ($data && isset($data['full_status_json'])) {

    $full = $data['full_status_json'];

    // =========================
    //  STATUS
    // =========================
    $router_status_raw =
        $full['ONU details']['Run state']
        ?? $full['ONU details']['State']
        ?? "offline";

    $display_status = ucfirst($router_status_raw);

    if (stripos($router_status_raw, "offline") !== false) {
        $display_status = "Offline";
    } elseif (stripos($router_status_raw, "online") !== false) {
        $display_status = "Online";
    }

    // =========================
    //  UPTIME
    // =========================
    $uptime = $full['ONU details']['ONT online duration'] ?? 'N/A';

    // =========================
    //  WAN IP (PPPoE interface, #2)
    // =========================
    $ip_address = $full['ONU WAN Interfaces'][2]['IPv4 address'] ?? 'N/A';

    // =========================
    //  OPTICAL SIGNALS (Correct Keys)
    // =========================
    $rx_signal = $full['Optical status']['Rx optical power(dBm)'] ?? 'N/A';
    $tx_signal = $full['Optical status']['Tx optical power(dBm)'] ?? 'N/A';
    $olt_rx_signal = $full['Optical status']['OLT Rx ONT optical power(dBm)'] ?? 'N/A';

    // =========================
    //  CONNECTED DEVICES
    // =========================
    $online_macs = $full['Online MACs on this ONU'] ?? [];
    $online_mac_count = count($online_macs);

    // =========================
    //  LAST DOWN CAUSE
    // =========================
    $last_down_cause = $full['ONU details']['Last down cause'] ?? 'N/A';
    $last_uptime = $full['ONU details']['Last up time'] ?? 'N/A';

    // Human-friendly translation
    if ($last_down_cause === "dying-gasp") {
        $last_down_cause = "Power Loss (Dying Gasp)";
    } elseif (stripos($last_down_cause, "los") !== false) {
        $last_down_cause = "Loss of Signal (Fiber Disconnected)";
    }

    // =========================
    //  SIGNAL QUALITY LOGIC
    // =========================
    function classifySignal($value)
    {
        if (!is_numeric($value)) {
            return ["Unknown", "secondary"];
        }

        if ($value > -23)
            return ["Excellent", "success"];
        if ($value > -27)
            return ["Good", "primary"];
        if ($value > -29)
            return ["Weak", "warning"];
        return ["Critical", "danger"];
    }

    [$signal_label, $signal_color] = classifySignal($rx_signal);
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Router Status</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        body {
            background: #f4f7fc;
            padding-bottom: 50px;
            font-family: "Inter", sans-serif;
        }

        .status-card {
            border-radius: 14px;
            padding: 25px;
            background: white;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08);
        }

        .info-box {
            border-radius: 14px;
            padding: 20px;
            background: white;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }
    </style>
</head>

<body>

    <div class="container py-4">

        <!-- HEADER -->
        <div class="text-center mb-4">
            <h2 class="fw-bold"><i class="bi bi-router-fill me-2"></i> Router Status</h2>
            <p class="text-muted mb-0">Account: <strong><?= htmlspecialchars($customer_login) ?></strong></p>
            <p class="text-muted">Serial: <strong><?= htmlspecialchars($barcode) ?></strong></p>
        </div>

        <!-- STATUS CARD -->
        <div class="status-card mx-auto" style="max-width: 600px;">
            <h5 class="fw-semibold mb-3">Current Status</h5>

            <p>
                <span class="badge bg-<?= ($display_status === "Online") ? 'success' : 'danger' ?> px-3 py-2 fs-6">
                    <?= $display_status ?>
                </span>
            </p>

            <p><i class="bi bi-clock me-2"></i><strong>Uptime:</strong> <?= $uptime ?></p>
            <p><i class="bi bi-wifi me-2"></i><strong>Connected Devices:</strong> <?= $online_mac_count ?></p>
            <p><i class="bi bi-globe me-2"></i><strong>WAN IP:</strong> <?= $ip_address ?></p>

            <p class="mt-3">
                <i class="bi bi-reception-4 me-2"></i>
                <strong>Optical Signal:</strong>
                <span class="badge bg-<?= $signal_color ?>"><?= $signal_label ?></span>
                <small class="text-muted ms-1">(<?= $rx_signal ?> dBm)</small>
            </p>
        </div>

        <!-- DIAGNOSTICS -->
        <h4 class="mt-4 mb-2 fw-semibold">History & Diagnostics</h4>

        <div class="info-box mx-auto" style="max-width: 600px;">
            <p><i class="bi bi-exclamation-circle me-2"></i>
                <strong>Last Down Cause:</strong> <?= $last_down_cause ?>
            </p>

            <p><i class="bi bi-arrow-repeat me-2"></i>
                <strong>Last Reconnected:</strong> <?= $last_uptime ?>
            </p>
        </div>

        <!-- CONNECTED DEVICES -->
        <h4 class="mt-4 mb-2 fw-semibold">Connected Devices</h4>
        <div class="info-box mx-auto" style="max-width: 800px;">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Port</th>
                        <th>MAC Address</th>
                        <th>VLAN</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($online_macs)): ?>
                        <?php foreach ($online_macs as $mac): ?>
                            <tr>
                                <td><?= htmlspecialchars($mac['Port']) ?></td>
                                <td><?= htmlspecialchars($mac['MAC address']) ?></td>
                                <td><?= htmlspecialchars($mac['VLAN']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" class="text-center text-muted">No active devices</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- ACTION BUTTONS -->
        <div class="text-center mt-4">
            <button onclick="location.reload()" class="btn btn-primary me-2">
                <i class="bi bi-arrow-repeat me-1"></i> Refresh
            </button>

            <a href="router_setup.php?barcode=<?= urlencode($barcode) ?>&customer_id=<?= urlencode($customer_id) ?>&customer_login=<?= urlencode($customer_login) ?>"
                class="btn btn-secondary">
                ⬅ Back
            </a>
        </div>

    </div>

</body>

</html>
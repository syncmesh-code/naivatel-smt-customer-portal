<?php
// Load environment variables
$env = include("../src/.env.php");

$smartolt_api_url = rtrim($env['SMARTOLT_API_URL'], '/') . '/';
$smartolt_api_token = $env['SMARTOLT_API_TOKEN'];

$customer_id = $_GET['customer_id'] ?? 'Unknown';
$onu_serial = $_GET['barcode'] ?? 'Unknown';
$customer_login = $_GET['customer_login'];

// SmartOLT API endpoint
$url = "{$smartolt_api_url}onu/get_onu_details/{$onu_serial}";

// Make request
$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_HTTPHEADER => ["X-Token: $smartolt_api_token"]
]);
$response = curl_exec($curl);
$http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);


// Default WiFi values
$wifi_1_ssid = "Not Available";
$wifi_1_password = "Not Available";

if ($http_code == 200 && !empty($response)) {
    $data = json_decode($response, true);

    if (!empty($data['onu_details']['wifi_ports'][0])) {
        $wifi_1_ssid = $data['onu_details']['wifi_ports'][0]['ssid'] ?? "Not Available";
        $wifi_1_password = $data['onu_details']['wifi_ports'][0]['password'] ?? "Not Available";
    }
}

$current_username = $wifi_1_ssid;
$current_password = $wifi_1_password;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WiFi Configuration</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <style>
        body {
            background: #f0f3f7;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            font-family: "Inter", sans-serif;
        }

        .wifi-container {
            max-width: 420px;
            width: 100%;
            background: white;
            padding: 25px;
            border-radius: 18px;
            box-shadow: 0px 8px 25px rgba(0, 0, 0, 0.08);
        }

        .wifi-section {
            border: 2px solid #0e165e;
            border-radius: 12px;
            padding: 20px;
        }

        .wifi-section h5 {
            text-align: center;
            font-weight: 700;
            color: #0e165e;
            margin-bottom: 15px;
        }

        .form-control {
            border-radius: 8px;
        }

        .input-group-text {
            cursor: pointer;
            background: #f0f3f7;
        }

        .btn-primary {
            background: #0e165e;
            border-radius: 8px;
        }

        .btn-back {
            margin-top: 15px;
            background: #6c757d;
            border-radius: 8px;
            padding: 10px 20px;
        }

        .spinner-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: none;
            justify-content: center;
            align-items: center;
            background: rgba(0, 0, 0, 0.4);
            z-index: 999;
        }

        .fade-in {
            animation: fadeIn 0.4s ease-in-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-5px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>

<body>

    <div class="wifi-container">

        <div id="responseMessage"></div>

        <div class="alert alert-light border text-center mb-3">
            <strong>Account Number:</strong>
            <span class="text-primary">
                <?= htmlspecialchars($customer_login) ?>
            </span>
        </div>

        <!-- PRIMARY WIFI -->
        <form id="primaryWifiForm" class="wifi-section">
            <h5>Main WiFi</h5>

            <label class="form-label">WiFi Name</label>
            <input type="text" class="form-control" name="wifi_username"
                value="<?= htmlspecialchars($current_username) ?>" required>

            <label class="form-label mt-2">WiFi Password</label>
            <div class="input-group">
                <input type="password" id="wifi1" class="form-control" name="wifi_password"
                    value="<?= htmlspecialchars($current_password) ?>" required>
                <span class="input-group-text" onclick="togglePassword('wifi1')">
                    <i class="fa fa-eye"></i>
                </span>
            </div>

            <input type="hidden" name="barcode" value="<?= $onu_serial ?>">
            <input type="hidden" name="wifi_port" value="wifi_0/1">

            <button class="btn btn-primary mt-3 w-100">Update Primary WiFi</button>
        </form>

        <div class="text-center mt-3">
            <a href="router_setup.php?barcode=<?= urlencode($onu_serial) ?>&customer_id=<?= urlencode($customer_id) ?>&customer_login=<?= urlencode($customer_login) ?>"
                class="btn btn-back">Back</a>
        </div>

    </div>

    <div class="spinner-overlay" id="spinnerOverlay">
        <div class="spinner-border text-light"></div>
    </div>

    <script>
        function togglePassword(id) {
            let f = document.getElementById(id);
            f.type = (f.type === "password") ? "text" : "password";
        }

        // Handle ONLY Primary WiFi (Guest removed)
        document.getElementById("primaryWifiForm").addEventListener("submit", function (e) {
            e.preventDefault();

            let formData = new FormData(this);
            let spinner = document.getElementById("spinnerOverlay");
            spinner.style.display = "flex";

            fetch("update_wifi.php", {
                method: "POST",
                body: formData
            })
                .then(r => r.text())
                .then(raw => {
                    spinner.style.display = "none";

                    let msgBox = document.getElementById("responseMessage");
                    let data;

                    try {
                        data = JSON.parse(raw);
                    } catch (e) {
                        // Fallback if response is not JSON
                        msgBox.innerHTML = `<div class="alert alert-danger shadow-sm fade-in">
                                Unexpected response: ${raw}
                            </div>`;
                        return;
                    }

                    let icon = data.status
                        ? "<i class='fa-solid fa-circle-check me-2'></i>"
                        : "<i class='fa-solid fa-circle-xmark me-2'></i>";

                    let alertClass = data.status ? "success" : "danger";

                    msgBox.innerHTML = `
                <div class="alert alert-${alertClass} shadow-sm fade-in">
                    ${icon} ${data.message}
                </div>
            `;

                    // Auto-hide message after 4 seconds
                    setTimeout(() => msgBox.innerHTML = "", 4000);
                })

                .catch(err => {
                    spinner.style.display = "none";
                    document.getElementById("responseMessage").innerHTML =
                        `<div class='alert alert-danger'>Error: ${err}</div>`;
                });
        });
    </script>

</body>

</html>
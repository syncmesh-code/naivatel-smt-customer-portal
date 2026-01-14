<?php
// Sanitize received values
$barcode = isset($_GET['barcode']) ? htmlspecialchars($_GET['barcode']) : 'Unknown';
$customer_id = isset($_GET['customer_id']) ? htmlspecialchars($_GET['customer_id']) : 'Unknown';
$customer_login = isset($_GET['customer_login']) ? htmlspecialchars($_GET['customer_login']) : 'Unknown';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Router Management</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

    <style>
        body {
            background: #f4f7fc;
            padding-bottom: 60px;
            font-family: "Inter", sans-serif;
        }

        .page-header {
            margin-top: 40px;
            text-align: center;
        }

        .router-info-box {
            background: white;
            border-radius: 14px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08);
        }

        .router-card {
            border-radius: 14px;
            transition: transform .25s ease;
            cursor: pointer;
        }

        .router-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.15);
        }

        .card-icon {
            font-size: 48px;
            color: #0e165e;
        }

        .card-title {
            font-weight: 600;
        }
    </style>
</head>

<body>

    <div class="container">

        <!-- Header -->
        <div class="page-header">
            <h2 class="fw-bold"><i class="bi bi-router-fill me-2"></i> Router Management</h2>
            <p class="text-muted">Manage settings for your Naivatel router</p>
        </div>

        <!-- Router Info -->
        <div class="router-info-box mx-auto" style="max-width: 600px;">
            <h5 class="fw-semibold">Router Serial:</h5>
            <p class="mb-2"><?= $barcode ?></p>

            <h6 class="fw-semibold">Account Login:</h6>
            <p><?= $customer_login ?></p>

            <!-- Back Button -->
            <a href="list_routers.php?customer_id=<?= urlencode($customer_id) ?>&customer_login=<?= urlencode($customer_login) ?>"
                class="btn btn-secondary btn-sm mt-3">
                ⬅ Back to Router List
            </a>
        </div>

        <!-- Action Cards -->
        <div class="row justify-content-center g-4">

            <!-- WiFi Settings -->
            <div class="col-md-5">
                <a href="wifi_config.php?barcode=<?= urlencode($barcode) ?>&customer_id=<?= urlencode($customer_id) ?>&customer_login=<?= urlencode($customer_login) ?>"
                    class="text-decoration-none">
                    <div class="card router-card p-4 text-center">
                        <i class="bi bi-wifi card-icon"></i>
                        <h4 class="mt-3 mb-2">Router Settings</h4>
                        <p class="text-muted">Update your WiFi name and password</p>
                    </div>
                </a>
            </div>

            <!-- Router Status -->
            <div class="col-md-5">
                <a href="router_status.php?barcode=<?= urlencode($barcode) ?>&customer_id=<?= urlencode($customer_id) ?>&customer_login=<?= urlencode($customer_login) ?>"
                    class="text-decoration-none">
                    <div class="card router-card p-4 text-center">
                        <i class="bi bi-activity card-icon"></i>
                        <h4 class="mt-3 mb-2">Router Status</h4>
                        <p class="text-muted">Check internet connection, LOS, power & uptime</p>
                    </div>
                </a>
            </div>

        </div>

    </div>

</body>

</html>
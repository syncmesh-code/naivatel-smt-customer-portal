<?php
include("../src/SplynxApi.php");

// Load environment variables
$env = include("../src/.env.php");

$api_url = $env['API_URL'];
$key = $env['API_KEY'];
$secret = $env['API_SECRET'];

// Accept POST (from Splynx portal) or GET (testing)
$customer_id = $_POST['customer_id'] ?? $_GET['customer_id'] ?? null;
$customer_login = $_POST['customer_login'] ?? $_GET['customer_login'] ?? null;

if (!$customer_id || !ctype_digit($customer_id)) {
    exit("Invalid or missing Customer ID");
}

// Init Splynx API
$api = new SplynxAPI($api_url);
$api->setVersion(SplynxApi::API_VERSION_2);

$isAuthorized = $api->login([
    'auth_type' => SplynxApi::AUTH_TYPE_API_KEY,
    'key' => $key,
    'secret' => $secret,
]);

if (!$isAuthorized) {
    exit("Authorization to Splynx failed.");
}

// Fetch devices assigned to this customer
$itemsApiUrl = "admin/inventory/items";
$condition = [
    'main_attributes' => [
        'status' => ['IN', ['assigned', 'sold']],
        'customer_id' => $customer_id,
    ]
];

$result = $api->api_call_get($itemsApiUrl . '?' . http_build_query($condition));
$items = $result ? $api->response : [];

// Convert ONU serial numbers for SmartOLT
function convertSerialNumber($serialNumber)
{
    if (strlen($serialNumber) == 16) {
        $firstEight = substr($serialNumber, 0, 8);
        $lastEight = substr($serialNumber, -8);

        if (ctype_xdigit($firstEight)) {
            $hex = hex2bin($firstEight);
            return $hex . $lastEight;
        }
    }
    return $serialNumber;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Konnect – My Routers</title>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

    <style>
        body {
            background: #f2f6fc;
            font-family: "Inter", sans-serif;
        }

        .router-card {
            border-radius: 14px;
            padding: 22px;
            background: white;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08);
            transition: 0.25s ease;
        }

        .router-card:hover {
            transform: translateY(-4px);
        }

        .router-icon {
            font-size: 46px;
            color: #0d6efd;
        }

        .btn-manage {
            background: #0d6efd;
            color: white;
            border-radius: 8px;
            padding: 9px 16px;
            font-weight: 500;
        }

        .btn-manage:hover {
            background: #0a58ca;
            color: white;
        }

        .page-title {
            font-weight: 700;
            color: #0d1b2a;
        }
    </style>
</head>

<body>

    <div class="container py-5">

        <h2 class="text-center mb-4 page-title">
            <i class="bi bi-router-fill me-2"></i> My Routers
        </h2>

        <div class="row justify-content-center mb-3">
            <div class="col-auto">
                <a href="/portal" class="btn btn-secondary btn-sm">
                    ⬅ Back to Customer Portal
                </a>
            </div>
        </div>

        <div class="row g-4">

            <?php if (!empty($items)): ?>
                <?php foreach ($items as $item): ?>

                    <?php
                    $serial = $item['barcode'];
                    $converted_serial = convertSerialNumber($serial);
                    ?>

                    <div class="col-md-4 col-lg-3">
                        <div class="router-card text-center">

                            <div class="router-icon">
                                <i class="bi bi-hdd-network"></i>
                            </div>

                            <h5 class="mt-3 mb-1"><?= htmlspecialchars($converted_serial) ?></h5>

                            <p class="text-muted small mb-3">
                                Inventory ID: <?= htmlspecialchars($item['id']) ?>
                            </p>

                            <button class="btn btn-manage w-100"
                                onclick="openItem('<?= htmlspecialchars($converted_serial) ?>', '<?= htmlspecialchars($customer_id) ?>', '<?= htmlspecialchars($customer_login) ?>')">
                                Manage Router
                            </button>

                        </div>
                    </div>

                <?php endforeach; ?>
            <?php else: ?>

                <div class="col-12 text-center text-muted">
                    <i class="bi bi-exclamation-circle fs-1"></i>
                    <p class="mt-3">No routers found for your Konnect account.</p>
                </div>

            <?php endif; ?>

        </div>

    </div>

    <script>
        function openItem(onu_serial, customerId, customerLogin) {
            window.location.href =
                'router_setup.php?barcode=' + encodeURIComponent(onu_serial) +
                '&customer_id=' + encodeURIComponent(customerId) +
                '&customer_login=' + encodeURIComponent(customerLogin);
        }
    </script>

</body>

</html>
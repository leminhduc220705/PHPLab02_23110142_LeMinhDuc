<?php
require __DIR__ . '/lib/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'POST'], true)) {
    http_response_code(405);
    exit('Method not allowed');
}

if ($method === 'POST' && !csrf_valid($_POST)) {
    http_response_code(403);
    exit('Invalid CSRF token');
}

$ref = $method === 'POST'
    ? (is_string($_POST['ref'] ?? null) ? $_POST['ref'] : '')
    : (is_string($_GET['ref'] ?? null) ? $_GET['ref'] : '');

$index = booking_index_by_ref($ref);
$booking = $index === null ? null : $_SESSION['bookings'][$index];
$item = $booking === null ? null : booking_workshop($booking, $workshops);

if ($booking === null || $item === null) {
    http_response_code(404);
}

$errors = [];
$old = [];

if ($method === 'POST' && $booking !== null && $item !== null) {
    $input = [
        'full_name'   => $_POST['full_name'] ?? '',
        'email'       => $_POST['email'] ?? '',
        'quantity'    => $_POST['quantity'] ?? null,
        'workshop_id' => $item['id'],
    ];

    $result = validate_booking($input, $workshops);
    $errors = $result['errors'];

    $old = [
        'full_name' => is_string($_POST['full_name'] ?? null) ? $_POST['full_name'] : '',
        'email'     => is_string($_POST['email'] ?? null) ? $_POST['email'] : '',
        'quantity'  => is_scalar($_POST['quantity'] ?? null) ? (string)$_POST['quantity'] : '',
    ];

    if (!$errors) {
        $d = $result['data'];
        $_SESSION['bookings'][$index]['full_name'] = $d['full_name'];
        $_SESSION['bookings'][$index]['email']     = $d['email'];
        $_SESSION['bookings'][$index]['quantity']  = $d['quantity'];
        $_SESSION['bookings'][$index]['amount']    = $item['fee'] * $d['quantity'];

        $_SESSION['flash'] = 'Booking updated successfully.';
        redirect_to('booking_detail.php?ref=' . urlencode($ref));
    }
}

if ($booking !== null && !$old) {
    $old = [
        'full_name' => $booking['full_name'],
        'email'     => $booking['email'],
        'quantity'  => (string)$booking['quantity'],
    ];
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Edit Booking</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="<?= e($theme) ?>">
<main class="wrap">
    <header>
        <h1>Edit Booking</h1>
        <nav>
            <a href="index.php">Catalog</a>
            <a href="dashboard.php">My bookings</a>
        </nav>
    </header>

    <?php if ($booking === null || $item === null): ?>
        <div class="panel">
            <h2>Booking not found</h2>
            <p class="muted">The reference is missing, unknown or no longer belongs to this session.</p>
            <a class="btn secondary-btn" href="dashboard.php">Back to My Bookings</a>
        </div>
    <?php else: ?>
        <form method="post" action="booking_edit.php" class="panel form-stack" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="ref" value="<?= e($booking['ref']) ?>">

            <label>Booking Reference
                <input value="<?= e($booking['ref']) ?>" readonly>
            </label>

            <label>Workshop
                <input value="<?= e($booking['workshop']) ?>" readonly>
            </label>

            <label>Full Name
                <input name="full_name" value="<?= e($old['full_name'] ?? '') ?>">
            </label>
            <small class="error"><?= e($errors['full_name'] ?? '') ?></small>

            <label>Email
                <input name="email" value="<?= e($old['email'] ?? '') ?>">
            </label>
            <small class="error"><?= e($errors['email'] ?? '') ?></small>

            <label>Tickets (1–4)
                <input name="quantity" type="number" min="1" max="4" step="1" value="<?= e($old['quantity'] ?? '') ?>">
            </label>
            <small class="error"><?= e($errors['quantity'] ?? '') ?></small>

            <p><strong>Current total: <?= number_format($booking['amount']) ?> VND</strong></p>
            <p class="muted">Unit fee: <?= number_format($item['fee']) ?> VND · Total will be recalculated after saving.</p>

            <div class="btn-group">
                <button type="submit">Save Changes</button>
                <a class="btn secondary-btn" href="booking_detail.php?ref=<?= urlencode($booking['ref']) ?>">Cancel</a>
            </div>
        </form>
    <?php endif; ?>
</main>
</body>
</html>
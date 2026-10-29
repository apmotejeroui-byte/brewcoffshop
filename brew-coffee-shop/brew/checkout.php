<?php
// JSON endpoint: validates the cart server-side, checks/decrements stock and saves the order in one transaction.
require __DIR__ . '/db.php';
header('Content-Type: application/json');

function fail(string $msg, int $code = 422): void {
    http_response_code($code);
    echo json_encode(['error' => $msg]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('POST only.', 405);

$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) fail('Invalid request.', 400);
if (!hash_equals($_SESSION['csrf'] ?? '', (string)($in['csrf'] ?? ''))) fail('Session expired. Refresh the page.', 400);

$name = trim((string)($in['name'] ?? ''));
$type = (string)($in['type'] ?? '');
$items = $in['items'] ?? [];

if ($name === '' || mb_strlen($name) > 60) fail('Please enter a name (max 60 characters).');
if (!in_array($type, ['Dine-in', 'Takeout'], true)) fail('Invalid order type.');
if (!is_array($items) || !$items) fail('Your cart is empty.');

// Merge duplicate lines and validate quantities
$want = [];
foreach ($items as $it) {
    $id  = (int)($it['id'] ?? 0);
    $qty = (int)($it['qty'] ?? 0);
    if ($id <= 0 || $qty < 1 || $qty > 50) fail('Invalid item quantity.');
    $want[$id] = ($want[$id] ?? 0) + $qty;
}

$pdo = db();
try {
    $pdo->exec('BEGIN IMMEDIATE');   // lock so two customers can't oversell the same stock

    $sel = $pdo->prepare('SELECT * FROM products WHERE id = ? AND active = 1');
    $upd = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ?');
    $lines = [];
    $total = 0.0;

    foreach ($want as $id => $qty) {
        $sel->execute([$id]);
        $p = $sel->fetch();
        if (!$p) throw new RuntimeException('An item in your cart is no longer available.');
        if ($p['stock'] < $qty) throw new RuntimeException("Sorry, only {$p['stock']} of {$p['name']} left.");
        $upd->execute([$qty, $id]);
        $lines[] = [$p['id'], $p['name'], $p['price'], $qty];
        $total += $p['price'] * $qty;   // price comes from the DB, never from the browser
    }

    $pdo->prepare('INSERT INTO orders (customer_name, order_type, total) VALUES (?,?,?)')
        ->execute([$name, $type, round($total, 2)]);
    $orderId = (int)$pdo->lastInsertId();

    $ins = $pdo->prepare('INSERT INTO order_items (order_id, product_id, name, price, qty) VALUES (?,?,?,?,?)');
    foreach ($lines as [$pid, $pname, $price, $qty]) $ins->execute([$orderId, $pid, $pname, $price, $qty]);

    $pdo->exec('COMMIT');
} catch (Throwable $ex) {
    if ($pdo->inTransaction()) $pdo->exec('ROLLBACK');
    $user = $ex instanceof RuntimeException;
    fail($user ? $ex->getMessage() : 'Could not save your order. Please try again.', $user ? 422 : 500);
}

$_SESSION['my_orders'][] = $orderId;
echo json_encode(['order_id' => $orderId, 'total' => round($total, 2)]);

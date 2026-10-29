<?php
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';

$id = (int)($_GET['id'] ?? 0);
// Customers can only see orders placed in their own session; staff can see all.
if (!is_admin() && !in_array($id, $_SESSION['my_orders'] ?? [], true)) {
    http_response_code(403);
    page_head('Receipt');
    echo '<div class="panel"><h2>Receipt not available</h2><p>Receipts can only be viewed by the person who placed the order, or by staff.</p><a class="btn" href="index.php">Back to menu</a></div>';
    page_foot();
    exit;
}

$st = db()->prepare('SELECT * FROM orders WHERE id = ?');
$st->execute([$id]);
$o = $st->fetch();
if (!$o) { http_response_code(404); exit('Order not found.'); }

$items = db()->prepare('SELECT * FROM order_items WHERE order_id = ?');
$items->execute([$id]);
$items = $items->fetchAll();

page_head('Receipt #' . $id);
?>
<div class="receipt">
    <h2>☕ Brew Coffee Shop</h2>
    <div class="c">Order #<?= str_pad((string)$o['id'], 4, '0', STR_PAD_LEFT) ?><br><?= e($o['created_at']) ?></div>
    <hr>
    <div>Name: <strong><?= e($o['customer_name']) ?></strong><br>Type: <?= e($o['order_type']) ?><br>
        Status: <span class="badge <?= e($o['status']) ?>"><?= e($o['status']) ?></span></div>
    <hr>
    <table>
        <?php foreach ($items as $i): ?>
            <tr><td><?= (int)$i['qty'] ?> × <?= e($i['name']) ?></td><td class="num"><?= peso($i['price'] * $i['qty']) ?></td></tr>
        <?php endforeach; ?>
    </table>
    <hr>
    <table><tr><th>TOTAL</th><th class="num"><?= peso($o['total']) ?></th></tr></table>
    <hr>
    <div class="c">Thank you! We'll call your name when it's ready.</div>
</div>
<div class="actions">
    <button class="btn" onclick="window.print()">Print receipt</button>
    <a class="btn ghost" href="index.php">New order</a>
</div>
<?php page_foot();

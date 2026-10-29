<?php
require __DIR__ . '/../db.php';
require __DIR__ . '/../layout.php';
require_admin();
$pdo = db();

$statuses = ['Pending', 'Preparing', 'Ready', 'Completed', 'Cancelled'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    $new = $_POST['status'] ?? '';

    if ($id && in_array($new, $statuses, true)) {
        $pdo->exec('BEGIN IMMEDIATE');
        $st = $pdo->prepare('SELECT status FROM orders WHERE id=?');
        $st->execute([$id]);
        $old = $st->fetchColumn();

        if ($old === false) {
            flash('Order not found.');
        } elseif ($old === 'Cancelled') {
            flash('Cancelled orders cannot be reopened (stock was already returned).');
        } else {
            if ($new === 'Cancelled') {   // put the stock back
                $pdo->prepare('UPDATE products SET stock = stock + (SELECT qty FROM order_items WHERE order_id=? AND product_id=products.id)
                               WHERE id IN (SELECT product_id FROM order_items WHERE order_id=? AND product_id IS NOT NULL)')
                    ->execute([$id, $id]);
            }
            $pdo->prepare('UPDATE orders SET status=? WHERE id=?')->execute([$new, $id]);
            flash("Order #$id marked $new.");
        }
        $pdo->exec('COMMIT');
    }
    header('Location: orders.php' . (isset($_GET['status']) ? '?status=' . urlencode($_GET['status']) : ''));
    exit;
}

$filter = $_GET['status'] ?? '';
if (in_array($filter, $statuses, true)) {
    $st = $pdo->prepare('SELECT * FROM orders WHERE status=? ORDER BY id DESC LIMIT 200');
    $st->execute([$filter]);
} else {
    $filter = '';
    $st = $pdo->query('SELECT * FROM orders ORDER BY id DESC LIMIT 200');
}
$orders = $st->fetchAll();

$itemSt = $pdo->prepare('SELECT qty, name FROM order_items WHERE order_id=?');

page_head('Orders', '../', true);
?>
<h1>Orders</h1>
<div class="tabs">
    <a href="orders.php"><button class="<?= $filter === '' ? 'active' : '' ?>">All</button></a>
    <?php foreach ($statuses as $s): ?>
        <a href="orders.php?status=<?= $s ?>"><button class="<?= $filter === $s ? 'active' : '' ?>"><?= $s ?></button></a>
    <?php endforeach; ?>
</div>

<div class="panel">
    <table>
        <tr><th>#</th><th>Customer</th><th>Items</th><th class="num">Total</th><th>Status</th><th>Placed</th><th>Update</th></tr>
        <?php foreach ($orders as $o):
            $itemSt->execute([$o['id']]);
            $lines = array_map(fn($i) => $i['qty'] . '× ' . $i['name'], $itemSt->fetchAll());
        ?>
            <tr>
                <td><a href="../receipt.php?id=<?= (int)$o['id'] ?>"><?= (int)$o['id'] ?></a></td>
                <td><?= e($o['customer_name']) ?><br><small><?= e($o['order_type']) ?></small></td>
                <td><small><?= e(implode(', ', $lines)) ?></small></td>
                <td class="num"><?= peso($o['total']) ?></td>
                <td><span class="badge <?= e($o['status']) ?>"><?= e($o['status']) ?></span></td>
                <td><small><?= e($o['created_at']) ?></small></td>
                <td>
                    <?php if ($o['status'] !== 'Cancelled'): ?>
                    <form method="post" class="inline">
                        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
                        <select name="status" onchange="if(this.value==='Cancelled' && !confirm('Cancel this order and return its stock?')){this.value='<?= e($o['status']) ?>';return;}this.form.submit()" style="width:auto">
                            <?php foreach ($statuses as $s): ?>
                                <option <?= $s === $o['status'] ? 'selected' : '' ?>><?= $s ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                    <?php else: ?>—<?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$orders): ?><tr><td colspan="7">No orders found.</td></tr><?php endif; ?>
    </table>
</div>
<?php page_foot();

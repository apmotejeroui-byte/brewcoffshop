<?php
require __DIR__ . '/../db.php';
require __DIR__ . '/../layout.php';
require_admin();
$pdo = db();

$today = $pdo->query("SELECT COUNT(*) c, COALESCE(SUM(total),0) s FROM orders WHERE date(created_at)=date('now','localtime') AND status!='Cancelled'")->fetch();
$allTime = $pdo->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE status!='Cancelled'")->fetchColumn();
$pending = $pdo->query("SELECT COUNT(*) FROM orders WHERE status IN ('Pending','Preparing')")->fetchColumn();
$lowStock = $pdo->query('SELECT name, stock FROM products WHERE active=1 AND stock<=10 ORDER BY stock, name')->fetchAll();
$top = $pdo->query("SELECT oi.name, SUM(oi.qty) q FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE o.status!='Cancelled' GROUP BY oi.name ORDER BY q DESC LIMIT 5")->fetchAll();
$recent = $pdo->query('SELECT * FROM orders ORDER BY id DESC LIMIT 8')->fetchAll();

page_head('Dashboard', '../', true);
?>
<h1>Dashboard</h1>
<div class="stats">
    <div class="stat"><div class="n"><?= peso($today['s']) ?></div><div class="l">Sales today (<?= (int)$today['c'] ?> orders)</div></div>
    <div class="stat"><div class="n"><?= (int)$pending ?></div><div class="l">Open orders (pending / preparing)</div></div>
    <div class="stat"><div class="n"><?= peso($allTime) ?></div><div class="l">All-time sales</div></div>
    <div class="stat"><div class="n"><?= count($lowStock) ?></div><div class="l">Items low on stock (≤10)</div></div>
</div>

<div class="cols">
    <div class="panel">
        <h2>Top sellers</h2>
        <table>
            <?php foreach ($top as $t): ?><tr><td><?= e($t['name']) ?></td><td class="num"><?= (int)$t['q'] ?> sold</td></tr><?php endforeach; ?>
            <?php if (!$top): ?><tr><td>No sales yet.</td></tr><?php endif; ?>
        </table>
    </div>
    <div class="panel">
        <h2>Low stock</h2>
        <table>
            <?php foreach ($lowStock as $l): ?>
                <tr><td><?= e($l['name']) ?></td><td class="num"><span class="badge <?= $l['stock'] == 0 ? 'Cancelled' : 'Pending' ?>"><?= (int)$l['stock'] ?> left</span></td></tr>
            <?php endforeach; ?>
            <?php if (!$lowStock): ?><tr><td>Everything is well stocked.</td></tr><?php endif; ?>
        </table>
    </div>
</div>

<div class="panel">
    <h2>Recent orders</h2>
    <table>
        <tr><th>#</th><th>Customer</th><th>Type</th><th>Status</th><th class="num">Total</th><th>Placed</th></tr>
        <?php foreach ($recent as $o): ?>
            <tr>
                <td><a href="../receipt.php?id=<?= (int)$o['id'] ?>"><?= (int)$o['id'] ?></a></td>
                <td><?= e($o['customer_name']) ?></td><td><?= e($o['order_type']) ?></td>
                <td><span class="badge <?= e($o['status']) ?>"><?= e($o['status']) ?></span></td>
                <td class="num"><?= peso($o['total']) ?></td><td><?= e($o['created_at']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$recent): ?><tr><td colspan="6">No orders yet.</td></tr><?php endif; ?>
    </table>
</div>
<?php page_foot();

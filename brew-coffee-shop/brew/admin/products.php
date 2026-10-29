<?php
require __DIR__ . '/../db.php';
require __DIR__ . '/../layout.php';
require_admin();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['action'] ?? '';
    $id  = (int)($_POST['id'] ?? 0);

    if ($act === 'add_category') {
        $n = trim($_POST['name'] ?? '');
        if ($n === '') flash('Category name is required.');
        else {
            try { $pdo->prepare('INSERT INTO categories (name) VALUES (?)')->execute([$n]); flash('Category added.'); }
            catch (PDOException $e) { flash('That category already exists.'); }
        }
    } elseif ($act === 'save') {
        $name  = trim($_POST['name'] ?? '');
        $cat   = (int)($_POST['category_id'] ?? 0);
        $price = (float)($_POST['price'] ?? -1);
        $stock = (int)($_POST['stock'] ?? -1);
        $desc  = trim($_POST['description'] ?? '');
        $emoji = trim($_POST['emoji'] ?? '') ?: '☕';
        if ($name === '' || !$cat || $price < 0 || $stock < 0) {
            flash('Please fill in name, category, a valid price and stock.');
        } elseif ($id) {
            $pdo->prepare('UPDATE products SET category_id=?,name=?,description=?,price=?,stock=?,emoji=? WHERE id=?')
                ->execute([$cat, $name, $desc, $price, $stock, $emoji, $id]);
            flash('Product updated.');
        } else {
            $pdo->prepare('INSERT INTO products (category_id,name,description,price,stock,emoji) VALUES (?,?,?,?,?,?)')
                ->execute([$cat, $name, $desc, $price, $stock, $emoji]);
            flash('Product added.');
        }
    } elseif ($act === 'toggle' && $id) {
        $pdo->prepare('UPDATE products SET active = 1 - active WHERE id=?')->execute([$id]);
        flash('Visibility changed.');
    } elseif ($act === 'delete' && $id) {
        $pdo->prepare('DELETE FROM products WHERE id=?')->execute([$id]);
        flash('Product deleted. Past orders keep their item names and prices.');
    }
    header('Location: products.php');
    exit;
}

$cats = $pdo->query('SELECT * FROM categories ORDER BY id')->fetchAll();
$products = $pdo->query('SELECT p.*, c.name AS category FROM products p JOIN categories c ON c.id=p.category_id ORDER BY c.id, p.name')->fetchAll();
$edit = null;
if (isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT * FROM products WHERE id=?');
    $st->execute([(int)$_GET['edit']]);
    $edit = $st->fetch() ?: null;
}

page_head('Products', '../', true);
?>
<h1>Products</h1>

<div class="panel">
    <h2><?= $edit ? 'Edit product' : 'Add product' ?></h2>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
        <div class="form-grid">
            <div><label>Name</label><input name="name" required value="<?= e($edit['name'] ?? '') ?>"></div>
            <div><label>Category</label>
                <select name="category_id">
                    <?php foreach ($cats as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= ($edit['category_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div><label>Price (₱)</label><input type="number" step="0.01" min="0" name="price" required value="<?= e($edit['price'] ?? '') ?>"></div>
            <div><label>Stock</label><input type="number" min="0" name="stock" required value="<?= e($edit['stock'] ?? 20) ?>"></div>
            <div><label>Emoji</label><input name="emoji" maxlength="4" value="<?= e($edit['emoji'] ?? '☕') ?>"></div>
        </div>
        <label>Description</label>
        <input name="description" value="<?= e($edit['description'] ?? '') ?>">
        <p>
            <button class="btn"><?= $edit ? 'Save changes' : 'Add product' ?></button>
            <?php if ($edit): ?><a class="btn ghost" href="products.php">Cancel</a><?php endif; ?>
        </p>
    </form>
    <form method="post" class="form-grid" style="max-width:420px">
        <?= csrf_field() ?><input type="hidden" name="action" value="add_category">
        <div><label>New category</label><input name="name" placeholder="e.g. Sandwiches"></div>
        <div><button class="btn ghost">Add category</button></div>
    </form>
</div>

<div class="panel">
    <table>
        <tr><th></th><th>Product</th><th>Category</th><th class="num">Price</th><th class="num">Stock</th><th>Shown</th><th></th></tr>
        <?php foreach ($products as $p): ?>
            <tr>
                <td><?= e($p['emoji']) ?></td>
                <td><strong><?= e($p['name']) ?></strong><br><small><?= e($p['description']) ?></small></td>
                <td><?= e($p['category']) ?></td>
                <td class="num"><?= peso($p['price']) ?></td>
                <td class="num"><?= (int)$p['stock'] ?></td>
                <td><?= $p['active'] ? 'Yes' : '<em>Hidden</em>' ?></td>
                <td style="white-space:nowrap">
                    <a class="btn small ghost" href="products.php?edit=<?= (int)$p['id'] ?>">Edit</a>
                    <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                        <button class="btn small ghost"><?= $p['active'] ? 'Hide' : 'Show' ?></button></form>
                    <form method="post" class="inline" onsubmit="return confirm('Delete <?= e(addslashes($p['name'])) ?>?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                        <button class="btn small danger">Delete</button></form>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>
<?php page_foot();

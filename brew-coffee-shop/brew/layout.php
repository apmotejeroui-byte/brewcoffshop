<?php
// Shared page chrome. $base is '' for root pages and '../' for admin pages.
function page_head(string $title, string $base = '', bool $admin = false): void { ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · Brew</title>
<link rel="stylesheet" href="<?= $base ?>assets/style.css">
</head>
<body>
<header class="topbar">
    <a class="brand" href="<?= $base ?>index.php"><span>☕</span> Brew <small>Coffee Shop</small></a>
    <nav>
        <?php if ($admin): ?>
            <a href="<?= $base ?>admin/index.php">Dashboard</a>
            <a href="<?= $base ?>admin/products.php">Products</a>
            <a href="<?= $base ?>admin/orders.php">Orders</a>
            <a href="<?= $base ?>index.php">Shop</a>
            <a href="<?= $base ?>logout.php">Logout</a>
        <?php else: ?>
            <a href="<?= $base ?>index.php">Menu</a>
            <?php if (is_admin()): ?>
                <a href="<?= $base ?>admin/index.php">Admin</a>
            <?php else: ?>
                <a href="<?= $base ?>login.php">Staff login</a>
            <?php endif; ?>
        <?php endif; ?>
    </nav>
</header>
<main>
<?php if ($m = flash()): ?><div class="notice"><?= e($m) ?></div><?php endif;
}

function page_foot(): void { ?>
</main>
</body>
</html>
<?php }

<?php
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';

if (is_admin()) { header('Location: admin/index.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $st = db()->prepare('SELECT * FROM users WHERE username = ?');
    $st->execute([trim($_POST['username'] ?? '')]);
    $u = $st->fetch();

    if ($u && password_verify($_POST['password'] ?? '', $u['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['admin'] = $u['id'];
        header('Location: admin/index.php');
        exit;
    }
    usleep(400000); // slow down guessing
    $error = 'Wrong username or password.';
}

page_head('Staff login');
?>
<div class="panel login-box">
    <h2>Staff login</h2>
    <?php if ($error): ?><div class="error"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
        <?= csrf_field() ?>
        <label for="u">Username</label>
        <input id="u" name="username" autocomplete="username" required autofocus>
        <label for="p">Password</label>
        <input id="p" type="password" name="password" autocomplete="current-password" required>
        <button class="btn" style="width:100%;margin-top:1rem">Sign in</button>
    </form>
    <p style="font-size:.8rem;color:#8a7765;margin-bottom:0">Default: admin / admin123 — change it after first login.</p>
</div>
<?php page_foot();

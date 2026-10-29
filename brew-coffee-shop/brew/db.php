<?php
// Brew Coffee Shop - database layer (SQLite via PDO, auto-creates and seeds itself)
session_start();

function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;

    $dir = __DIR__ . '/data';
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    // Keep the database file from being downloaded on Apache
    if (!file_exists($dir . '/.htaccess')) file_put_contents($dir . '/.htaccess', "Require all denied\n");

    $pdo = new PDO('sqlite:' . $dir . '/brew.sqlite');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL
        );
        CREATE TABLE IF NOT EXISTS categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT UNIQUE NOT NULL
        );
        CREATE TABLE IF NOT EXISTS products (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            category_id INTEGER NOT NULL REFERENCES categories(id),
            name TEXT NOT NULL,
            description TEXT DEFAULT '',
            price REAL NOT NULL CHECK (price >= 0),
            stock INTEGER NOT NULL DEFAULT 0 CHECK (stock >= 0),
            emoji TEXT DEFAULT '☕',
            active INTEGER NOT NULL DEFAULT 1
        );
        CREATE TABLE IF NOT EXISTS orders (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            customer_name TEXT NOT NULL,
            order_type TEXT NOT NULL DEFAULT 'Dine-in',
            status TEXT NOT NULL DEFAULT 'Pending',
            total REAL NOT NULL,
            created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
        );
        CREATE TABLE IF NOT EXISTS order_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
            product_id INTEGER REFERENCES products(id) ON DELETE SET NULL,
            name TEXT NOT NULL,
            price REAL NOT NULL,
            qty INTEGER NOT NULL
        );
    ");

    // Seed on first run
    if ((int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0) {
        $pdo->prepare('INSERT INTO users (username, password_hash) VALUES (?, ?)')
            ->execute(['admin', password_hash('admin123', PASSWORD_DEFAULT)]);
    }
    if ((int)$pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn() === 0) {
        foreach (['Hot Coffee', 'Iced Coffee', 'Non-Coffee', 'Pastries'] as $c) {
            $pdo->prepare('INSERT INTO categories (name) VALUES (?)')->execute([$c]);
        }
        $seed = [
            [1, 'Espresso',        'Double shot, bold and rich',          90,  50, '☕'],
            [1, 'Americano',       'Espresso with hot water',             100, 50, '☕'],
            [1, 'Cappuccino',      'Espresso, steamed milk, thick foam',  130, 50, '☕'],
            [1, 'Caffè Latte',     'Espresso with silky steamed milk',    140, 50, '🥛'],
            [2, 'Iced Americano',  'Chilled espresso over ice',           110, 50, '🧊'],
            [2, 'Iced Latte',      'Espresso, cold milk, ice',            150, 50, '🧊'],
            [2, 'Caramel Macchiato','Vanilla, milk, espresso, caramel',   165, 40, '🍮'],
            [2, 'Cold Brew',       '18-hour steeped, smooth and strong',  145, 30, '🫗'],
            [3, 'Matcha Latte',    'Ceremonial matcha with milk',         150, 30, '🍵'],
            [3, 'Hot Chocolate',   'Dark chocolate, steamed milk',        125, 30, '🍫'],
            [4, 'Butter Croissant','Flaky, baked fresh daily',             95, 20, '🥐'],
            [4, 'Blueberry Muffin','Moist with real blueberries',          85, 20, '🧁'],
            [4, 'Chocolate Chip Cookie','Chewy and warm',                  65, 25, '🍪'],
        ];
        $st = $pdo->prepare('INSERT INTO products (category_id,name,description,price,stock,emoji) VALUES (?,?,?,?,?,?)');
        foreach ($seed as $r) $st->execute($r);
    }
    return $pdo;
}

// ---------- helpers ----------
function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function peso($n): string { return '₱' . number_format((float)$n, 2); }

function is_admin(): bool { return !empty($_SESSION['admin']); }
function require_admin(): void {
    if (!is_admin()) {
        $inAdmin = basename(dirname($_SERVER['SCRIPT_NAME'])) === 'admin';
        header('Location: ' . ($inAdmin ? '../login.php' : 'login.php'));
        exit;
    }
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function csrf_field(): string {
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}
function csrf_check(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(400);
        exit('Invalid form token. Go back and try again.');
    }
}

function flash(?string $msg = null): ?string {
    if ($msg !== null) { $_SESSION['flash'] = $msg; return null; }
    $m = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $m;
}

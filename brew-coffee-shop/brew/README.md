# Brew Coffee Shop (PHP + SQLite)

A small coffee-shop ordering system: a customer menu/cart GUI, a transactional checkout, receipts,
and a staff admin area. The database is a single SQLite file that creates and seeds itself on first run,
so there is nothing to import or configure.

## Requirements
- PHP 8.0+ with the `pdo_sqlite` extension (enabled by default in most installs; in XAMPP, enable `extension=pdo_sqlite` in `php.ini` if needed)

## Run it
**Quickest (built-in server):**
```
cd brew
php -S localhost:8000
```
Open http://localhost:8000

**XAMPP / Apache:** copy the `brew` folder into `htdocs` and open http://localhost/brew/
(the `data/` folder must be writable by the web server).

## Pages
| URL | What it does |
|---|---|
| `/index.php` | Customer menu, category filter, live cart, place order |
| `/receipt.php?id=N` | Printable receipt (only the customer's own session, or staff) |
| `/login.php` | Staff login — default **admin / admin123** |
| `/admin/index.php` | Dashboard: sales today, open orders, low stock, top sellers |
| `/admin/products.php` | Add / edit / hide / delete products, add categories, manage stock |
| `/admin/orders.php` | Filter orders, move them Pending → Preparing → Ready → Completed, or Cancel (returns stock) |

## Database (`data/brew.sqlite`)
`users`, `categories`, `products`, `orders`, `order_items`. Order lines store the product name and price
at time of sale, so history stays correct if you later change or delete a product.
To reset everything, stop the server and delete `data/brew.sqlite`.

## Safety notes
- All SQL uses prepared statements; all output is HTML-escaped; forms and checkout use CSRF tokens.
- Prices and totals are always recalculated on the server; stock is checked and deducted inside a locked transaction.
- Change the default admin password before using this for real. To set a new one, run:
  `php -r "echo password_hash('NEWPASSWORD', PASSWORD_DEFAULT);"` and update the `users` row (e.g. with the `sqlite3` CLI or DB Browser for SQLite).
- If you deploy publicly, serve over HTTPS and block web access to the `data/` folder.

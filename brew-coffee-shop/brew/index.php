<?php
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';

$pdo  = db();
$cats = $pdo->query('SELECT * FROM categories ORDER BY id')->fetchAll();
$products = $pdo->query(
    'SELECT * FROM products WHERE active = 1 ORDER BY category_id, name'
)->fetchAll();

page_head('Menu');
?>
<div class="hero">
    <h1>Fresh-brewed, made to order</h1>
    <p>Pick your drinks, place your order, and we'll call your name.</p>
</div>

<div class="shop">
    <section>
        <div class="tabs" id="tabs">
            <button class="active" data-cat="all">All</button>
            <?php foreach ($cats as $c): ?>
                <button data-cat="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></button>
            <?php endforeach; ?>
        </div>

        <div class="grid" id="grid">
            <?php foreach ($products as $p): $sold = $p['stock'] <= 0; ?>
                <article class="card <?= $sold ? 'sold' : '' ?>" data-cat="<?= (int)$p['category_id'] ?>">
                    <div class="emoji"><?= e($p['emoji']) ?></div>
                    <h3><?= e($p['name']) ?></h3>
                    <p><?= e($p['description']) ?></p>
                    <div class="row">
                        <span class="price"><?= peso($p['price']) ?></span>
                        <span class="stock <?= $p['stock'] > 0 && $p['stock'] <= 5 ? 'low' : '' ?>">
                            <?= $sold ? 'Sold out' : ($p['stock'] <= 5 ? 'Only ' . (int)$p['stock'] . ' left' : '') ?>
                        </span>
                    </div>
                    <button class="btn add"
                        data-id="<?= (int)$p['id'] ?>"
                        data-name="<?= e($p['name']) ?>"
                        data-price="<?= e($p['price']) ?>"
                        data-stock="<?= (int)$p['stock'] ?>"
                        <?= $sold ? 'disabled' : '' ?>>
                        <?= $sold ? 'Unavailable' : 'Add to order' ?>
                    </button>
                </article>
            <?php endforeach; ?>
            <?php if (!$products): ?><p>No products available right now.</p><?php endif; ?>
        </div>
    </section>

    <aside class="cart">
        <h2>Your order</h2>
        <ul class="cart-items" id="cartItems"></ul>
        <div class="cart-empty" id="cartEmpty">Your cart is empty.</div>
        <div class="total"><span>Total</span><span id="cartTotal">₱0.00</span></div>

        <label for="cname">Name for the order</label>
        <input id="cname" maxlength="60" placeholder="e.g. Maria">

        <label>Order type</label>
        <div class="seg">
            <label><input type="radio" name="otype" value="Dine-in" checked><span>Dine-in</span></label>
            <label><input type="radio" name="otype" value="Takeout"><span>Takeout</span></label>
        </div>

        <button class="btn" id="checkout" style="width:100%;margin-top:1rem" disabled>Place order</button>
        <div id="msg"></div>
    </aside>
</div>

<script>
const CSRF = <?= json_encode(csrf_token()) ?>;
const cart = {};   // id -> {id, name, price, stock, qty}

const money = n => '₱' + Number(n).toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
const $ = s => document.querySelector(s);

// Category filter
$('#tabs').addEventListener('click', ev => {
    const b = ev.target.closest('button'); if (!b) return;
    document.querySelectorAll('#tabs button').forEach(x => x.classList.toggle('active', x === b));
    document.querySelectorAll('#grid .card').forEach(c => {
        c.style.display = (b.dataset.cat === 'all' || c.dataset.cat === b.dataset.cat) ? '' : 'none';
    });
});

// Add to cart
document.querySelectorAll('.btn.add').forEach(btn => btn.addEventListener('click', () => {
    const id = btn.dataset.id;
    const stock = parseInt(btn.dataset.stock, 10);
    if (!cart[id]) cart[id] = {id, name: btn.dataset.name, price: parseFloat(btn.dataset.price), stock, qty: 0};
    if (cart[id].qty < stock) cart[id].qty++;
    else say('Only ' + stock + ' of ' + cart[id].name + ' available.', true);
    render();
}));

function say(text, bad) {
    const m = $('#msg');
    m.textContent = text;
    m.style.color = bad ? 'var(--bad)' : 'var(--ok)';
}

function render() {
    const list = $('#cartItems');
    list.innerHTML = '';
    let total = 0, count = 0;
    Object.values(cart).forEach(it => {
        total += it.price * it.qty; count += it.qty;
        const li = document.createElement('li');
        const name = document.createElement('span'); name.className = 'name'; name.textContent = it.name;
        const sub = document.createElement('span'); sub.textContent = money(it.price * it.qty);
        const qty = document.createElement('span'); qty.className = 'qty';
        const minus = document.createElement('button'); minus.textContent = '−';
        const num = document.createElement('span'); num.textContent = it.qty;
        const plus = document.createElement('button'); plus.textContent = '+';
        minus.onclick = () => { it.qty--; if (it.qty <= 0) delete cart[it.id]; render(); };
        plus.onclick = () => { if (it.qty < it.stock) { it.qty++; render(); } };
        qty.append(minus, num, plus);
        li.append(name, sub, qty);
        list.appendChild(li);
    });
    $('#cartEmpty').style.display = count ? 'none' : '';
    $('#cartTotal').textContent = money(total);
    $('#checkout').disabled = count === 0;
}

// Checkout
$('#checkout').addEventListener('click', async () => {
    const btn = $('#checkout');
    const name = $('#cname').value.trim();
    if (!name) { say('Please enter a name for the order.', true); $('#cname').focus(); return; }
    btn.disabled = true; say('Placing your order…');
    try {
        const res = await fetch('checkout.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                csrf: CSRF,
                name,
                type: document.querySelector('input[name=otype]:checked').value,
                items: Object.values(cart).map(i => ({id: i.id, qty: i.qty}))
            })
        });
        const data = await res.json();
        if (!res.ok) throw new Error(data.error || 'Something went wrong.');
        location.href = 'receipt.php?id=' + data.order_id;
    } catch (err) {
        say(err.message, true);
        btn.disabled = false;
    }
});

render();
</script>
<?php page_foot();

@extends('layouts.app')

@section('title', 'Kasir POS')

@section('content')
    <style>
        .form-card { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 22px; max-width: 760px; }
        .field { margin-bottom: 14px; }
        .field label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px; }
        .field input, .field select { width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid var(--line); border-radius: 9px; }
        .btn-primary { padding: 10px 18px; background: var(--pine); color: #fff; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; }
        .error-text { color: var(--brick); font-size: 12px; margin-top: 4px; }
        .hint { font-size: 11px; color: var(--muted); margin: 8px 0; }
        .data-table { width: 100%; border-collapse: collapse; background: var(--surface); border: 1px solid var(--line); border-radius: 12px; overflow: hidden; margin-top: 24px; }
        .data-table th, .data-table td { text-align: left; padding: 10px 14px; border-bottom: 1px solid var(--line); font-size: 13px; }
        .data-table th { background: var(--paper); font-weight: 700; color: var(--muted); }

        .branch-bar { max-width: 260px; margin-bottom: 16px; }

        .pos-layout { display: flex; gap: 18px; align-items: flex-start; flex-wrap: wrap; }
        .product-panel { flex: 1 1 560px; min-width: 320px; }
        .cart-panel { flex: 0 0 360px; width: 360px; position: sticky; top: 16px; background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 18px; }

        .product-toolbar { display: flex; flex-direction: column; gap: 10px; margin-bottom: 14px; }
        .product-toolbar input[type="text"] { width: 100%; box-sizing: border-box; padding: 10px 14px; border: 1px solid var(--line); border-radius: 10px; font-size: 14px; }
        .category-chips { display: flex; flex-wrap: wrap; gap: 8px; }
        .category-chip { padding: 6px 14px; border-radius: 999px; border: 1px solid var(--line); background: var(--surface); color: var(--muted); font-size: 12px; font-weight: 600; cursor: pointer; }
        .category-chip.active { background: var(--pine); color: #fff; border-color: var(--pine); }

        .product-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 12px; }
        .product-card { background: var(--surface); border: 1px solid var(--line); border-radius: 12px; padding: 10px; cursor: pointer; text-align: left; transition: box-shadow 120ms ease, transform 120ms ease; }
        .product-card:hover { box-shadow: var(--card-shadow); transform: translateY(-2px); }
        .product-thumb, .product-thumb-placeholder { width: 100%; aspect-ratio: 1 / 1; border-radius: 8px; margin-bottom: 8px; }
        .product-thumb { object-fit: cover; border: 1px solid var(--line); }
        .product-thumb-placeholder { background: var(--leaf); color: var(--pine-bright); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 18px; }
        .product-card-name { font-size: 12.5px; font-weight: 600; line-height: 1.3; margin-bottom: 4px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .product-card-price { font-size: 12.5px; font-weight: 700; color: var(--pine-bright); }
        .product-empty { color: var(--muted); font-size: 13px; padding: 24px 0; text-align: center; }

        .payment-tabs { display: flex; gap: 6px; margin-bottom: 14px; }
        .payment-tab { flex: 1; padding: 9px 8px; border-radius: 9px; border: 1px solid var(--line); background: var(--surface); color: var(--muted); font-size: 12px; font-weight: 700; cursor: pointer; }
        .payment-tab.active { background: var(--pine); color: #fff; border-color: var(--pine); }

        .search-box { position: relative; margin-bottom: 10px; }
        .search-results { position: absolute; top: 100%; left: 0; right: 0; z-index: 5; background: var(--surface); border: 1px solid var(--line); border-radius: 9px; margin-top: 4px; max-height: 180px; overflow-y: auto; box-shadow: var(--shadow); }
        .search-result { display: block; width: 100%; text-align: left; padding: 8px 12px; border: none; background: none; cursor: pointer; font-size: 12.5px; color: var(--pine-ink); }
        .search-result:hover { background: var(--leaf); }

        .cart-empty { color: var(--muted); font-size: 12.5px; text-align: center; padding: 16px 0; }
        .cart-line { display: grid; grid-template-columns: 1fr auto auto auto; align-items: center; gap: 8px; padding: 8px 0; border-bottom: 1px solid var(--line); font-size: 12.5px; }
        .cart-line-name { font-weight: 600; }
        .cart-line-qty { display: flex; align-items: center; gap: 6px; }
        .qty-btn { width: 24px; height: 24px; border-radius: 6px; border: 1px solid var(--line); background: var(--surface); color: var(--pine-ink); font-weight: 700; cursor: pointer; }
        .cart-line-subtotal { font-weight: 700; white-space: nowrap; }
        .cart-line-remove { width: 22px; height: 22px; border-radius: 6px; border: 1px solid var(--brick); background: transparent; color: var(--brick); cursor: pointer; font-weight: 700; line-height: 1; }

        .cart-total-row { display: flex; justify-content: space-between; align-items: baseline; margin: 14px 0; font-weight: 700; }
        .cart-total-row .amount { font-size: 20px; color: var(--pine-bright); }

        .btn-pay { width: 100%; padding: 13px; background: var(--pine); color: #fff; border: none; border-radius: 10px; font-weight: 700; font-size: 15px; cursor: pointer; margin-top: 6px; }
        .btn-pay:disabled { opacity: .5; cursor: not-allowed; }
    </style>

    <h2>Kasir POS</h2>

    @if (session('status'))
        <p style="color:var(--ok); font-size:13px; margin-bottom:14px;">{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p class="error-text" style="margin-bottom:14px;">{{ session('error') }}</p>
    @endif
    @if ($errors->any())
        <div class="error-text" style="margin-bottom: 12px;">
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('staf.pos.store') }}" id="pos-form">
        @csrf

        <div class="field branch-bar">
            <label>Cabang</label>
            <select name="branch_id" required>
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="pos-layout">
            <div class="product-panel">
                <div class="product-toolbar">
                    <input type="text" id="product-search" placeholder="Cari barang berdasarkan nama/kode...">
                    <div class="category-chips">
                        <button type="button" class="category-chip active" data-category="">Semua</button>
                        @foreach ($categories as $category)
                            <button type="button" class="category-chip" data-category="{{ $category }}">{{ $category }}</button>
                        @endforeach
                    </div>
                </div>

                <div class="product-grid" id="product-grid">
                    @forelse ($products as $product)
                        <div class="product-card" data-id="{{ $product->id }}" data-code="{{ $product->code }}"
                             data-name="{{ $product->name }}" data-price="{{ $product->selling_price }}"
                             data-category="{{ $product->category }}">
                            @if ($product->image_path)
                                <img src="{{ \Illuminate\Support\Facades\Storage::url($product->image_path) }}" alt="{{ $product->name }}" class="product-thumb">
                            @else
                                <div class="product-thumb-placeholder">{{ strtoupper(mb_substr($product->name, 0, 2)) }}</div>
                            @endif
                            <div class="product-card-name">{{ $product->name }}</div>
                            <div class="product-card-price">Rp {{ number_format((float) $product->selling_price, 0, ',', '.') }}</div>
                        </div>
                    @empty
                        <p class="product-empty">Belum ada barang aktif.</p>
                    @endforelse
                </div>
            </div>

            <div class="cart-panel">
                <input type="hidden" name="payment_method" id="payment_method" value="tunai">

                <div class="payment-tabs">
                    <button type="button" class="payment-tab active" data-method="tunai">Tunai</button>
                    <button type="button" class="payment-tab" data-method="potong_simpanan">Potong Simpanan</button>
                    <button type="button" class="payment-tab" data-method="hutang">Hutang</button>
                </div>

                <div id="fields-potong-simpanan" style="display:none;">
                    <div class="search-box">
                        <input type="text" id="savings-search" placeholder="Cari No. Rekening / Nama Anggota...">
                        <div class="search-results" id="savings-results"></div>
                    </div>
                    <input type="hidden" name="savings_account_id" id="savings_account_id">
                </div>

                <div id="fields-hutang" style="display:none;">
                    <div class="search-box">
                        <input type="text" id="member-search" placeholder="Cari No. Anggota / Nama...">
                        <div class="search-results" id="member-results"></div>
                    </div>
                    <input type="hidden" name="member_id" id="member_id">
                    <div class="field">
                        <label>Produk Pinjaman</label>
                        <select name="loan_product_id">
                            <option value="">— Pilih Produk —</option>
                            @foreach ($loanProducts as $loanProduct)
                                <option value="{{ $loanProduct->id }}">{{ $loanProduct->code }} — {{ $loanProduct->name }} ({{ $loanProduct->tenorLabel() }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label>Tenor</label>
                        <input type="number" name="tenor_days" min="1">
                        <p style="font-size:11px; color: var(--muted); margin-top:4px;">Isi sesuai satuan produk yang dipilih (lihat keterangan hari/bulan).</p>
                    </div>
                </div>

                <p class="cart-empty" id="cart-empty">Keranjang masih kosong — tap barang untuk menambahkan.</p>
                <div id="cart-lines"></div>
                <div id="cart-inputs"></div>

                <div class="cart-total-row">
                    <span>Total</span>
                    <span class="amount" id="cart-total">Rp 0</span>
                </div>

                <button type="submit" class="btn-pay">Bayar</button>
            </div>
        </div>
    </form>

    <h3>Transaksi Hari Ini</h3>
    <table class="data-table">
        <thead><tr><th>No. Transaksi</th><th>Total</th><th>Metode Bayar</th><th>Struk</th></tr></thead>
        <tbody>
            @forelse ($recentSales as $sale)
                <tr>
                    <td>{{ $sale->sale_number }}</td>
                    <td>Rp {{ number_format((float) $sale->total_amount, 0, ',', '.') }}</td>
                    <td>{{ match ($sale->payment_method) { 'tunai' => 'Tunai', 'potong_simpanan' => 'Potong Simpanan', 'hutang' => 'Hutang' } }}</td>
                    <td><a href="{{ route('staf.pos.receipt', $sale) }}">Lihat Struk</a></td>
                </tr>
            @empty
                <tr><td colspan="4">Belum ada transaksi POS hari ini.</td></tr>
            @endforelse
        </tbody>
    </table>

    <script>
        (function () {
            var cart = [];
            var productCards = document.querySelectorAll('.product-card');
            var searchInput = document.getElementById('product-search');
            var categoryChips = document.querySelectorAll('.category-chip');
            var activeCategory = '';

            var cartLinesEl = document.getElementById('cart-lines');
            var cartInputsEl = document.getElementById('cart-inputs');
            var cartEmptyEl = document.getElementById('cart-empty');
            var cartTotalEl = document.getElementById('cart-total');

            function formatRupiah(value) {
                return 'Rp ' + Math.round(value).toLocaleString('id-ID');
            }

            function renderCart() {
                cartLinesEl.innerHTML = '';
                cartInputsEl.innerHTML = '';
                var total = 0;

                cart.forEach(function (line, i) {
                    total += line.qty * line.price;

                    var row = document.createElement('div');
                    row.className = 'cart-line';
                    row.innerHTML =
                        '<div class="cart-line-name">' + line.name + '</div>' +
                        '<div class="cart-line-qty">' +
                            '<button type="button" class="qty-btn" data-action="dec" data-id="' + line.productId + '">−</button>' +
                            '<span>' + line.qty + '</span>' +
                            '<button type="button" class="qty-btn" data-action="inc" data-id="' + line.productId + '">+</button>' +
                        '</div>' +
                        '<div class="cart-line-subtotal">' + formatRupiah(line.qty * line.price) + '</div>' +
                        '<button type="button" class="cart-line-remove" data-action="remove" data-id="' + line.productId + '">&times;</button>';
                    cartLinesEl.appendChild(row);

                    // Explicit sequential indices rebuilt from scratch every
                    // render — never `items[][x]` (see Jurnal Umum lesson:
                    // bare-bracket names don't group per-row in PHP's parser).
                    cartInputsEl.insertAdjacentHTML('beforeend',
                        '<input type="hidden" name="items[' + i + '][product_id]" value="' + line.productId + '">' +
                        '<input type="hidden" name="items[' + i + '][qty]" value="' + line.qty + '">');
                });

                cartTotalEl.textContent = formatRupiah(total);
                cartEmptyEl.style.display = cart.length === 0 ? 'block' : 'none';
            }

            function addToCart(id, name, price) {
                var existing = cart.find(function (l) { return l.productId === id; });
                if (existing) {
                    existing.qty += 1;
                } else {
                    cart.push({ productId: id, name: name, price: price, qty: 1 });
                }
                renderCart();
            }

            function changeQty(id, delta) {
                var line = cart.find(function (l) { return l.productId === id; });
                if (!line) return;
                line.qty += delta;
                if (line.qty <= 0) {
                    cart = cart.filter(function (l) { return l.productId !== id; });
                }
                renderCart();
            }

            function removeFromCart(id) {
                cart = cart.filter(function (l) { return l.productId !== id; });
                renderCart();
            }

            productCards.forEach(function (card) {
                card.addEventListener('click', function () {
                    addToCart(card.dataset.id, card.dataset.name, parseFloat(card.dataset.price));
                });
            });

            cartLinesEl.addEventListener('click', function (event) {
                var btn = event.target.closest('[data-action]');
                if (!btn) return;
                var id = btn.dataset.id;
                if (btn.dataset.action === 'inc') changeQty(id, 1);
                if (btn.dataset.action === 'dec') changeQty(id, -1);
                if (btn.dataset.action === 'remove') removeFromCart(id);
            });

            function filterProducts() {
                var q = searchInput.value.trim().toLowerCase();
                productCards.forEach(function (card) {
                    var matchesText = card.dataset.name.toLowerCase().indexOf(q) !== -1
                        || card.dataset.code.toLowerCase().indexOf(q) !== -1;
                    var matchesCategory = activeCategory === '' || card.dataset.category === activeCategory;
                    card.style.display = (matchesText && matchesCategory) ? '' : 'none';
                });
            }

            searchInput.addEventListener('input', filterProducts);

            categoryChips.forEach(function (chip) {
                chip.addEventListener('click', function () {
                    categoryChips.forEach(function (c) { c.classList.remove('active'); });
                    chip.classList.add('active');
                    activeCategory = chip.dataset.category || '';
                    filterProducts();
                });
            });

            // Payment method tabs
            var paymentMethodInput = document.getElementById('payment_method');
            var paymentTabs = document.querySelectorAll('.payment-tab');
            var fieldsPotongSimpanan = document.getElementById('fields-potong-simpanan');
            var fieldsHutang = document.getElementById('fields-hutang');
            var savingsHidden = document.getElementById('savings_account_id');
            var memberHidden = document.getElementById('member_id');
            var loanProductSelect = document.querySelector('select[name="loan_product_id"]');
            var tenorInput = document.querySelector('input[name="tenor_days"]');

            function setPaymentMethod(method) {
                paymentMethodInput.value = method;
                paymentTabs.forEach(function (tab) {
                    tab.classList.toggle('active', tab.dataset.method === method);
                });
                fieldsPotongSimpanan.style.display = method === 'potong_simpanan' ? 'block' : 'none';
                fieldsHutang.style.display = method === 'hutang' ? 'block' : 'none';
                savingsHidden.required = method === 'potong_simpanan';
                memberHidden.required = method === 'hutang';
                if (loanProductSelect) loanProductSelect.required = method === 'hutang';
                if (tenorInput) tenorInput.required = method === 'hutang';
            }

            paymentTabs.forEach(function (tab) {
                tab.addEventListener('click', function () { setPaymentMethod(tab.dataset.method); });
            });

            function initDebouncedSearch(inputEl, resultsEl, hiddenInputEl, endpointUrl) {
                var timer = null;
                inputEl.addEventListener('input', function () {
                    clearTimeout(timer);
                    hiddenInputEl.value = '';
                    var q = inputEl.value.trim();
                    if (q.length < 2) {
                        resultsEl.innerHTML = '';
                        return;
                    }
                    timer = setTimeout(function () {
                        fetch(endpointUrl + '?q=' + encodeURIComponent(q))
                            .then(function (response) { return response.json(); })
                            .then(function (items) {
                                resultsEl.innerHTML = '';
                                items.forEach(function (item) {
                                    var btn = document.createElement('button');
                                    btn.type = 'button';
                                    btn.className = 'search-result';
                                    btn.textContent = item.label;
                                    btn.addEventListener('click', function () {
                                        hiddenInputEl.value = item.id;
                                        inputEl.value = item.label;
                                        resultsEl.innerHTML = '';
                                    });
                                    resultsEl.appendChild(btn);
                                });
                            });
                    }, 300);
                });
            }

            initDebouncedSearch(
                document.getElementById('savings-search'),
                document.getElementById('savings-results'),
                savingsHidden,
                '{{ route('staf.pos.search-savings-accounts') }}'
            );
            initDebouncedSearch(
                document.getElementById('member-search'),
                document.getElementById('member-results'),
                memberHidden,
                '{{ route('staf.pos.search-members') }}'
            );

            document.getElementById('pos-form').addEventListener('submit', function (event) {
                if (cart.length === 0) {
                    event.preventDefault();
                    alert('Keranjang masih kosong — tambahkan barang terlebih dahulu.');
                }
            });

            renderCart();
            setPaymentMethod('tunai');
        })();
    </script>
@endsection

<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MotoVault POS — Modern Cashier & Direct Billing Terminal</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        vault: {
                            50: '#f0fdf4',
                            500: '#10b981',
                            600: '#059669',
                            dark: '#090d16',
                            card: '#111827',
                            surface: '#161e2e',
                            border: '#1f293d',
                            accent: '#f59e0b',
                            crimson: '#ef4444'
                        }
                    }
                }
            }
        }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        code, pre, .font-mono { font-family: 'JetBrains Mono', monospace; }
        .pos-grid {
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        }
        @media print {
            body * { visibility: hidden; }
            #receipt-modal-content, #receipt-modal-content * { visibility: visible; }
            #receipt-modal-content {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                background: white !important;
                color: black !important;
            }
        }
    </style>
</head>
<body class="bg-[#090d16] text-gray-100 min-h-screen flex flex-col antialiased selection:bg-emerald-500 selection:text-black overflow-hidden">

    <!-- Top POS Navigation Bar -->
    <header class="h-16 bg-[#111827]/90 border-b border-gray-800 px-4 sm:px-6 flex items-center justify-between z-30 flex-shrink-0">
        <div class="flex items-center space-x-4">
            <a href="/" class="flex items-center space-x-2.5 text-gray-400 hover:text-white transition group pr-3 border-r border-gray-800">
                <svg class="w-5 h-5 text-gray-400 group-hover:-translate-x-0.5 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                <span class="text-xs font-semibold">Storefront</span>
            </a>

            <div class="flex items-center space-x-2.5">
                <div class="w-8 h-8 rounded-lg bg-gradient-to-tr from-emerald-500 to-cyan-500 flex items-center justify-center font-bold text-white text-sm shadow-md">
                    MV
                </div>
                <div>
                    <div class="flex items-center space-x-2">
                        <span class="text-sm font-extrabold text-white tracking-tight">MotoVault POS</span>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">ONLINE</span>
                    </div>
                    <div class="text-[11px] text-gray-400 font-medium">Terminal Kasir Toko Fisik & Bengkel</div>
                </div>
            </div>
        </div>

        <!-- Center: Search SKU / Barcode Bar -->
        <div class="hidden md:flex items-center flex-1 max-w-md mx-6">
            <div class="relative w-full">
                <input type="text" id="pos-search-input" placeholder="Cari nama produk, brand, atau scan SKU... (Tekan /)" 
                       class="w-full bg-gray-900 border border-gray-700/80 rounded-xl pl-9 pr-12 py-2 text-xs text-gray-200 placeholder-gray-500 focus:outline-none focus:border-emerald-500 transition"
                       oninput="filterProducts()">
                <svg class="w-4 h-4 text-gray-500 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <span class="absolute right-3 top-2 text-[10px] font-mono px-1.5 py-0.5 rounded bg-gray-800 text-gray-400 border border-gray-700">/</span>
            </div>
        </div>

        <!-- Right: Cashier Info & Live Clock -->
        <div class="flex items-center space-x-4">
            <div class="hidden lg:flex flex-col text-right">
                <span id="pos-clock" class="text-xs font-bold text-gray-200 font-mono">15:40:00</span>
                <span class="text-[10px] text-gray-500">Cabang: <b>Jakarta Selatan Hub</b></span>
            </div>

            <div class="flex items-center space-x-2.5 bg-gray-900/80 border border-gray-800 px-3 py-1.5 rounded-xl">
                <div class="w-7 h-7 rounded-lg bg-emerald-600/30 text-emerald-400 flex items-center justify-center font-bold text-xs">
                    ST
                </div>
                <div class="text-left text-xs">
                    <div class="font-bold text-white leading-none">Store Staff</div>
                    <div class="text-[10px] text-emerald-400 font-mono mt-0.5">Kasir #01</div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Workspace Split Screen -->
    <main class="flex-1 flex flex-col md:flex-row overflow-hidden">
        
        <!-- LEFT: Product Grid & Category Filters (65% width) -->
        <section class="flex-1 flex flex-col border-r border-gray-800/80 bg-[#0b0f19] overflow-hidden">
            
            <!-- Category Filter Pills -->
            <div class="p-3 bg-gray-900/40 border-b border-gray-800/80 flex items-center space-x-2 overflow-x-auto flex-shrink-0">
                <button onclick="filterCategory('')" class="cat-pill active px-3.5 py-1.5 rounded-lg text-xs font-bold bg-emerald-500 text-black whitespace-nowrap transition">
                    Semua Kategori
                </button>
                @foreach($categories as $cat)
                    <button onclick="filterCategory('{{ $cat->id }}')" class="cat-pill px-3.5 py-1.5 rounded-lg text-xs font-semibold bg-gray-900 border border-gray-800 text-gray-400 hover:text-white hover:border-gray-700 whitespace-nowrap transition">
                        {{ $cat->name }}
                    </button>
                @endforeach
            </div>

            <!-- Product Cards Catalog Scroll Area -->
            <div class="flex-1 p-4 overflow-y-auto">
                <div id="pos-product-grid" class="grid pos-grid gap-3.5">
                    @foreach($variants as $v)
                        <div class="product-card group bg-gray-900/80 border border-gray-800/90 rounded-2xl p-3.5 hover:border-emerald-500/50 hover:bg-gray-900 transition flex flex-col justify-between cursor-pointer active:scale-95 shadow-md"
                             data-id="{{ $v->id }}"
                             data-sku="{{ $v->sku }}"
                             data-name="{{ $v->product->name }} - {{ $v->variant_name }}"
                             data-price="{{ $v->final_price }}"
                             data-stock="{{ $v->stock }}"
                             data-category="{{ $v->product->category_id }}"
                             onclick="addToCart({{ $v->id }}, '{{ addslashes($v->sku) }}', '{{ addslashes($v->product->name . ' - ' . $v->variant_name) }}', {{ $v->final_price }}, {{ $v->stock }})">
                            
                            <div>
                                <div class="flex items-center justify-between mb-1.5">
                                    <span class="text-[10px] font-bold text-emerald-400 uppercase tracking-wider font-mono">{{ $v->product->brand }}</span>
                                    <span class="text-[10px] px-1.5 py-0.5 rounded font-bold {{ $v->stock > 5 ? 'bg-emerald-500/10 text-emerald-400' : 'bg-amber-500/10 text-amber-400' }}">
                                        {{ $v->stock }} unit
                                    </span>
                                </div>
                                <h3 class="text-xs font-bold text-white line-clamp-2 leading-snug group-hover:text-emerald-300 transition">
                                    {{ $v->product->name }} <span class="text-gray-400 font-normal">({{ $v->variant_name }})</span>
                                </h3>
                                <div class="text-[10px] font-mono text-gray-500 mt-1">SKU: {{ $v->sku }}</div>
                            </div>

                            <div class="mt-3 pt-2.5 border-t border-gray-800/80 flex items-center justify-between">
                                <span class="text-xs font-extrabold text-emerald-400 font-mono">
                                    Rp {{ number_format($v->final_price, 0, ',', '.') }}
                                </span>
                                <div class="w-6 h-6 rounded-lg bg-emerald-500/10 group-hover:bg-emerald-500 text-emerald-400 group-hover:text-black flex items-center justify-center transition">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                                    </svg>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <!-- RIGHT: Current Order Cart & Checkout Drawer (35% width) -->
        <section class="w-full md:w-[420px] lg:w-[450px] bg-[#111827] flex flex-col flex-shrink-0 z-20 shadow-2xl">
            
            <!-- Cart Header -->
            <div class="p-4 border-b border-gray-800 flex items-center justify-between bg-gray-900/40">
                <div class="flex items-center space-x-2">
                    <span class="text-base font-bold text-white">Keranjang Pesanan</span>
                    <span id="cart-item-count" class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-emerald-500/20 text-emerald-400 font-mono">0 item</span>
                </div>
                <button onclick="clearCart()" class="text-xs text-rose-400 hover:text-rose-300 font-medium transition">
                    Kosongkan
                </button>
            </div>

            <!-- Customer Details Input -->
            <div class="p-3 bg-gray-900/30 border-b border-gray-800 flex items-center space-x-2">
                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
                <input type="text" id="pos-customer-input" placeholder="Nama Pelanggan (Default: Pelanggan Walk-In)" 
                       class="flex-1 bg-transparent border-none text-xs text-gray-200 placeholder-gray-500 focus:outline-none">
            </div>

            <!-- Cart Line Items (Scrollable) -->
            <div id="cart-items-container" class="flex-1 overflow-y-auto p-4 space-y-2.5">
                <div class="h-full flex flex-col items-center justify-center text-center py-16 text-gray-500">
                    <svg class="w-12 h-12 text-gray-600 mb-2 stroke-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    <p class="text-xs">Keranjang masih kosong</p>
                    <p class="text-[11px] text-gray-600 mt-0.5">Pilih suku cadang dari daftar di sebelah kiri</p>
                </div>
            </div>

            <!-- Cart Calculation & Payment Footer -->
            <div class="p-4 border-t border-gray-800 bg-gray-900/60 flex flex-col space-y-3">
                
                <!-- Subtotals -->
                <div class="space-y-1.5 text-xs text-gray-400">
                    <div class="flex justify-between">
                        <span>Subtotal:</span>
                        <span id="pos-subtotal" class="font-mono text-gray-200">Rp 0</span>
                    </div>
                    <div class="flex justify-between text-gray-400">
                        <span>PPN (0% Bebas Pajak):</span>
                        <span class="font-mono text-gray-300">Rp 0</span>
                    </div>
                    <div class="flex justify-between items-baseline pt-2 border-t border-gray-800 text-sm font-bold text-white">
                        <span>TOTAL TAGIHAN:</span>
                        <span id="pos-grand-total" class="text-xl font-extrabold text-emerald-400 font-mono">Rp 0</span>
                    </div>
                </div>

                <!-- Payment Method Selector -->
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-1.5">Metode Bayar:</label>
                    <div class="grid grid-cols-4 gap-1.5 text-xs">
                        <label class="pay-method-btn flex items-center justify-center py-2 px-1 rounded-xl bg-emerald-500 text-black font-bold cursor-pointer transition text-center shadow-sm" onclick="selectPayMethod('CASH')">
                            <input type="radio" name="pos_pay" value="CASH" checked class="hidden"> CASH
                        </label>
                        <label class="pay-method-btn flex items-center justify-center py-2 px-1 rounded-xl bg-gray-900 border border-gray-700 text-gray-300 font-semibold cursor-pointer transition text-center hover:border-emerald-500" onclick="selectPayMethod('QRIS')">
                            <input type="radio" name="pos_pay" value="QRIS" class="hidden"> QRIS
                        </label>
                        <label class="pay-method-btn flex items-center justify-center py-2 px-1 rounded-xl bg-gray-900 border border-gray-700 text-gray-300 font-semibold cursor-pointer transition text-center hover:border-emerald-500" onclick="selectPayMethod('DEBIT')">
                            <input type="radio" name="pos_pay" value="DEBIT" class="hidden"> DEBIT
                        </label>
                        <label class="pay-method-btn flex items-center justify-center py-2 px-1 rounded-xl bg-gray-900 border border-gray-700 text-gray-300 font-semibold cursor-pointer transition text-center hover:border-emerald-500" onclick="selectPayMethod('TRANSFER')">
                            <input type="radio" name="pos_pay" value="TRANSFER" class="hidden"> TRF
                        </label>
                    </div>
                </div>

                <!-- Cash Quick Nominals (Shown when CASH is active) -->
                <div id="cash-nominals-container" class="grid grid-cols-4 gap-1 text-[11px] font-mono">
                    <button onclick="setCashNominal('exact')" class="py-1 rounded bg-gray-800 hover:bg-gray-700 text-gray-300">Uang Pas</button>
                    <button onclick="setCashNominal(50000)" class="py-1 rounded bg-gray-800 hover:bg-gray-700 text-gray-300">50.000</button>
                    <button onclick="setCashNominal(100000)" class="py-1 rounded bg-gray-800 hover:bg-gray-700 text-gray-300">100.000</button>
                    <button onclick="setCashNominal(200000)" class="py-1 rounded bg-gray-800 hover:bg-gray-700 text-gray-300">200.000</button>
                </div>

                <!-- Cash Tender Input & Change -->
                <div id="cash-tender-container" class="flex items-center justify-between text-xs bg-gray-950 p-2.5 rounded-xl border border-gray-800">
                    <div class="flex items-center space-x-2">
                        <span class="text-gray-400">Dibayar:</span>
                        <input type="number" id="cash-tendered" placeholder="0" class="w-24 bg-gray-900 border border-gray-700 rounded px-2 py-1 text-xs text-white font-mono" oninput="calculateChange()">
                    </div>
                    <div class="text-right">
                        <span class="text-[10px] text-gray-400 block">Kembalian:</span>
                        <span id="cash-change" class="font-bold text-emerald-400 font-mono">Rp 0</span>
                    </div>
                </div>

                <!-- Checkout Action Button -->
                <button id="pos-checkout-btn" onclick="executeCheckout()" class="w-full py-3.5 bg-emerald-500 hover:bg-emerald-400 active:scale-[0.98] text-black font-extrabold rounded-2xl text-sm transition flex items-center justify-center space-x-2 shadow-lg shadow-emerald-500/25">
                    <span>⚡ BAYAR & CETAK STRUK</span>
                </button>
            </div>
        </section>
    </main>

    <!-- Modal: Thermal Receipt Preview -->
    <div id="receipt-modal" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center hidden p-4">
        <div class="bg-gray-900 border border-gray-800 rounded-2xl max-w-sm w-full p-6 text-gray-100 shadow-2xl relative">
            <button onclick="closeReceiptModal()" class="absolute top-4 right-4 text-gray-400 hover:text-white text-lg">✕</button>
            
            <div id="receipt-modal-content" class="bg-white text-black p-5 rounded-xl font-mono text-xs shadow-inner">
                <!-- Receipt dynamically inserted here -->
            </div>

            <div class="mt-5 flex space-x-2">
                <button onclick="window.print()" class="flex-1 py-2.5 bg-emerald-500 hover:bg-emerald-400 text-black font-bold rounded-xl text-xs flex items-center justify-center space-x-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                    </svg>
                    <span>Cetak Struk</span>
                </button>
                <button onclick="closeReceiptModal()" class="px-4 py-2.5 bg-gray-800 hover:bg-gray-700 text-gray-300 font-bold rounded-xl text-xs">
                    Tutup
                </button>
            </div>
        </div>
    </div>

    <!-- POS Frontend Logic -->
    <script>
        let cart = [];
        let selectedPayMethod = 'CASH';
        let currentTotal = 0;

        // Clock Live Ticker
        setInterval(() => {
            const now = new Date();
            document.getElementById('pos-clock').textContent = now.toLocaleTimeString('id-ID');
        }, 1000);

        // Keyboard Shortcut: '/' focuses search
        document.addEventListener('keydown', (e) => {
            if (e.key === '/' && document.activeElement.tagName !== 'INPUT') {
                e.preventDefault();
                document.getElementById('pos-search-input').focus();
            }
        });

        // Add Item to Cart
        function addToCart(id, sku, name, price, maxStock) {
            const existing = cart.find(item => item.id === id);
            if (existing) {
                if (existing.qty < maxStock) {
                    existing.qty += 1;
                } else {
                    alert(`Stok SKU ${sku} tidak mencukupi (maksimal: ${maxStock} unit)`);
                    return;
                }
            } else {
                cart.push({ id, sku, name, price, qty: 1, maxStock });
            }
            renderCart();
        }

        // Update Qty
        function updateQty(id, delta) {
            const item = cart.find(i => i.id === id);
            if (!item) return;

            item.qty += delta;
            if (item.qty <= 0) {
                cart = cart.filter(i => i.id !== id);
            } else if (item.qty > item.maxStock) {
                item.qty = item.maxStock;
                alert(`Maksimal stok tersedia adalah ${item.maxStock}`);
            }
            renderCart();
        }

        function clearCart() {
            cart = [];
            renderCart();
        }

        // Render Cart HTML & Totals
        function renderCart() {
            const container = document.getElementById('cart-items-container');
            const countElem = document.getElementById('cart-item-count');
            const subtotalElem = document.getElementById('pos-subtotal');
            const grandTotalElem = document.getElementById('pos-grand-total');

            const totalItems = cart.reduce((sum, item) => sum + item.qty, 0);
            currentTotal = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);

            countElem.textContent = `${totalItems} item`;
            subtotalElem.textContent = `Rp ${currentTotal.toLocaleString('id-ID')}`;
            grandTotalElem.textContent = `Rp ${currentTotal.toLocaleString('id-ID')}`;

            if (cart.length === 0) {
                container.innerHTML = `
                    <div class="h-full flex flex-col items-center justify-center text-center py-16 text-gray-500">
                        <svg class="w-12 h-12 text-gray-600 mb-2 stroke-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                        <p class="text-xs">Keranjang masih kosong</p>
                        <p class="text-[11px] text-gray-600 mt-0.5">Pilih suku cadang dari daftar di sebelah kiri</p>
                    </div>
                `;
                calculateChange();
                return;
            }

            container.innerHTML = cart.map(item => `
                <div class="bg-gray-900/90 border border-gray-800 p-3 rounded-xl flex items-center justify-between">
                    <div class="flex-1 pr-2">
                        <div class="text-xs font-bold text-white line-clamp-1">${escapeHtml(item.name)}</div>
                        <div class="text-[10px] font-mono text-gray-400 mt-0.5">${item.sku} • Rp ${item.price.toLocaleString('id-ID')}</div>
                    </div>

                    <div class="flex items-center space-x-2">
                        <div class="flex items-center bg-gray-800 rounded-lg p-0.5 border border-gray-700">
                            <button onclick="updateQty(${item.id}, -1)" class="w-6 h-6 flex items-center justify-center text-gray-300 hover:text-white font-bold text-xs">-</button>
                            <span class="w-7 text-center text-xs font-bold font-mono text-emerald-400">${item.qty}</span>
                            <button onclick="updateQty(${item.id}, 1)" class="w-6 h-6 flex items-center justify-center text-gray-300 hover:text-white font-bold text-xs">+</button>
                        </div>
                        <span class="text-xs font-bold font-mono text-gray-200 w-20 text-right">
                            Rp ${(item.price * item.qty).toLocaleString('id-ID')}
                        </span>
                    </div>
                </div>
            `).join('');

            calculateChange();
        }

        // Filter Category Pills
        function filterCategory(catId) {
            document.querySelectorAll('.cat-pill').forEach(btn => {
                btn.classList.remove('active', 'bg-emerald-500', 'text-black');
                btn.classList.add('bg-gray-900', 'text-gray-400');
            });
            event.target.classList.add('active', 'bg-emerald-500', 'text-black');
            event.target.classList.remove('bg-gray-900', 'text-gray-400');

            document.querySelectorAll('.product-card').forEach(card => {
                const cardCat = card.getAttribute('data-category');
                if (!catId || cardCat === catId) {
                    card.classList.remove('hidden');
                } else {
                    card.classList.add('hidden');
                }
            });
        }

        // Live Search Input Filter
        function filterProducts() {
            const query = document.getElementById('pos-search-input').value.toLowerCase().trim();
            document.querySelectorAll('.product-card').forEach(card => {
                const name = card.getAttribute('data-name').toLowerCase();
                const sku = card.getAttribute('data-sku').toLowerCase();
                if (name.includes(query) || sku.includes(query)) {
                    card.classList.remove('hidden');
                } else {
                    card.classList.add('hidden');
                }
            });
        }

        // Select Payment Method
        function selectPayMethod(method) {
            selectedPayMethod = method;
            document.querySelectorAll('.pay-method-btn').forEach(btn => {
                btn.classList.remove('bg-emerald-500', 'text-black');
                btn.classList.add('bg-gray-900', 'text-gray-300');
            });
            event.currentTarget.classList.add('bg-emerald-500', 'text-black');
            event.currentTarget.classList.remove('bg-gray-900', 'text-gray-300');

            const cashNominals = document.getElementById('cash-nominals-container');
            const cashTender = document.getElementById('cash-tender-container');

            if (method === 'CASH') {
                cashNominals.classList.remove('hidden');
                cashTender.classList.remove('hidden');
            } else {
                cashNominals.classList.add('hidden');
                cashTender.classList.add('hidden');
            }
        }

        function setCashNominal(val) {
            const input = document.getElementById('cash-tendered');
            if (val === 'exact') {
                input.value = currentTotal;
            } else {
                input.value = val;
            }
            calculateChange();
        }

        function calculateChange() {
            const tendered = parseFloat(document.getElementById('cash-tendered').value) || 0;
            const changeElem = document.getElementById('cash-change');
            const change = Math.max(0, tendered - currentTotal);
            changeElem.textContent = `Rp ${change.toLocaleString('id-ID')}`;
        }

        // Execute POS Checkout
        async function executeCheckout() {
            if (cart.length === 0) {
                alert('Keranjang belanja masih kosong!');
                return;
            }

            const customerName = document.getElementById('pos-customer-input').value.trim() || 'Walk-In Customer';
            const btn = document.getElementById('pos-checkout-btn');

            btn.disabled = true;
            btn.innerHTML = '<span>Memproses Pembayaran...</span>';

            try {
                // Authenticate staff cashier
                const authRes = await fetch('/api/v1/auth/login', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ email: 'staff@motovault.test', password: 'password' })
                });
                const authJson = await authRes.json();
                const token = authJson.data?.token;

                const payload = {
                    customer_name: customerName,
                    payment_method: selectedPayMethod,
                    items: cart.map(i => ({ sku: i.sku, quantity: i.qty }))
                };

                const orderRes = await fetch('/api/v1/pos/orders', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'Authorization': `Bearer ${token}`
                    },
                    body: JSON.stringify(payload)
                });
                const orderJson = await orderRes.json();

                if (!orderJson.success) {
                    alert('Gagal: ' + orderJson.message);
                    return;
                }

                const ord = orderJson.data;
                showThermalReceipt(ord, customerName);
                clearCart();
            } catch (err) {
                alert('Error transaksi: ' + err.message);
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<span>⚡ BAYAR & CETAK STRUK</span>';
            }
        }

        // Show Thermal Receipt Modal
        function showThermalReceipt(order, custName) {
            const modal = document.getElementById('receipt-modal');
            const content = document.getElementById('receipt-modal-content');

            const tendered = parseFloat(document.getElementById('cash-tendered').value) || order.total_amount;
            const change = Math.max(0, tendered - order.total_amount);

            content.innerHTML = `
                <div style="text-align: center; border-bottom: 1px dashed black; padding-bottom: 8px;">
                    <div style="font-size: 14px; font-weight: bold;">MOTOVAULT STORE SCBD</div>
                    <div style="font-size: 10px;">Jl. SCBD Senopati No. 88, Jakarta Selatan</div>
                    <div style="font-size: 10px;">Telp: (021) 555-MOTO</div>
                </div>

                <div style="padding: 8px 0; font-size: 10px; border-bottom: 1px dashed black;">
                    <div>No. Resi : ${order.order_number}</div>
                    <div>Tanggal  : ${new Date().toLocaleString('id-ID')}</div>
                    <div>Kasir    : Store Staff</div>
                    <div>Customer : ${custName}</div>
                    <div>Metode   : ${order.payment_method || selectedPayMethod}</div>
                </div>

                <div style="padding: 8px 0; border-bottom: 1px dashed black;">
                    ${order.items.map(item => `
                        <div style="display: flex; justify-content: space-between; font-size: 10px; margin-bottom: 4px;">
                            <span>${item.product_name} x${item.quantity}</span>
                            <span>Rp ${Number(item.subtotal).toLocaleString('id-ID')}</span>
                        </div>
                    `).join('')}
                </div>

                <div style="padding: 8px 0; font-size: 11px;">
                    <div style="display: flex; justify-content: space-between; font-weight: bold;">
                        <span>TOTAL :</span>
                        <span>Rp ${Number(order.total_amount).toLocaleString('id-ID')}</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 10px;">
                        <span>BAYAR :</span>
                        <span>Rp ${Number(tendered).toLocaleString('id-ID')}</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 10px;">
                        <span>KEMBALI :</span>
                        <span>Rp ${Number(change).toLocaleString('id-ID')}</span>
                    </div>
                </div>

                <div style="text-align: center; font-size: 9px; border-top: 1px dashed black; padding-top: 8px; margin-top: 6px;">
                    *** TERIMA KASIH ***<br>
                    Garansi Resmi MotoVault Indonesia<br>
                    Barang yang dibeli tidak dapat ditukar tanpa struk asli.
                </div>
            `;

            modal.classList.remove('hidden');
        }

        function closeReceiptModal() {
            document.getElementById('receipt-modal').classList.add('hidden');
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.innerText = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>

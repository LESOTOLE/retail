<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MotoVault POS — Modern Cashier & Direct Billing Terminal</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Pusher JS & Laravel Echo CDN for Reverb WebSockets -->
    <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.16.1/dist/echo.iife.js"></script>
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

            <button onclick="openExportModal()" class="flex items-center space-x-1.5 px-2.5 py-1.5 rounded-xl bg-gray-900 border border-gray-800 text-gray-300 hover:text-emerald-400 hover:border-emerald-500/40 text-xs font-semibold transition cursor-pointer" title="Unduh Rekapitulasi Penjualan">
                <svg class="w-3.5 h-3.5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <span class="hidden sm:inline">Laporan Penjualan</span>
            </button>

            <a href="/admin" target="_blank" class="flex items-center space-x-1.5 px-2.5 py-1.5 rounded-xl bg-gray-900 border border-gray-800 text-gray-300 hover:text-cyan-400 hover:border-cyan-500/40 text-xs font-semibold transition cursor-pointer" title="Buka Admin Console">
                <svg class="w-3.5 h-3.5 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <span class="hidden sm:inline">Admin</span>
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

        <!-- Right: Real-time Indicator, Audio Toggle, Notifications, Cashier Info & Live Clock -->
        <div class="flex items-center space-x-3 sm:space-x-4">
            <!-- Connection Status Badge -->
            <div id="ws-status-badge" class="hidden sm:flex items-center space-x-1.5 px-2.5 py-1 rounded-lg text-[10px] font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20" title="Status Koneksi Real-Time">
                <span id="ws-status-dot" class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse"></span>
                <span id="ws-status-text">CONNECTING</span>
            </div>

            <!-- Audio Chime Toggle Button -->
            <button id="btn-toggle-sound" onclick="SoundFx.toggleMute()" class="p-2 rounded-xl bg-gray-900 border border-gray-800 text-gray-400 hover:text-white hover:border-gray-700 transition" title="Aktif/Bisu Suara Notifikasi">
                <svg id="icon-sound-on" class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/>
                </svg>
                <svg id="icon-sound-off" class="w-4 h-4 text-gray-500 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z m11.414-2.586l4.242 4.242m0-4.242l-4.242 4.242"/>
                </svg>
            </button>

            <!-- Notification Center Bell & Dropdown -->
            <div class="relative">
                <button id="btn-notif-center" onclick="toggleNotificationDropdown()" class="relative p-2 rounded-xl bg-gray-900 border border-gray-800 text-gray-400 hover:text-white hover:border-gray-700 transition" title="Pusat Notifikasi">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                    <span id="notif-badge" class="hidden absolute -top-1 -right-1 bg-red-500 text-white text-[9px] font-extrabold rounded-full h-4 w-4 flex items-center justify-center animate-pulse">0</span>
                </button>

                <!-- Notification Dropdown Panel -->
                <div id="notif-dropdown" class="hidden absolute right-0 mt-2 w-80 sm:w-96 bg-gray-900 border border-gray-800 rounded-2xl shadow-2xl z-50 overflow-hidden backdrop-blur-xl">
                    <div class="p-3 border-b border-gray-800 flex items-center justify-between bg-gray-950/60">
                        <div class="flex items-center space-x-2">
                            <span class="text-xs font-bold text-white">Notifikasi Real-Time</span>
                            <span id="notif-count-pill" class="text-[10px] px-1.5 py-0.5 rounded bg-emerald-500/20 text-emerald-400 font-mono">0 Baru</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <button onclick="SoundFx.playChime()" class="text-[10px] text-gray-400 hover:text-emerald-400 transition" title="Tes Bunyi Chime">🔔 Tes</button>
                            <button onclick="clearNotifications()" class="text-[10px] text-emerald-400 hover:underline">Tandai Dibaca</button>
                        </div>
                    </div>
                    <div id="notif-items-list" class="max-h-72 overflow-y-auto divide-y divide-gray-800/60 text-xs">
                        <div class="p-6 text-center text-gray-500">Belum ada notifikasi baru</div>
                    </div>
                </div>
            </div>

            <!-- Clock -->
            <div class="hidden lg:flex flex-col text-right">
                <span id="pos-clock" class="text-xs font-bold text-gray-200 font-mono">15:40:00</span>
                <span class="text-[10px] text-gray-500">Cabang: <b>Jakarta Selatan Hub</b></span>
            </div>

            <!-- Cashier Pill -->
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

    <!-- Modal: Export Sales Report -->
    <div id="export-modal" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center hidden p-4">
        <div class="bg-gray-900 border border-gray-800 rounded-3xl max-w-lg w-full p-6 text-gray-100 shadow-2xl relative">
            <button onclick="closeExportModal()" class="absolute top-5 right-5 text-gray-400 hover:text-white text-lg">✕</button>
            
            <div class="flex items-center space-x-3 mb-4">
                <div class="w-10 h-10 rounded-2xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center font-bold text-lg">
                    📊
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-white">Ekspor Rekapitulasi Penjualan</h3>
                    <p class="text-xs text-gray-400">Unduh data omset dan pembukuan CSV/Excel untuk akuntansi.</p>
                </div>
            </div>

            <div class="space-y-4">
                <!-- Date Filter -->
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-semibold text-gray-400 mb-1">Dari Tanggal</label>
                        <input type="date" id="export-start-date" onchange="fetchReportSummaryPreview()" class="w-full bg-gray-950 border border-gray-800 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-emerald-500">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-gray-400 mb-1">Sampai Tanggal</label>
                        <input type="date" id="export-end-date" onchange="fetchReportSummaryPreview()" class="w-full bg-gray-950 border border-gray-800 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-emerald-500">
                    </div>
                </div>

                <!-- Warehouse Filter -->
                <div>
                    <label class="block text-[11px] font-semibold text-gray-400 mb-1">Filter Cabang Gudang</label>
                    <select id="export-warehouse-id" onchange="fetchReportSummaryPreview()" class="w-full bg-gray-950 border border-gray-800 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-emerald-500">
                        <option value="">Semua Cabang Gudang</option>
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}">{{ $wh->name }} ({{ $wh->code }})</option>
                        @endforeach
                    </select>
                </div>

                <!-- Status Filter -->
                <div>
                    <label class="block text-[11px] font-semibold text-gray-400 mb-1">Status Pembayaran</label>
                    <select id="export-payment-status" onchange="fetchReportSummaryPreview()" class="w-full bg-gray-950 border border-gray-800 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-emerald-500">
                        <option value="">Semua Status</option>
                        <option value="paid" selected>Hanya Yang Lunas (Paid)</option>
                        <option value="unpaid">Belum Lunas (Unpaid)</option>
                    </select>
                </div>

                <!-- Preview Omset Box -->
                <div id="export-summary-box" class="p-3.5 bg-gray-950/80 border border-gray-800/80 rounded-2xl flex items-center justify-between text-xs">
                    <div>
                        <span class="text-[10px] text-gray-500 block">Total Omset Terpilih:</span>
                        <span id="export-summary-omset" class="font-extrabold text-emerald-400 font-mono text-sm">Memuat ringkasan...</span>
                    </div>
                    <div class="text-right">
                        <span class="text-[10px] text-gray-500 block">Jumlah Transaksi:</span>
                        <span id="export-summary-count" class="font-bold text-white font-mono">0 Pesanan</span>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex space-x-3">
                <button onclick="downloadSalesCsv()" class="flex-1 py-3 bg-emerald-500 hover:bg-emerald-400 text-black font-extrabold rounded-2xl text-xs flex items-center justify-center space-x-2 transition shadow-lg shadow-emerald-500/25 cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    <span>Download CSV / Excel</span>
                </button>
                <button onclick="closeExportModal()" class="px-5 py-3 bg-gray-800 hover:bg-gray-700 text-gray-300 font-bold rounded-2xl text-xs transition cursor-pointer">
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
                // Check for auth token
                const token = localStorage.getItem('motovault_admin_token') || localStorage.getItem('motovault_pos_token');
                
                if (!token) {
                    alert('Silakan login terlebih dahulu sebagai Staff/Admin untuk menggunakan POS.');
                    window.location.href = '/';
                    return;
                }

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

        // ==========================================
        // SALES REPORT EXPORT MODAL LOGIC
        // ==========================================
        function openExportModal() {
            const modal = document.getElementById('export-modal');
            modal.classList.remove('hidden');
            const today = new Date().toISOString().split('T')[0];
            const monthAgo = new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
            if (!document.getElementById('export-start-date').value) {
                document.getElementById('export-start-date').value = monthAgo;
            }
            if (!document.getElementById('export-end-date').value) {
                document.getElementById('export-end-date').value = today;
            }
            fetchReportSummaryPreview();
        }

        function closeExportModal() {
            document.getElementById('export-modal').classList.add('hidden');
        }

        async function fetchReportSummaryPreview() {
            const start = document.getElementById('export-start-date').value;
            const end = document.getElementById('export-end-date').value;
            const wh = document.getElementById('export-warehouse-id').value;
            const payStatus = document.getElementById('export-payment-status').value;

            const params = new URLSearchParams();
            if (start) params.append('start_date', start);
            if (end) params.append('end_date', end);
            if (wh) params.append('warehouse_id', wh);
            if (payStatus) params.append('payment_status', payStatus);

            try {
                const res = await fetch(`/api/v1/admin/reports/sales?${params.toString()}`, {
                    headers: {
                        'Accept': 'application/json',
                        'Authorization': `Bearer ${localStorage.getItem('motovault_token') || ''}`
                    }
                });
                const json = await res.json();
                if (json.success && json.data?.summary) {
                    document.getElementById('export-summary-omset').textContent = `Rp ${Number(json.data.summary.total_omset).toLocaleString('id-ID')}`;
                    document.getElementById('export-summary-count').textContent = `${json.data.summary.total_transaksi} Pesanan (${json.data.summary.total_unit_terjual} unit)`;
                } else {
                    document.getElementById('export-summary-omset').textContent = 'Rp 0';
                    document.getElementById('export-summary-count').textContent = '0 Pesanan';
                }
            } catch (e) {
                document.getElementById('export-summary-omset').textContent = 'Rp -';
            }
        }

        function downloadSalesCsv() {
            const start = document.getElementById('export-start-date').value;
            const end = document.getElementById('export-end-date').value;
            const wh = document.getElementById('export-warehouse-id').value;
            const payStatus = document.getElementById('export-payment-status').value;

            const params = new URLSearchParams();
            if (start) params.append('start_date', start);
            if (end) params.append('end_date', end);
            if (wh) params.append('warehouse_id', wh);
            if (payStatus) params.append('payment_status', payStatus);

            window.location.href = `/pos/reports/sales/export?${params.toString()}`;
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.innerText = text;
            return div.innerHTML;
        }

        // ==========================================
        // REAL-TIME AUDIO SYNTHESIZER (Web Audio API)
        // ==========================================
        const SoundFx = {
            audioCtx: null,
            isMuted: localStorage.getItem('pos_sound_muted') === 'true',

            init() {
                if (!this.audioCtx && (window.AudioContext || window.webkitAudioContext)) {
                    const AudioCtxClass = window.AudioContext || window.webkitAudioContext;
                    this.audioCtx = new AudioCtxClass();
                }
            },

            updateMuteButton() {
                const iconOn = document.getElementById('icon-sound-on');
                const iconOff = document.getElementById('icon-sound-off');
                if (iconOn && iconOff) {
                    if (this.isMuted) {
                        iconOn.classList.add('hidden');
                        iconOff.classList.remove('hidden');
                    } else {
                        iconOn.classList.remove('hidden');
                        iconOff.classList.add('hidden');
                    }
                }
            },

            toggleMute() {
                this.isMuted = !this.isMuted;
                localStorage.setItem('pos_sound_muted', this.isMuted);
                this.updateMuteButton();
                if (!this.isMuted) {
                    this.playChime();
                }
            },

            playChime() {
                if (this.isMuted) return;
                try {
                    this.init();
                    if (this.audioCtx && this.audioCtx.state === 'suspended') {
                        this.audioCtx.resume();
                    }
                    if (!this.audioCtx) return;

                    const now = this.audioCtx.currentTime;
                    // D5 (587.33 Hz)
                    const osc1 = this.audioCtx.createOscillator();
                    const gain1 = this.audioCtx.createGain();
                    osc1.type = 'sine';
                    osc1.frequency.setValueAtTime(587.33, now);
                    gain1.gain.setValueAtTime(0.18, now);
                    gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.35);
                    osc1.connect(gain1);
                    gain1.connect(this.audioCtx.destination);
                    osc1.start(now);
                    osc1.stop(now + 0.35);

                    // A5 (880.00 Hz)
                    const osc2 = this.audioCtx.createOscillator();
                    const gain2 = this.audioCtx.createGain();
                    osc2.type = 'sine';
                    osc2.frequency.setValueAtTime(880.00, now + 0.12);
                    gain2.gain.setValueAtTime(0.22, now + 0.12);
                    gain2.gain.exponentialRampToValueAtTime(0.001, now + 0.55);
                    osc2.connect(gain2);
                    gain2.connect(this.audioCtx.destination);
                    osc2.start(now + 0.12);
                    osc2.stop(now + 0.55);
                } catch (e) {
                    // console.warn('Web Audio error:', e);
                }
            },

            playAlert() {
                if (this.isMuted) return;
                try {
                    this.init();
                    if (this.audioCtx && this.audioCtx.state === 'suspended') {
                        this.audioCtx.resume();
                    }
                    if (!this.audioCtx) return;

                    const now = this.audioCtx.currentTime;
                    const osc = this.audioCtx.createOscillator();
                    const gain = this.audioCtx.createGain();
                    osc.type = 'triangle';
                    osc.frequency.setValueAtTime(329.63, now); // E4
                    gain.gain.setValueAtTime(0.2, now);
                    gain.gain.exponentialRampToValueAtTime(0.001, now + 0.4);
                    osc.connect(gain);
                    gain.connect(this.audioCtx.destination);
                    osc.start(now);
                    osc.stop(now + 0.4);
                } catch (e) {
                    // console.warn('Web Audio error:', e);
                }
            }
        };

        // ==========================================
        // NOTIFICATION CENTER & TOAST MANAGEMENT
        // ==========================================
        let notificationsList = [];
        let seenOrderNumbers = new Set();
        let fallbackPollingInterval = null;

        function toggleNotificationDropdown() {
            const dropdown = document.getElementById('notif-dropdown');
            dropdown.classList.toggle('hidden');
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            const dropdown = document.getElementById('notif-dropdown');
            const btn = document.getElementById('btn-notif-center');
            if (dropdown && btn && !dropdown.contains(e.target) && !btn.contains(e.target)) {
                dropdown.classList.add('hidden');
            }
        });

        function addNotification(item) {
            notificationsList.unshift(item);
            if (notificationsList.length > 20) {
                notificationsList.pop();
            }
            renderNotificationCenter();
        }

        function clearNotifications() {
            notificationsList = [];
            renderNotificationCenter();
        }

        function renderNotificationCenter() {
            const badge = document.getElementById('notif-badge');
            const countPill = document.getElementById('notif-count-pill');
            const listEl = document.getElementById('notif-items-list');

            const unreadCount = notificationsList.length;
            if (unreadCount > 0) {
                badge.textContent = unreadCount > 9 ? '9+' : unreadCount;
                badge.classList.remove('hidden');
                countPill.textContent = `${unreadCount} Baru`;
            } else {
                badge.classList.add('hidden');
                countPill.textContent = '0 Baru';
            }

            if (!listEl) return;
            if (notificationsList.length === 0) {
                listEl.innerHTML = '<div class="p-6 text-center text-gray-500">Belum ada notifikasi baru</div>';
                return;
            }

            listEl.innerHTML = notificationsList.map(n => `
                <div class="p-3 hover:bg-gray-800/40 transition flex items-start space-x-3">
                    <div class="w-7 h-7 rounded-lg ${n.type === 'order' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-amber-500/20 text-amber-400'} flex items-center justify-center flex-shrink-0 mt-0.5">
                        ${n.type === 'order' ? '📦' : '⚠️'}
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-gray-200 text-xs">${escapeHtml(n.title)}</span>
                            <span class="text-[10px] text-gray-500">${n.time}</span>
                        </div>
                        <p class="text-[11px] text-gray-400 mt-0.5">${escapeHtml(n.message)}</p>
                    </div>
                </div>
            `).join('');
        }

        function showPosToast(title, message, type = 'order', actionData = null) {
            const container = document.getElementById('pos-toast-container');
            if (!container) return;

            const toast = document.createElement('div');
            toast.className = 'pointer-events-auto bg-gray-900/95 border ' + 
                (type === 'order' ? 'border-emerald-500/50 shadow-emerald-500/20' : 'border-amber-500/50 shadow-amber-500/20') + 
                ' shadow-xl rounded-2xl p-4 flex items-start space-x-3 backdrop-blur-md transform transition-all duration-300 ease-out translate-y-2 opacity-0';

            const iconSvg = type === 'order' 
                ? `<div class="w-8 h-8 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center flex-shrink-0">
                     <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                   </div>`
                : `<div class="w-8 h-8 rounded-xl bg-amber-500/20 text-amber-400 flex items-center justify-center flex-shrink-0">
                     <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                   </div>`;

            toast.innerHTML = `
                ${iconSvg}
                <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-white">${escapeHtml(title)}</span>
                        <button onclick="this.closest('.pointer-events-auto').remove()" class="text-gray-500 hover:text-gray-300 text-sm ml-2 leading-none">&times;</button>
                    </div>
                    <p class="text-[11px] text-gray-300 mt-1 leading-relaxed">${escapeHtml(message)}</p>
                </div>
            `;

            container.appendChild(toast);
            requestAnimationFrame(() => {
                toast.classList.remove('translate-y-2', 'opacity-0');
                toast.classList.add('translate-y-0', 'opacity-100');
            });

            setTimeout(() => {
                toast.classList.remove('opacity-100');
                toast.classList.add('opacity-0', 'translate-y-2');
                setTimeout(() => toast.remove(), 300);
            }, 6000);
        }

        // ==========================================
        // LARAVEL REVERB WEBSOCKET & FALLBACK ENGINE
        // ==========================================
        function updateConnectionBadge(status, modeText) {
            const badge = document.getElementById('ws-status-badge');
            const dot = document.getElementById('ws-status-dot');
            const text = document.getElementById('ws-status-text');
            if (!badge || !dot || !text) return;

            badge.classList.remove('hidden');
            if (status === 'connected') {
                badge.className = 'hidden sm:flex items-center space-x-1.5 px-2.5 py-1 rounded-lg text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/30';
                dot.className = 'w-1.5 h-1.5 rounded-full bg-emerald-400';
                text.textContent = 'WS ONLINE';
            } else if (status === 'fallback') {
                badge.className = 'hidden sm:flex items-center space-x-1.5 px-2.5 py-1 rounded-lg text-[10px] font-bold bg-amber-500/10 text-amber-400 border border-amber-500/30';
                dot.className = 'w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse';
                text.textContent = 'POLLING FALLBACK';
            } else {
                badge.className = 'hidden sm:flex items-center space-x-1.5 px-2.5 py-1 rounded-lg text-[10px] font-bold bg-gray-800 text-gray-400 border border-gray-700';
                dot.className = 'w-1.5 h-1.5 rounded-full bg-gray-400 animate-pulse';
                text.textContent = 'CONNECTING';
            }
        }

        function initRealtimeBroadcasting() {
            SoundFx.updateMuteButton();

            // 1. Coba inisialisasi Laravel Echo via Reverb
            if (typeof window.Echo !== 'undefined' || typeof Echo !== 'undefined') {
                try {
                    const EchoClass = window.Echo || Echo;
                    window.posEcho = new EchoClass({
                        broadcaster: 'reverb',
                        key: '{{ config('broadcasting.connections.reverb.key', 'motovault-key') }}',
                        wsHost: window.location.hostname || '127.0.0.1',
                        wsPort: {{ config('broadcasting.connections.reverb.options.port', 8080) }},
                        wssPort: {{ config('broadcasting.connections.reverb.options.port', 8080) }},
                        forceTLS: false,
                        enabledTransports: ['ws', 'wss'],
                    });

                    const pusher = window.posEcho.connector?.pusher;
                    if (pusher && pusher.connection) {
                        pusher.connection.bind('connected', () => {
                            updateConnectionBadge('connected');
                            if (fallbackPollingInterval) {
                                clearInterval(fallbackPollingInterval);
                                fallbackPollingInterval = null;
                            }
                        });

                        pusher.connection.bind('unavailable', () => {
                            updateConnectionBadge('fallback');
                            startFallbackPolling();
                        });

                        pusher.connection.bind('failed', () => {
                            updateConnectionBadge('fallback');
                            startFallbackPolling();
                        });
                    }

                    // Listen public order channel
                    window.posEcho.channel('orders')
                        .listen('.OrderCreated', (e) => {
                            handleOrderCreatedEvent(e);
                        })
                        .listen('.OrderStatusUpdated', (e) => {
                            handleOrderStatusUpdatedEvent(e);
                        });

                    // Listen low stock alert channel
                    window.posEcho.channel('inventory.alerts')
                        .listen('.LowStockAlert', (e) => {
                            handleLowStockAlertEvent(e);
                        });

                } catch (err) {
                    // console.warn('Reverb WebSocket init error, falling back to polling:', err);
                    updateConnectionBadge('fallback');
                    startFallbackPolling();
                }
            } else {
                updateConnectionBadge('fallback');
                startFallbackPolling();
            }

            // Jalankan polling awal untuk memuat notifikasi yang ada
            pollRecentNotifications(false);
        }

        function handleOrderCreatedEvent(e) {
            if (seenOrderNumbers.has(e.order_number)) return;
            seenOrderNumbers.add(e.order_number);

            SoundFx.playChime();
            showPosToast(
                'Pesanan Baru Masuk!',
                `No: ${e.order_number} | Rp ${Number(e.total_amount).toLocaleString('id-ID')} (${e.payment_method || 'Online'})`,
                'order',
                e
            );
            addNotification({
                type: 'order',
                title: `Order ${e.order_number}`,
                message: `Rp ${Number(e.total_amount).toLocaleString('id-ID')} - ${e.customer_name || 'Customer'}`,
                time: new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
            });
        }

        function handleOrderStatusUpdatedEvent(e) {
            showPosToast(
                'Status Order Diperbarui',
                `Order ${e.order_number} telah ${e.payment_status === 'paid' ? 'LUNAS' : e.fulfillment_status}`,
                'order'
            );
        }

        function handleLowStockAlertEvent(e) {
            SoundFx.playAlert();
            showPosToast(
                'Peringatan Stok Menipis!',
                `SKU: ${e.sku} (${e.product_name}) sisa ${e.current_stock} unit!`,
                'alert'
            );
            addNotification({
                type: 'alert',
                title: `Stok Kritis: ${e.sku}`,
                message: `${e.product_name} sisa ${e.current_stock} unit`,
                time: new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
            });
        }

        async function pollRecentNotifications(playAudio = true) {
            try {
                const res = await fetch('/api/v1/notifications/recent');
                const json = await res.json();
                if (!json.success || !json.data) return;

                const { recent_orders, low_stock_alerts } = json.data;

                // Cek pesanan baru yang belum pernah dilihat
                let newOrderFound = false;
                if (Array.isArray(recent_orders)) {
                    recent_orders.slice(0, 5).forEach(ord => {
                        if (!seenOrderNumbers.has(ord.order_number)) {
                            seenOrderNumbers.add(ord.order_number);
                            newOrderFound = true;
                            addNotification({
                                type: 'order',
                                title: `Order ${ord.order_number}`,
                                message: `Rp ${Number(ord.total_amount).toLocaleString('id-ID')} - ${ord.customer_name}`,
                                time: new Date(ord.created_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
                            });
                        }
                    });
                }

                if (newOrderFound && playAudio) {
                    SoundFx.playChime();
                    showPosToast('Pesanan Baru Terdeteksi', 'Ada transaksi online baru yang tercatat di sistem.', 'order');
                }
            } catch (err) {
                // console.warn('Fallback polling error:', err);
            }
        }

        function startFallbackPolling() {
            if (fallbackPollingInterval) return;
            fallbackPollingInterval = setInterval(() => {
                pollRecentNotifications(true);
            }, 15000);
        }

        // Auto initialize on DOM loaded
        document.addEventListener('DOMContentLoaded', () => {
            initRealtimeBroadcasting();
        });
    </script>

    <!-- Floating POS Toast Container -->
    <div id="pos-toast-container" class="fixed bottom-5 right-5 z-50 flex flex-col space-y-3 max-w-sm pointer-events-none"></div>
</body>
</html>

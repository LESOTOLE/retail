<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MotoVault Admin — Enterprise Omnichannel Back-Office</title>
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
        .tab-btn.active {
            background: rgba(16, 185, 129, 0.12);
            color: #34d399;
            border-left: 3px solid #10b981;
        }
        /* Custom scrollbar */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #090d16; }
        ::-webkit-scrollbar-thumb { background: #1f293d; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #374151; }
    </style>
</head>
<body class="bg-[#090d16] text-gray-100 min-h-screen flex flex-col antialiased selection:bg-emerald-500 selection:text-black">

    <!-- Global Toast Container -->
    <div id="toast-container" class="fixed bottom-5 right-5 z-50 flex flex-col space-y-2 pointer-events-none"></div>

    <!-- Top Navigation Bar -->
    <header class="h-16 bg-[#111827]/90 border-b border-gray-800 px-4 sm:px-6 flex items-center justify-between z-30 flex-shrink-0 sticky top-0 backdrop-blur-md">
        <div class="flex items-center space-x-4">
            <div class="flex items-center space-x-3">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-emerald-500 to-cyan-500 flex items-center justify-center font-extrabold text-white text-base shadow-lg shadow-emerald-500/20">
                    MV
                </div>
                <div>
                    <div class="flex items-center space-x-2">
                        <span class="text-base font-extrabold text-white tracking-tight">MotoVault Admin</span>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">CONSOLE v1.2</span>
                    </div>
                    <div class="text-[11px] text-gray-400 font-medium">Enterprise Omnichannel Back-Office</div>
                </div>
            </div>

            <!-- Switcher links -->
            <div class="hidden lg:flex items-center space-x-2 pl-4 border-l border-gray-800">
                <a href="/" target="_blank" class="px-2.5 py-1.5 rounded-lg text-xs font-semibold text-gray-400 hover:text-white hover:bg-gray-800 transition flex items-center space-x-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                    <span>Storefront</span>
                </a>
                <a href="/pos" target="_blank" class="px-2.5 py-1.5 rounded-lg text-xs font-semibold text-gray-400 hover:text-white hover:bg-gray-800 transition flex items-center space-x-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    <span>Kasir POS</span>
                </a>
            </div>
        </div>

        <!-- Right: Auth info, 1-Click login buttons & status -->
        <div class="flex items-center space-x-3">
            <div id="auth-guest-panel" class="flex items-center space-x-2">
                <button onclick="promptLogin('admin@motovault.test')" class="px-3 py-1.5 rounded-xl bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 text-xs font-semibold transition flex items-center space-x-1.5 cursor-pointer">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
                    <span>Login Super Admin</span>
                </button>
                <button onclick="promptLogin('staff@motovault.test')" class="px-3 py-1.5 rounded-xl bg-gray-800 hover:bg-gray-700 text-gray-300 border border-gray-700 text-xs font-semibold transition flex items-center space-x-1.5 cursor-pointer">
                    <span>Login Staff</span>
                </button>
            </div>

            <div id="auth-user-panel" class="hidden flex items-center space-x-3">
                <div class="flex items-center space-x-2 bg-gray-900 border border-gray-800 rounded-xl px-3 py-1.5">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    <div class="text-left">
                        <div id="auth-user-name" class="text-xs font-bold text-white">Admin User</div>
                        <div id="auth-user-role" class="text-[10px] font-mono text-emerald-400 uppercase tracking-wider">ADMIN</div>
                    </div>
                </div>
                <button onclick="handleLogout()" class="p-2 rounded-xl bg-gray-900 border border-gray-800 text-gray-400 hover:text-red-400 hover:border-red-500/30 transition cursor-pointer" title="Keluar">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                </button>
            </div>
        </div>
    </header>

    <!-- Main Workspace Container: Sidebar + Content -->
    <div class="flex-1 flex overflow-hidden">
        
        <!-- Sidebar Navigation -->
        <aside class="w-64 bg-[#111827]/60 border-r border-gray-800/80 flex flex-col justify-between p-3 flex-shrink-0">
            <nav class="space-y-1">
                <div class="px-3 py-2 text-[10px] font-bold text-gray-400 uppercase tracking-wider">Modul Operasional</div>
                
                <button onclick="switchTab('overview')" id="btn-tab-overview" class="tab-btn active w-full flex items-center space-x-3 px-3.5 py-2.5 rounded-xl text-xs font-semibold text-gray-300 hover:text-white hover:bg-gray-800/60 transition text-left cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                    <span>Overview</span>
                </button>

                <button onclick="switchTab('products')" id="btn-tab-products" class="tab-btn w-full flex items-center space-x-3 px-3.5 py-2.5 rounded-xl text-xs font-semibold text-gray-300 hover:text-white hover:bg-gray-800/60 transition text-left cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    <span>Katalog Produk</span>
                </button>

                <button onclick="switchTab('orders')" id="btn-tab-orders" class="tab-btn w-full flex items-center space-x-3 px-3.5 py-2.5 rounded-xl text-xs font-semibold text-gray-300 hover:text-white hover:bg-gray-800/60 transition text-left cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                    <span>Pesanan & Logistik</span>
                </button>

                <button onclick="switchTab('warehouse')" id="btn-tab-warehouse" class="tab-btn w-full flex items-center space-x-3 px-3.5 py-2.5 rounded-xl text-xs font-semibold text-gray-300 hover:text-white hover:bg-gray-800/60 transition text-left cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    <span>Pergudangan & Stok</span>
                </button>

                <div class="px-3 pt-4 pb-2 text-[10px] font-bold text-gray-400 uppercase tracking-wider">AI & Analitik</div>

                <button onclick="switchTab('ai-audit')" id="btn-tab-ai-audit" class="tab-btn w-full flex items-center space-x-3 px-3.5 py-2.5 rounded-xl text-xs font-semibold text-gray-300 hover:text-white hover:bg-gray-800/60 transition text-left cursor-pointer">
                    <svg class="w-4 h-4 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    <span>Audit Log AI</span>
                </button>

                <button onclick="switchTab('reports')" id="btn-tab-reports" class="tab-btn w-full flex items-center space-x-3 px-3.5 py-2.5 rounded-xl text-xs font-semibold text-gray-300 hover:text-white hover:bg-gray-800/60 transition text-left cursor-pointer">
                    <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    <span>Laporan Penjualan</span>
                </button>
            </nav>

            <div class="p-3 bg-gray-900/80 border border-gray-800 rounded-xl">
                <div class="text-[11px] text-gray-400">Status Database</div>
                <div class="flex items-center space-x-2 mt-1">
                    <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                    <span class="text-xs font-bold text-white font-mono">ONLINE & READY</span>
                </div>
                <div class="mt-2 text-[10px] text-gray-400">Gudang Aktif: {{ count($warehouses) }} cabang</div>
            </div>
        </aside>

        <!-- Main Content Area -->
        <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8 space-y-6">
            
            <!-- Tab 1: Overview -->
            <section id="pane-overview" class="tab-pane space-y-6">
                <!-- Welcome Banner -->
                <div class="bg-gradient-to-r from-emerald-950/40 via-gray-900 to-gray-900 border border-emerald-500/20 rounded-2xl p-6 relative overflow-hidden">
                    <div class="relative z-10">
                        <h1 class="text-xl sm:text-2xl font-extrabold text-white">Selamat Datang di MotoVault Admin Console</h1>
                        <p class="text-xs sm:text-sm text-gray-400 mt-1 max-w-2xl">
                            Kelola operasional katalog otomotif, stok multi-cabang, pemenuhan resi ekspedisi, dan audit asisten AI Gemini secara terpusat.
                        </p>
                    </div>
                </div>

                <!-- KPI Metric Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-[#111827] border border-gray-800 rounded-2xl p-4">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold text-gray-400">Total Katalog Produk</span>
                            <span class="p-2 rounded-xl bg-emerald-500/10 text-emerald-400">📦</span>
                        </div>
                        <div class="mt-3 text-2xl font-extrabold text-white font-mono">{{ $stats['products_count'] }}</div>
                        <div class="text-[11px] text-gray-400 mt-1">Master suku cadang & aksesori</div>
                    </div>

                    <div class="bg-[#111827] border border-gray-800 rounded-2xl p-4">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold text-gray-400">Total Pesanan Masuk</span>
                            <span class="p-2 rounded-xl bg-cyan-500/10 text-cyan-400">🛒</span>
                        </div>
                        <div class="mt-3 text-2xl font-extrabold text-white font-mono">{{ $stats['orders_count'] }}</div>
                        <div class="text-[11px] text-gray-400 mt-1">Storefront & POS kasir</div>
                    </div>

                    <div class="bg-[#111827] border border-gray-800 rounded-2xl p-4">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold text-gray-400">Varian Stok Rendah</span>
                            <span class="p-2 rounded-xl bg-amber-500/10 text-amber-400">⚠️</span>
                        </div>
                        <div class="mt-3 text-2xl font-extrabold text-amber-400 font-mono">{{ $stats['low_stock_count'] }}</div>
                        <div class="text-[11px] text-gray-400 mt-1">Perlu re-order ke distributor</div>
                    </div>

                    <div class="bg-[#111827] border border-gray-800 rounded-2xl p-4">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold text-gray-400">Transfer Stok Aktif</span>
                            <span class="p-2 rounded-xl bg-purple-500/10 text-purple-400">🚚</span>
                        </div>
                        <div class="mt-3 text-2xl font-extrabold text-purple-400 font-mono">{{ $stats['transfers_count'] }}</div>
                        <div class="text-[11px] text-gray-400 mt-1">Dalam perjalanan antar-gudang</div>
                    </div>
                </div>

                <!-- Recent Orders Preview -->
                <div class="bg-[#111827] border border-gray-800 rounded-2xl p-5 space-y-4">
                    <div class="flex items-center justify-between">
                        <h2 class="text-sm font-bold text-white flex items-center space-x-2">
                            <span>Aktivitas Pesanan Terbaru</span>
                        </h2>
                        <button onclick="switchTab('orders')" class="text-xs text-emerald-400 hover:underline font-semibold cursor-pointer">Lihat Semua Pesanan &rarr;</button>
                    </div>

                    <div id="overview-recent-orders-list" class="space-y-2">
                        <div class="text-center py-6 text-xs text-gray-400">Memuat pesanan terbaru...</div>
                    </div>
                </div>
            </section>

            <!-- Tab 2: Products Management -->
            <section id="pane-products" class="tab-pane hidden space-y-6">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-bold text-white">Katalog Produk & Kompatibilitas Motor</h2>
                        <p class="text-xs text-gray-400 mt-0.5">Kelola data master produk, varian SKU fisik, dan relasi motor.</p>
                    </div>
                    <button onclick="openCreateProductModal()" class="px-3.5 py-2 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-black font-bold text-xs transition flex items-center space-x-1.5 shadow-md shadow-emerald-500/20 cursor-pointer">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        <span>Tambah Produk Master</span>
                    </button>
                </div>

                <!-- Filter Bar -->
                <div class="bg-[#111827] border border-gray-800 rounded-2xl p-4 flex flex-col md:flex-row items-center gap-3">
                    <div class="relative flex-1 w-full">
                        <input type="text" id="product-search-query" placeholder="Cari nama produk, brand, atau SKU..." 
                               class="w-full bg-gray-900 border border-gray-700/80 rounded-xl pl-9 pr-4 py-2 text-xs text-gray-200 placeholder-gray-500 focus:outline-none focus:border-emerald-500 transition"
                               oninput="loadProducts()">
                        <svg class="w-4 h-4 text-gray-500 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </div>
                    <select id="product-filter-category" onchange="loadProducts()" class="w-full md:w-48 bg-gray-900 border border-gray-700/80 rounded-xl px-3 py-2 text-xs text-gray-200 focus:outline-none focus:border-emerald-500 transition">
                        <option value="">Semua Kategori</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->slug }}">{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Products Table -->
                <div class="bg-[#111827] border border-gray-800 rounded-2xl overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-gray-900/80 border-b border-gray-800 text-gray-400 font-semibold uppercase tracking-wider text-[10px]">
                                <tr>
                                    <th class="p-4">Produk</th>
                                    <th class="p-4">Kategori</th>
                                    <th class="p-4">Varian & SKU</th>
                                    <th class="p-4">Kompatibilitas Motor</th>
                                    <th class="p-4">Status</th>
                                    <th class="p-4 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="products-table-body" class="divide-y divide-gray-800/60">
                                <tr><td colspan="6" class="p-8 text-center text-gray-400">Memuat data produk...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- Tab 3: Orders & Fulfillment -->
            <section id="pane-orders" class="tab-pane hidden space-y-6">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-bold text-white">Pesanan Omnichannel & Logistik</h2>
                        <p class="text-xs text-gray-400 mt-0.5">Pemantauan pesanan pelanggan, status pembayaran, dan pengiriman resi.</p>
                    </div>
                </div>

                <!-- Filter Status Tabs -->
                <div class="flex items-center space-x-2 overflow-x-auto pb-2" id="order-status-filters">
                    <button onclick="filterOrdersStatus('')" class="order-filter-btn active px-3 py-1.5 rounded-xl text-xs font-semibold bg-emerald-500 text-black cursor-pointer">Semua</button>
                    <button onclick="filterOrdersStatus('unpaid')" class="order-filter-btn px-3 py-1.5 rounded-xl text-xs font-semibold bg-gray-900 border border-gray-800 text-gray-300 hover:text-white cursor-pointer">Unpaid</button>
                    <button onclick="filterOrdersStatus('paid')" class="order-filter-btn px-3 py-1.5 rounded-xl text-xs font-semibold bg-gray-900 border border-gray-800 text-gray-300 hover:text-white cursor-pointer">Paid</button>
                    <button onclick="filterOrdersStatus('processing')" class="order-filter-btn px-3 py-1.5 rounded-xl text-xs font-semibold bg-gray-900 border border-gray-800 text-gray-300 hover:text-white cursor-pointer">Processing</button>
                    <button onclick="filterOrdersStatus('shipped')" class="order-filter-btn px-3 py-1.5 rounded-xl text-xs font-semibold bg-gray-900 border border-gray-800 text-gray-300 hover:text-white cursor-pointer">Shipped</button>
                    <button onclick="filterOrdersStatus('delivered')" class="order-filter-btn px-3 py-1.5 rounded-xl text-xs font-semibold bg-gray-900 border border-gray-800 text-gray-300 hover:text-white cursor-pointer">Delivered</button>
                </div>

                <!-- Orders Table -->
                <div class="bg-[#111827] border border-gray-800 rounded-2xl overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-gray-900/80 border-b border-gray-800 text-gray-400 font-semibold uppercase tracking-wider text-[10px]">
                                <tr>
                                    <th class="p-4">No. Pesanan</th>
                                    <th class="p-4">Pelanggan</th>
                                    <th class="p-4">Total</th>
                                    <th class="p-4">Metode Bayar</th>
                                    <th class="p-4">Status Bayar</th>
                                    <th class="p-4">Pengiriman</th>
                                    <th class="p-4">Tanggal</th>
                                    <th class="p-4 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="orders-table-body" class="divide-y divide-gray-800/60">
                                <tr><td colspan="8" class="p-8 text-center text-gray-400">Memuat data pesanan...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- Tab 4: Warehouse & Inventory -->
            <section id="pane-warehouse" class="tab-pane hidden space-y-6">
                <div>
                    <h2 class="text-lg font-bold text-white">Pergudangan & Manajemen Stok Multi-Cabang</h2>
                    <p class="text-xs text-gray-400 mt-0.5">Pemantauan batas stok minimum dan transfer stok antar-gudang.</p>
                </div>

                <!-- Sub-tabs: Low-Stock Alerts vs Transfers -->
                <div class="flex items-center space-x-3 border-b border-gray-800 pb-3">
                    <button onclick="switchWarehouseSubTab('low-stock')" id="btn-sub-low-stock" class="px-3.5 py-2 rounded-xl text-xs font-bold bg-amber-500/20 text-amber-400 border border-amber-500/30 cursor-pointer flex items-center space-x-2">
                        <span>⚠️ Stok Menipis (Alert)</span>
                    </button>
                    <button onclick="switchWarehouseSubTab('transfers')" id="btn-sub-transfers" class="px-3.5 py-2 rounded-xl text-xs font-bold bg-gray-900 text-gray-400 border border-gray-800 hover:text-white cursor-pointer flex items-center space-x-2">
                        <span>🚚 Transfer Antar-Gudang</span>
                    </button>
                </div>

                <!-- Sub-panel: Low Stock -->
                <div id="subpane-low-stock" class="space-y-4">
                    <div class="bg-[#111827] border border-gray-800 rounded-2xl overflow-hidden">
                        <div class="p-4 border-b border-gray-800 flex items-center justify-between">
                            <span class="text-xs font-bold text-white">Daftar SKU di Bawah Batas Minimum Safety Stock</span>
                            <button onclick="loadLowStock()" class="text-xs text-emerald-400 hover:underline">Segarkan</button>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-gray-900/80 border-b border-gray-800 text-gray-400 font-semibold uppercase tracking-wider text-[10px]">
                                    <tr>
                                        <th class="p-4">SKU Barcode</th>
                                        <th class="p-4">Nama Produk & Varian</th>
                                        <th class="p-4">Kategori</th>
                                        <th class="p-4">Stok Saat Ini</th>
                                        <th class="p-4">Min. Alert</th>
                                        <th class="p-4 text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody id="low-stock-table-body" class="divide-y divide-gray-800/60">
                                    <tr><td colspan="6" class="p-8 text-center text-gray-400">Memuat data stok kritis...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Sub-panel: Stock Transfers -->
                <div id="subpane-transfers" class="hidden space-y-4">
                    <div class="flex justify-end">
                        <button onclick="openCreateTransferModal()" class="px-3.5 py-2 rounded-xl bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs transition flex items-center space-x-1.5 shadow-md shadow-purple-500/20 cursor-pointer">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            <span>Buat Permintaan Transfer Baru</span>
                        </button>
                    </div>

                    <div class="bg-[#111827] border border-gray-800 rounded-2xl overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-gray-900/80 border-b border-gray-800 text-gray-400 font-semibold uppercase tracking-wider text-[10px]">
                                    <tr>
                                        <th class="p-4">No. Transfer</th>
                                        <th class="p-4">Gudang Asal</th>
                                        <th class="p-4">Gudang Tujuan</th>
                                        <th class="p-4">Varian SKU</th>
                                        <th class="p-4">Kuantitas</th>
                                        <th class="p-4">Status</th>
                                        <th class="p-4 text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody id="transfers-table-body" class="divide-y divide-gray-800/60">
                                    <tr><td colspan="7" class="p-8 text-center text-gray-400">Memuat data transfer stok...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Tab 5: AI Audit Logs -->
            <section id="pane-ai-audit" class="tab-pane hidden space-y-6">
                <div>
                    <h2 class="text-lg font-bold text-white">Audit Log Percakapan Asisten AI Gemini</h2>
                    <p class="text-xs text-gray-400 mt-0.5">Pantau interaksi konsultasi suku cadang, tool calling loop, dan efektivitas konversi.</p>
                </div>

                <div class="bg-[#111827] border border-gray-800 rounded-2xl overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-gray-900/80 border-b border-gray-800 text-gray-400 font-semibold uppercase tracking-wider text-[10px]">
                                <tr>
                                    <th class="p-4">ID Sesi</th>
                                    <th class="p-4">Customer</th>
                                    <th class="p-4">Motor Konteks</th>
                                    <th class="p-4">Prompt Terakhir</th>
                                    <th class="p-4">Tool Calls</th>
                                    <th class="p-4">Konversi</th>
                                    <th class="p-4 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="ai-logs-table-body" class="divide-y divide-gray-800/60">
                                <tr><td colspan="7" class="p-8 text-center text-gray-400">Memuat log AI...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- Tab 6: Sales Reports -->
            <section id="pane-reports" class="tab-pane hidden space-y-6">
                <div>
                    <h2 class="text-lg font-bold text-white">Laporan Rekapitulasi Penjualan</h2>
                    <p class="text-xs text-gray-400 mt-0.5">Analisis pendapatan omset dan unduh berkas CSV terstandarisasi untuk akuntansi.</p>
                </div>

                <!-- Filter Box -->
                <div class="bg-[#111827] border border-gray-800 rounded-2xl p-5 space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Dari Tanggal</label>
                            <input type="date" id="report-date-start" class="w-full bg-gray-900 border border-gray-700 rounded-xl px-3 py-2 text-xs text-gray-200">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Sampai Tanggal</label>
                            <input type="date" id="report-date-end" class="w-full bg-gray-900 border border-gray-700 rounded-xl px-3 py-2 text-xs text-gray-200">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Gudang Cabang</label>
                            <select id="report-warehouse-id" class="w-full bg-gray-900 border border-gray-700 rounded-xl px-3 py-2 text-xs text-gray-200">
                                <option value="">Semua Gudang</option>
                                @foreach($warehouses as $w)
                                    <option value="{{ $w->id }}">{{ $w->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="flex items-center justify-between pt-2 border-t border-gray-800">
                        <button onclick="loadSalesReport()" class="px-4 py-2 rounded-xl bg-gray-800 hover:bg-gray-700 text-gray-200 text-xs font-semibold transition cursor-pointer">
                            Perbarui Tampilan
                        </button>
                        <button onclick="downloadSalesCsv()" class="px-4 py-2 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-black text-xs font-bold transition flex items-center space-x-1.5 shadow-md shadow-emerald-500/20 cursor-pointer">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            <span>Unduh Laporan CSV</span>
                        </button>
                    </div>
                </div>

                <!-- Report Summary Result Cards -->
                <div id="report-summary-cards" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="bg-[#111827] border border-gray-800 rounded-2xl p-4">
                        <span class="text-xs text-gray-400">Total Omset Bersih</span>
                        <div id="report-total-revenue" class="text-xl font-extrabold text-emerald-400 font-mono mt-1">Rp 0</div>
                    </div>
                    <div class="bg-[#111827] border border-gray-800 rounded-2xl p-4">
                        <span class="text-xs text-gray-400">Jumlah Transaksi Selesai</span>
                        <div id="report-total-orders" class="text-xl font-extrabold text-white font-mono mt-1">0</div>
                    </div>
                    <div class="bg-[#111827] border border-gray-800 rounded-2xl p-4">
                        <span class="text-xs text-gray-400">Total Unit Terjual</span>
                        <div id="report-total-items" class="text-xl font-extrabold text-cyan-400 font-mono mt-1">0</div>
                    </div>
                </div>
            </section>

        </main>
    </div>

    <!-- Modals Container -->
    <div id="modals-root">
        
        <!-- Modal Create/Edit Product -->
        <div id="modal-product" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm hidden flex items-center justify-center p-4">
            <div class="bg-[#111827] border border-gray-800 rounded-2xl max-w-lg w-full p-6 space-y-4">
                <div class="flex items-center justify-between border-b border-gray-800 pb-3">
                    <h3 id="modal-product-title" class="text-base font-bold text-white">Tambah Master Produk</h3>
                    <button onclick="closeModal('modal-product')" class="text-gray-400 hover:text-white">&times;</button>
                </div>
                <form id="product-form" onsubmit="saveProduct(event)" class="space-y-3">
                    <input type="hidden" id="form-product-id">
                    <div>
                        <label class="block text-xs font-semibold text-gray-300 mb-1">Kategori Produk</label>
                        <select id="form-product-category" required class="w-full bg-gray-900 border border-gray-700 rounded-xl px-3 py-2 text-xs text-white">
                            @foreach($categories as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-300 mb-1">Brand / Pabrikan</label>
                        <input type="text" id="form-product-brand" required placeholder="Contoh: Motul, Brembo, NGK" class="w-full bg-gray-900 border border-gray-700 rounded-xl px-3 py-2 text-xs text-white">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-300 mb-1">Nama Produk</label>
                        <input type="text" id="form-product-name" required placeholder="Contoh: Oli Mesin 7100 4T 10W-40" class="w-full bg-gray-900 border border-gray-700 rounded-xl px-3 py-2 text-xs text-white">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-300 mb-1">Deskripsi Lengkap</label>
                        <textarea id="form-product-desc" rows="3" class="w-full bg-gray-900 border border-gray-700 rounded-xl px-3 py-2 text-xs text-white"></textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-300 mb-1">URL Gambar (Opsional)</label>
                        <input type="text" id="form-product-image" placeholder="https://..." class="w-full bg-gray-900 border border-gray-700 rounded-xl px-3 py-2 text-xs text-white">
                    </div>
                    <div class="flex items-center space-x-2 pt-1">
                        <input type="checkbox" id="form-product-active" checked class="rounded bg-gray-900 border-gray-700 text-emerald-500">
                        <label for="form-product-active" class="text-xs text-gray-300 font-medium">Status Aktif & Tampil di Katalog</label>
                    </div>
                    <div class="flex justify-end space-x-2 pt-3 border-t border-gray-800">
                        <button type="button" onclick="closeModal('modal-product')" class="px-4 py-2 rounded-xl bg-gray-800 text-gray-300 text-xs font-semibold">Batal</button>
                        <button type="submit" id="btn-save-product" class="px-4 py-2 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-black text-xs font-bold">Simpan Produk</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal Add Variant -->
        <div id="modal-variant" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm hidden flex items-center justify-center p-4">
            <div class="bg-[#111827] border border-gray-800 rounded-2xl max-w-md w-full p-6 space-y-4">
                <div class="flex items-center justify-between border-b border-gray-800 pb-3">
                    <h3 class="text-base font-bold text-white">Tambah Varian Fisik Baru</h3>
                    <button onclick="closeModal('modal-variant')" class="text-gray-400 hover:text-white">&times;</button>
                </div>
                <form id="variant-form" onsubmit="saveVariant(event)" class="space-y-3">
                    <input type="hidden" id="form-variant-product-id">
                    <div>
                        <label class="block text-xs font-semibold text-gray-300 mb-1">SKU Barcode Unik</label>
                        <input type="text" id="form-variant-sku" required placeholder="Contoh: MOT-OIL-10W40-1L-001" class="w-full bg-gray-900 border border-gray-700 rounded-xl px-3 py-2 text-xs text-white font-mono">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-300 mb-1">Nama Varian / Ukuran</label>
                        <input type="text" id="form-variant-name" required placeholder="Contoh: 1 Liter, Size XL" class="w-full bg-gray-900 border border-gray-700 rounded-xl px-3 py-2 text-xs text-white">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-300 mb-1">Harga Tambahan (Rp)</label>
                        <input type="number" id="form-variant-price" required min="0" value="0" class="w-full bg-gray-900 border border-gray-700 rounded-xl px-3 py-2 text-xs text-white font-mono">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Stok Awal</label>
                            <input type="number" id="form-variant-stock" required min="0" value="10" class="w-full bg-gray-900 border border-gray-700 rounded-xl px-3 py-2 text-xs text-white font-mono">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Min Alert Stock</label>
                            <input type="number" id="form-variant-alert" required min="1" value="5" class="w-full bg-gray-900 border border-gray-700 rounded-xl px-3 py-2 text-xs text-white font-mono">
                        </div>
                    </div>
                    <div class="flex justify-end space-x-2 pt-3 border-t border-gray-800">
                        <button type="button" onclick="closeModal('modal-variant')" class="px-4 py-2 rounded-xl bg-gray-800 text-gray-300 text-xs font-semibold">Batal</button>
                        <button type="submit" class="px-4 py-2 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-black text-xs font-bold">Tambah Varian</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal Compatibility Sync -->
        <div id="modal-compat" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm hidden flex items-center justify-center p-4">
            <div class="bg-[#111827] border border-gray-800 rounded-2xl max-w-lg w-full p-6 space-y-4">
                <div class="flex items-center justify-between border-b border-gray-800 pb-3">
                    <h3 class="text-base font-bold text-white">Sinkronisasi Kompatibilitas Motor</h3>
                    <button onclick="closeModal('modal-compat')" class="text-gray-400 hover:text-white">&times;</button>
                </div>
                <form id="compat-form" onsubmit="saveCompatibility(event)" class="space-y-3">
                    <input type="hidden" id="form-compat-product-id">
                    <div>
                        <label class="block text-xs font-semibold text-gray-300 mb-1">Pilih Model Motor (Tahan Ctrl/Cmd untuk multiselect)</label>
                        <select id="form-compat-vehicles" multiple size="6" required class="w-full bg-gray-900 border border-gray-700 rounded-xl p-2 text-xs text-white">
                            @foreach($vehicles as $v)
                                <option value="{{ $v->id }}">{{ $v->brand }} {{ $v->model }} ({{ $v->year_start }}-{{ $v->year_end ?? 'Sekarang' }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-300 mb-1">Catatan Teknis Pemasangan</label>
                        <input type="text" id="form-compat-notes" value="Plug and Play" placeholder="Contoh: Plug and Play, Butuh bracket kaliper 260mm" class="w-full bg-gray-900 border border-gray-700 rounded-xl px-3 py-2 text-xs text-white">
                    </div>
                    <div class="flex justify-end space-x-2 pt-3 border-t border-gray-800">
                        <button type="button" onclick="closeModal('modal-compat')" class="px-4 py-2 rounded-xl bg-gray-800 text-gray-300 text-xs font-semibold">Batal</button>
                        <button type="submit" class="px-4 py-2 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-black text-xs font-bold">Simpan Kompatibilitas</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal Order Detail -->
        <div id="modal-order-detail" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm hidden flex items-center justify-center p-4">
            <div class="bg-[#111827] border border-gray-800 rounded-2xl max-w-2xl w-full p-6 space-y-4 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between border-b border-gray-800 pb-3">
                    <div>
                        <h3 id="modal-order-number" class="text-base font-bold text-white font-mono">ORD-...</h3>
                        <div id="modal-order-date" class="text-xs text-gray-400">...</div>
                    </div>
                    <button onclick="closeModal('modal-order-detail')" class="text-gray-400 hover:text-white">&times;</button>
                </div>
                <div id="modal-order-content" class="space-y-4">
                    <!-- Dynamic details -->
                </div>
                <div class="flex justify-end pt-3 border-t border-gray-800">
                    <button type="button" onclick="closeModal('modal-order-detail')" class="px-4 py-2 rounded-xl bg-gray-800 text-gray-300 text-xs font-semibold">Tutup</button>
                </div>
            </div>
        </div>

        <!-- Modal Fulfill / Input Tracking Resi -->
        <div id="modal-fulfill" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm hidden flex items-center justify-center p-4">
            <div class="bg-[#111827] border border-gray-800 rounded-2xl max-w-md w-full p-6 space-y-4">
                <div class="flex items-center justify-between border-b border-gray-800 pb-3">
                    <h3 class="text-base font-bold text-white">Input Nomor Resi Ekspedisi</h3>
                    <button onclick="closeModal('modal-fulfill')" class="text-gray-400 hover:text-white">&times;</button>
                </div>
                <form id="fulfill-form" onsubmit="saveFulfill(event)" class="space-y-3">
                    <input type="hidden" id="form-fulfill-order-number">
                    <div>
                        <label class="block text-xs font-semibold text-gray-300 mb-1">Nomor Resi / AWB Ekspedisi</label>
                        <input type="text" id="form-fulfill-tracking" required placeholder="Contoh: JNE8899220011, JNT998822" class="w-full bg-gray-900 border border-gray-700 rounded-xl px-3 py-2 text-xs text-white font-mono">
                    </div>
                    <div class="flex justify-end space-x-2 pt-3 border-t border-gray-800">
                        <button type="button" onclick="closeModal('modal-fulfill')" class="px-4 py-2 rounded-xl bg-gray-800 text-gray-300 text-xs font-semibold">Batal</button>
                        <button type="submit" class="px-4 py-2 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-black text-xs font-bold">Kirim Resi & Dispatch</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal Stock Adjustment -->
        <div id="modal-stock-adjust" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm hidden flex items-center justify-center p-4">
            <div class="bg-[#111827] border border-gray-800 rounded-2xl max-w-md w-full p-6 space-y-4">
                <div class="flex items-center justify-between border-b border-gray-800 pb-3">
                    <h3 class="text-base font-bold text-white">Penyesuaian Stok Varian (Opname)</h3>
                    <button onclick="closeModal('modal-stock-adjust')" class="text-gray-400 hover:text-white">&times;</button>
                </div>
                <form id="stock-adjust-form" onsubmit="saveStockAdjust(event)" class="space-y-3">
                    <input type="hidden" id="form-adjust-variant-id">
                    <div>
                        <div id="adjust-variant-name" class="text-xs font-semibold text-white">...</div>
                        <div id="adjust-variant-sku" class="text-[11px] font-mono text-gray-400">...</div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-300 mb-1">Kuantitas Stok Nyata Baru</label>
                        <input type="number" id="form-adjust-stock" required min="0" class="w-full bg-gray-900 border border-gray-700 rounded-xl px-3 py-2 text-xs text-white font-mono">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-300 mb-1">Alasan Penyesuaian</label>
                        <input type="text" id="form-adjust-reason" value="Stok opname rutin" class="w-full bg-gray-900 border border-gray-700 rounded-xl px-3 py-2 text-xs text-white">
                    </div>
                    <div class="flex justify-end space-x-2 pt-3 border-t border-gray-800">
                        <button type="button" onclick="closeModal('modal-stock-adjust')" class="px-4 py-2 rounded-xl bg-gray-800 text-gray-300 text-xs font-semibold">Batal</button>
                        <button type="submit" class="px-4 py-2 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-black text-xs font-bold">Simpan Stok</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal Create Stock Transfer -->
        <div id="modal-create-transfer" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm hidden flex items-center justify-center p-4">
            <div class="bg-[#111827] border border-gray-800 rounded-2xl max-w-md w-full p-6 space-y-4">
                <div class="flex items-center justify-between border-b border-gray-800 pb-3">
                    <h3 class="text-base font-bold text-white">Buat Permintaan Transfer Antar-Gudang</h3>
                    <button onclick="closeModal('modal-create-transfer')" class="text-gray-400 hover:text-white">&times;</button>
                </div>
                <form id="transfer-form" onsubmit="saveCreateTransfer(event)" class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-300 mb-1">Gudang Asal (Pengirim)</label>
                        <select id="form-transfer-from" required class="w-full bg-gray-900 border border-gray-700 rounded-xl px-3 py-2 text-xs text-white">
                            @foreach($warehouses as $w)
                                <option value="{{ $w->id }}">{{ $w->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-300 mb-1">Gudang Tujuan (Penerima)</label>
                        <select id="form-transfer-to" required class="w-full bg-gray-900 border border-gray-700 rounded-xl px-3 py-2 text-xs text-white">
                            @foreach($warehouses as $w)
                                <option value="{{ $w->id }}">{{ $w->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-300 mb-1">Pilih SKU Varian</label>
                        <select id="form-transfer-variant" required class="w-full bg-gray-900 border border-gray-700 rounded-xl px-3 py-2 text-xs text-white">
                            <option value="">Memuat varian...</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-300 mb-1">Kuantitas Unit</label>
                        <input type="number" id="form-transfer-qty" required min="1" value="5" class="w-full bg-gray-900 border border-gray-700 rounded-xl px-3 py-2 text-xs text-white font-mono">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-300 mb-1">Catatan Pengiriman</label>
                        <input type="text" id="form-transfer-notes" placeholder="Restock mingguan" class="w-full bg-gray-900 border border-gray-700 rounded-xl px-3 py-2 text-xs text-white">
                    </div>
                    <div class="flex justify-end space-x-2 pt-3 border-t border-gray-800">
                        <button type="button" onclick="closeModal('modal-create-transfer')" class="px-4 py-2 rounded-xl bg-gray-800 text-gray-300 text-xs font-semibold">Batal</button>
                        <button type="submit" class="px-4 py-2 rounded-xl bg-purple-600 hover:bg-purple-500 text-white text-xs font-bold">Buat Transfer</button>
                    </div>
                </form>
            </div>
        </div>

    </div>

    <!-- Client-Side Logic & API Wrapper -->
    <script>
        // Global state
        let currentUser = null;
        let currentToken = localStorage.getItem('motovault_admin_token') || null;
        let activeTab = 'overview';
        let activeWarehouseSubTab = 'low-stock';
        let currentOrderStatusFilter = '';

        // Toast Helper
        function showToast(msg, type = 'success') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            const colors = {
                success: 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40',
                error: 'bg-red-500/20 text-red-300 border-red-500/40',
                warning: 'bg-amber-500/20 text-amber-300 border-amber-500/40',
            };
            toast.className = `p-3 rounded-xl text-xs font-semibold border backdrop-blur-md shadow-2xl flex items-center space-x-2 transition-all duration-300 transform translate-y-2 ${colors[type] || colors.success}`;
            toast.innerHTML = `<span>${type === 'success' ? '✓' : '⚠️'}</span><span>${msg}</span>`;
            container.appendChild(toast);
            setTimeout(() => { toast.classList.remove('translate-y-2'); }, 10);
            setTimeout(() => {
                toast.classList.add('opacity-0', 'translate-y-2');
                setTimeout(() => toast.remove(), 300);
            }, 3500);
        }

        // Modal Helpers
        function openModal(id) {
            const el = document.getElementById(id);
            if (el) el.classList.remove('hidden');
        }
        function closeModal(id) {
            const el = document.getElementById(id);
            if (el) el.classList.add('hidden');
        }

        // API Client
        const AdminApi = {
            async request(endpoint, options = {}) {
                if (!currentToken) {
                    window.location.href = '/';
                    throw new Error('Unauthorized');
                }
                const headers = {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${currentToken}`,
                    ...(options.headers || {})
                };
                try {
                    const res = await fetch(endpoint, { ...options, headers });
                    if (res.status === 401) {
                        localStorage.removeItem('motovault_admin_token');
                        currentToken = null;
                        updateAuthUI();
                        showToast('Sesi telah berakhir, silakan login kembali.', 'warning');
                        throw new Error('Unauthorized');
                    }
                    const data = await res.json();
                    if (!res.ok) {
                        const errMessage = data.message || 'Terjadi kesalahan sistem';
                        throw new Error(errMessage);
                    }
                    return data;
                } catch (err) {
                    console.error('API Error:', err);
                    throw err;
                }
            }
        };

        // Authentication Handlers
        async function loginAs(email, password, silent = false) {
            try {
                const res = await fetch('/api/v1/auth/login', {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email, password })
                });
                const data = await res.json();
                if (res.ok && data.data?.token) {
                    currentToken = data.data.token;
                    currentUser = data.data.user;
                    localStorage.setItem('motovault_admin_token', currentToken);
                    localStorage.setItem('motovault_admin_user', JSON.stringify(currentUser));
                    updateAuthUI();
                    if (!silent) showToast(`Login berhasil sebagai ${currentUser.name}!`);
                    refreshCurrentTab();
                } else {
                    if (!silent) showToast(data.message || 'Login gagal', 'error');
                }
            } catch (err) {
                if (!silent) showToast('Gagal menghubungi server autentikasi', 'error');
            }
        }

        function promptLogin(defaultEmail) {
            const email = prompt('Email akun Admin / Staff:', defaultEmail || '');
            if (!email) return;
            const password = prompt('Password akun:');
            if (!password) return;
            loginAs(email.trim(), password);
        }

        async function handleLogout() {
            try {
                if (currentToken) {
                    await fetch('/api/v1/auth/logout', {
                        method: 'POST',
                        headers: { 'Accept': 'application/json', 'Authorization': `Bearer ${currentToken}` }
                    });
                }
            } catch (e) {}
            localStorage.removeItem('motovault_admin_token');
            localStorage.removeItem('motovault_admin_user');
            currentToken = null;
            currentUser = null;
            updateAuthUI();
            showToast('Anda telah keluar.');
        }

        function updateAuthUI() {
            const guestPanel = document.getElementById('auth-guest-panel');
            const userPanel = document.getElementById('auth-user-panel');
            if (currentToken && currentUser) {
                guestPanel.classList.add('hidden');
                userPanel.classList.remove('hidden');
                document.getElementById('auth-user-name').textContent = currentUser.name;
                document.getElementById('auth-user-role').textContent = currentUser.role || 'STAFF';
            } else {
                guestPanel.classList.remove('hidden');
                userPanel.classList.add('hidden');
            }
        }

        // Tab Navigation
        function switchTab(tabId) {
            activeTab = tabId;
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-pane').forEach(p => p.classList.add('hidden'));

            const btn = document.getElementById(`btn-tab-${tabId}`);
            const pane = document.getElementById(`pane-tab-${tabId}`) || document.getElementById(`pane-${tabId}`);
            if (btn) btn.classList.add('active');
            if (pane) pane.classList.remove('hidden');

            refreshCurrentTab();
        }

        function refreshCurrentTab() {
            if (activeTab === 'overview') loadOverviewOrders();
            if (activeTab === 'products') loadProducts();
            if (activeTab === 'orders') loadOrders();
            if (activeTab === 'warehouse') {
                if (activeWarehouseSubTab === 'low-stock') loadLowStock();
                else loadStockTransfers();
            }
            if (activeTab === 'ai-audit') loadAiLogs();
            if (activeTab === 'reports') loadSalesReport();
        }

        function switchWarehouseSubTab(sub) {
            activeWarehouseSubTab = sub;
            const btnLow = document.getElementById('btn-sub-low-stock');
            const btnTrans = document.getElementById('btn-sub-transfers');
            const paneLow = document.getElementById('subpane-low-stock');
            const paneTrans = document.getElementById('subpane-transfers');

            if (sub === 'low-stock') {
                btnLow.className = 'px-3.5 py-2 rounded-xl text-xs font-bold bg-amber-500/20 text-amber-400 border border-amber-500/30 cursor-pointer flex items-center space-x-2';
                btnTrans.className = 'px-3.5 py-2 rounded-xl text-xs font-bold bg-gray-900 text-gray-400 border border-gray-800 hover:text-white cursor-pointer flex items-center space-x-2';
                paneLow.classList.remove('hidden');
                paneTrans.classList.add('hidden');
                loadLowStock();
            } else {
                btnTrans.className = 'px-3.5 py-2 rounded-xl text-xs font-bold bg-purple-500/20 text-purple-400 border border-purple-500/30 cursor-pointer flex items-center space-x-2';
                btnLow.className = 'px-3.5 py-2 rounded-xl text-xs font-bold bg-gray-900 text-gray-400 border border-gray-800 hover:text-white cursor-pointer flex items-center space-x-2';
                paneTrans.classList.remove('hidden');
                paneLow.classList.add('hidden');
                loadStockTransfers();
            }
        }

        // TAB 1: Overview
        async function loadOverviewOrders() {
            const container = document.getElementById('overview-recent-orders-list');
            try {
                const res = await AdminApi.request('/api/v1/admin/orders');
                const orders = (res.data || []).slice(0, 5);
                if (orders.length === 0) {
                    container.innerHTML = '<div class="text-center py-6 text-xs text-gray-500">Belum ada pesanan masuk.</div>';
                    return;
                }
                container.innerHTML = orders.map(o => `
                    <div class="flex items-center justify-between p-3 bg-gray-900/60 border border-gray-800/80 rounded-xl hover:border-gray-700 transition">
                        <div class="flex items-center space-x-3">
                            <span class="w-8 h-8 rounded-lg bg-emerald-500/10 text-emerald-400 flex items-center justify-center font-mono text-xs font-bold">#</span>
                            <div>
                                <div class="text-xs font-bold text-white font-mono">${o.order_number}</div>
                                <div class="text-[11px] text-gray-400">${o.customer?.name || 'Walk-In Customer'} &bull; ${o.items?.length || 1} item</div>
                            </div>
                        </div>
                        <div class="flex items-center space-x-3">
                            <div class="text-right">
                                <div class="text-xs font-bold text-emerald-400 font-mono">Rp ${(o.total_amount || 0).toLocaleString('id-ID')}</div>
                                <div class="text-[10px] text-gray-500">${o.payment_method || 'CASH'}</div>
                            </div>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold ${o.payment_status === 'paid' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-amber-500/20 text-amber-400'} uppercase font-mono">${o.payment_status}</span>
                        </div>
                    </div>
                `).join('');
            } catch (err) {
                container.innerHTML = `<div class="text-center py-6 text-xs text-red-400">Gagal memuat pesanan: ${err.message}</div>`;
            }
        }

        // TAB 2: Products
        async function loadProducts() {
            const tbody = document.getElementById('products-table-body');
            const search = document.getElementById('product-search-query').value.trim();
            const cat = document.getElementById('product-filter-category').value;
            let url = '/api/v1/products?limit=50';
            if (search) url += `&search=${encodeURIComponent(search)}`;
            if (cat) url += `&category=${encodeURIComponent(cat)}`;

            try {
                const res = await AdminApi.request(url);
                const products = res.data?.data || res.data || [];
                if (products.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6" class="p-8 text-center text-gray-500">Tidak ada produk ditemukan.</td></tr>';
                    return;
                }
                tbody.innerHTML = products.map(p => `
                    <tr class="hover:bg-gray-800/30 transition">
                        <td class="p-4">
                            <div class="font-bold text-white">${p.name}</div>
                            <div class="text-[11px] text-gray-400">${p.brand || 'Brand Otomotif'}</div>
                        </td>
                        <td class="p-4 text-gray-300">${p.category?.name || '-'}</td>
                        <td class="p-4">
                            <div class="space-y-1">
                                ${(p.variants || []).map(v => `
                                    <div class="text-[11px] flex items-center space-x-2">
                                        <span class="font-mono text-emerald-400">${v.sku}</span>
                                        <span class="text-gray-400">(${v.name}):</span>
                                        <span class="font-bold text-white">${v.stock} unit</span>
                                    </div>
                                `).join('') || '<span class="text-gray-500">Belum ada varian</span>'}
                            </div>
                        </td>
                        <td class="p-4">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-cyan-500/10 text-cyan-400 border border-cyan-500/20">
                                ${(p.compatible_vehicles || p.compatibleVehicles || []).length} Motor Pas
                            </span>
                        </td>
                        <td class="p-4">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold ${p.is_active ? 'bg-emerald-500/20 text-emerald-400' : 'bg-gray-800 text-gray-400'}">
                                ${p.is_active ? 'AKTIF' : 'NONAKTIF'}
                            </span>
                        </td>
                        <td class="p-4 text-right space-x-1">
                            <button onclick="openAddVariantModal(${p.id})" class="px-2 py-1 rounded bg-gray-800 hover:bg-gray-700 text-emerald-400 text-[11px] font-semibold cursor-pointer" title="Tambah Varian SKU">+ Varian</button>
                            <button onclick="openCompatModal(${p.id})" class="px-2 py-1 rounded bg-gray-800 hover:bg-gray-700 text-cyan-400 text-[11px] font-semibold cursor-pointer" title="Kelola Kompatibilitas Motor">Motor</button>
                            <button onclick="deleteProduct(${p.id})" class="px-2 py-1 rounded bg-gray-800 hover:bg-red-900/40 text-red-400 text-[11px] font-semibold cursor-pointer" title="Hapus Produk">Hapus</button>
                        </td>
                    </tr>
                `).join('');
            } catch (err) {
                tbody.innerHTML = `<tr><td colspan="6" class="p-8 text-center text-red-400">Gagal memuat produk: ${err.message}</td></tr>`;
            }
        }

        function openCreateProductModal() {
            document.getElementById('form-product-id').value = '';
            document.getElementById('product-form').reset();
            document.getElementById('modal-product-title').textContent = 'Tambah Master Produk Baru';
            openModal('modal-product');
        }

        async function saveProduct(e) {
            e.preventDefault();
            const id = document.getElementById('form-product-id').value;
            const payload = {
                category_id: document.getElementById('form-product-category').value,
                brand: document.getElementById('form-product-brand').value,
                name: document.getElementById('form-product-name').value,
                description: document.getElementById('form-product-desc').value,
                image_url: document.getElementById('form-product-image').value,
                is_active: document.getElementById('form-product-active').checked
            };

            try {
                if (id) {
                    await AdminApi.request(`/api/v1/admin/products/${id}`, { method: 'PUT', body: JSON.stringify(payload) });
                    showToast('Produk berhasil diperbarui!');
                } else {
                    await AdminApi.request('/api/v1/admin/products', { method: 'POST', body: JSON.stringify(payload) });
                    showToast('Produk master berhasil ditambahkan!');
                }
                closeModal('modal-product');
                loadProducts();
            } catch (err) {
                showToast(err.message, 'error');
            }
        }

        function openAddVariantModal(productId) {
            document.getElementById('form-variant-product-id').value = productId;
            document.getElementById('variant-form').reset();
            openModal('modal-variant');
        }

        async function saveVariant(e) {
            e.preventDefault();
            const productId = document.getElementById('form-variant-product-id').value;
            const payload = {
                sku: document.getElementById('form-variant-sku').value,
                name: document.getElementById('form-variant-name').value,
                additional_price: parseFloat(document.getElementById('form-variant-price').value) || 0,
                stock: parseInt(document.getElementById('form-variant-stock').value) || 0,
                min_stock_alert: parseInt(document.getElementById('form-variant-alert').value) || 5,
            };

            try {
                await AdminApi.request(`/api/v1/admin/products/${productId}/variants`, {
                    method: 'POST',
                    body: JSON.stringify(payload)
                });
                showToast('Varian SKU baru berhasil disimpan!');
                closeModal('modal-variant');
                loadProducts();
            } catch (err) {
                showToast(err.message, 'error');
            }
        }

        function openCompatModal(productId) {
            document.getElementById('form-compat-product-id').value = productId;
            openModal('modal-compat');
        }

        async function saveCompatibility(e) {
            e.preventDefault();
            const productId = document.getElementById('form-compat-product-id').value;
            const select = document.getElementById('form-compat-vehicles');
            const selectedVehicles = Array.from(select.selectedOptions).map(opt => parseInt(opt.value));
            const notes = document.getElementById('form-compat-notes').value;

            if (selectedVehicles.length === 0) {
                showToast('Pilih minimal satu motor!', 'warning');
                return;
            }

            try {
                await AdminApi.request(`/api/v1/admin/products/${productId}/compatibility`, {
                    method: 'POST',
                    body: JSON.stringify({ vehicle_ids: selectedVehicles, notes })
                });
                showToast('Kompatibilitas motor berhasil disinkronkan!');
                closeModal('modal-compat');
                loadProducts();
            } catch (err) {
                showToast(err.message, 'error');
            }
        }

        async function deleteProduct(productId) {
            if (!confirm('Apakah Anda yakin ingin menghapus produk ini?')) return;
            try {
                await AdminApi.request(`/api/v1/admin/products/${productId}`, { method: 'DELETE' });
                showToast('Produk telah dinonaktifkan/dihapus.');
                loadProducts();
            } catch (err) {
                showToast(err.message, 'error');
            }
        }

        // TAB 3: Orders
        function filterOrdersStatus(status) {
            currentOrderStatusFilter = status;
            document.querySelectorAll('.order-filter-btn').forEach(b => {
                b.className = 'order-filter-btn px-3 py-1.5 rounded-xl text-xs font-semibold bg-gray-900 border border-gray-800 text-gray-300 hover:text-white cursor-pointer';
            });
            event.currentTarget.className = 'order-filter-btn active px-3 py-1.5 rounded-xl text-xs font-semibold bg-emerald-500 text-black cursor-pointer';
            loadOrders();
        }

        async function loadOrders() {
            const tbody = document.getElementById('orders-table-body');
            let url = '/api/v1/admin/orders';
            if (currentOrderStatusFilter) url += `?status=${currentOrderStatusFilter}`;

            try {
                const res = await AdminApi.request(url);
                const orders = res.data || [];
                if (orders.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="8" class="p-8 text-center text-gray-500">Tidak ada pesanan pada status ini.</td></tr>';
                    return;
                }
                tbody.innerHTML = orders.map(o => `
                    <tr class="hover:bg-gray-800/30 transition">
                        <td class="p-4 font-mono font-bold text-white">${o.order_number}</td>
                        <td class="p-4 text-gray-300">${o.customer?.name || 'Walk-In'}</td>
                        <td class="p-4 font-mono font-bold text-emerald-400">Rp ${(o.total_amount || 0).toLocaleString('id-ID')}</td>
                        <td class="p-4 text-gray-400 font-mono text-[11px]">${o.payment_method || 'CASH'}</td>
                        <td class="p-4">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold ${o.payment_status === 'paid' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-amber-500/20 text-amber-400'} font-mono uppercase">
                                ${o.payment_status}
                            </span>
                        </td>
                        <td class="p-4">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold ${o.fulfillment_status === 'delivered' ? 'bg-blue-500/20 text-blue-400' : (o.fulfillment_status === 'shipped' ? 'bg-purple-500/20 text-purple-400' : 'bg-gray-800 text-gray-400')} font-mono uppercase">
                                ${o.fulfillment_status}
                            </span>
                        </td>
                        <td class="p-4 text-gray-500 text-[11px]">${(o.created_at || '').substring(0, 10)}</td>
                        <td class="p-4 text-right space-x-1">
                            <button onclick="viewOrderDetail('${o.order_number}')" class="px-2 py-1 rounded bg-gray-800 hover:bg-gray-700 text-gray-300 text-[11px] font-semibold cursor-pointer">Detail</button>
                            ${o.fulfillment_status === 'processing' ? `
                                <button onclick="openFulfillModal('${o.order_number}')" class="px-2 py-1 rounded bg-purple-900/40 hover:bg-purple-800/60 text-purple-300 text-[11px] font-semibold cursor-pointer">Input Resi</button>
                                <button onclick="generateAwb('${o.order_number}')" class="px-2 py-1 rounded bg-emerald-900/40 hover:bg-emerald-800/60 text-emerald-300 text-[11px] font-semibold cursor-pointer">Auto AWB</button>
                            ` : ''}
                        </td>
                    </tr>
                `).join('');
            } catch (err) {
                tbody.innerHTML = `<tr><td colspan="8" class="p-8 text-center text-red-400">Gagal memuat pesanan: ${err.message}</td></tr>`;
            }
        }

        async function viewOrderDetail(orderNumber) {
            try {
                const res = await AdminApi.request(`/api/v1/orders/${orderNumber}`);
                const o = res.data;
                document.getElementById('modal-order-number').textContent = o.order_number;
                document.getElementById('modal-order-date').textContent = `Dibuat pada ${o.created_at || '-'}`;
                
                const content = document.getElementById('modal-order-content');
                content.innerHTML = `
                    <div class="grid grid-cols-2 gap-4 bg-gray-900/60 p-3 rounded-xl text-xs">
                        <div>
                            <span class="text-gray-400">Pelanggan:</span>
                            <div class="font-bold text-white">${o.customer?.name || 'Walk-In'} (${o.customer?.phone || '-'})</div>
                        </div>
                        <div>
                            <span class="text-gray-400">Alamat Kirim:</span>
                            <div class="font-bold text-white">${o.shipping_order?.address || 'Ambil di Toko / POS'}</div>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <div class="text-xs font-bold text-gray-300">Rincian Item Belanja:</div>
                        ${(o.items || []).map(i => `
                            <div class="flex items-center justify-between p-2.5 bg-gray-900 rounded-xl text-xs">
                                <div>
                                    <div class="font-bold text-white">${i.product_name || i.variant?.name}</div>
                                    <div class="text-[11px] text-gray-400 font-mono">${i.sku} &bull; Qty: ${i.quantity}</div>
                                </div>
                                <div class="font-mono font-bold text-emerald-400">Rp ${(i.subtotal || (i.price * i.quantity)).toLocaleString('id-ID')}</div>
                            </div>
                        `).join('')}
                    </div>

                    <div class="border-t border-gray-800 pt-3 flex items-center justify-between font-mono">
                        <span class="text-xs font-bold text-gray-400">TOTAL AKHIR:</span>
                        <span class="text-base font-extrabold text-emerald-400">Rp ${(o.total_amount || 0).toLocaleString('id-ID')}</span>
                    </div>
                `;
                openModal('modal-order-detail');
            } catch (err) {
                showToast(err.message, 'error');
            }
        }

        function openFulfillModal(orderNumber) {
            document.getElementById('form-fulfill-order-number').value = orderNumber;
            document.getElementById('fulfill-form').reset();
            openModal('modal-fulfill');
        }

        async function saveFulfill(e) {
            e.preventDefault();
            const orderNumber = document.getElementById('form-fulfill-order-number').value;
            const trackingNumber = document.getElementById('form-fulfill-tracking').value;

            try {
                await AdminApi.request(`/api/v1/admin/orders/${orderNumber}/fulfill`, {
                    method: 'PATCH',
                    body: JSON.stringify({ tracking_number: trackingNumber })
                });
                showToast(`Pesanan ${orderNumber} berhasil dikirim dengan resi ${trackingNumber}!`);
                closeModal('modal-fulfill');
                loadOrders();
            } catch (err) {
                showToast(err.message, 'error');
            }
        }

        async function generateAwb(orderNumber) {
            try {
                const res = await AdminApi.request('/api/v1/admin/shipping/create-awb', {
                    method: 'POST',
                    body: JSON.stringify({ order_number: orderNumber })
                });
                showToast(`Resi otomatis AWB ${res.data?.tracking_number} berhasil dibuat!`);
                loadOrders();
            } catch (err) {
                showToast(err.message, 'error');
            }
        }

        // TAB 4: Warehouse & Low Stock
        async function loadLowStock() {
            const tbody = document.getElementById('low-stock-table-body');
            try {
                const res = await AdminApi.request('/api/v1/admin/variants/low-stock');
                const variants = res.data || [];
                if (variants.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6" class="p-8 text-center text-emerald-400">Semua stok varian dalam kondisi aman (di atas batas minimum).</td></tr>';
                    return;
                }
                tbody.innerHTML = variants.map(v => `
                    <tr class="hover:bg-gray-800/30 transition">
                        <td class="p-4 font-mono text-amber-400 font-bold">${v.sku}</td>
                        <td class="p-4">
                            <div class="font-bold text-white">${v.product?.name || '-'}</div>
                            <div class="text-[11px] text-gray-400">${v.name}</div>
                        </td>
                        <td class="p-4 text-gray-300">${v.product?.category?.name || '-'}</td>
                        <td class="p-4 font-mono font-bold text-red-400">${v.stock} unit</td>
                        <td class="p-4 font-mono text-gray-400">${v.min_stock_alert} unit</td>
                        <td class="p-4 text-right">
                            <button onclick="openStockAdjustModal(${v.id}, '${v.name}', '${v.sku}', ${v.stock})" class="px-2.5 py-1 rounded bg-amber-500/20 hover:bg-amber-500/30 text-amber-300 font-semibold text-xs cursor-pointer">
                                Sesuaikan Stok
                            </button>
                        </td>
                    </tr>
                `).join('');
            } catch (err) {
                tbody.innerHTML = `<tr><td colspan="6" class="p-8 text-center text-red-400">Gagal memuat stok: ${err.message}</td></tr>`;
            }
        }

        function openStockAdjustModal(id, name, sku, currentStock) {
            document.getElementById('form-adjust-variant-id').value = id;
            document.getElementById('adjust-variant-name').textContent = name;
            document.getElementById('adjust-variant-sku').textContent = sku;
            document.getElementById('form-adjust-stock').value = currentStock;
            openModal('modal-stock-adjust');
        }

        async function saveStockAdjust(e) {
            e.preventDefault();
            const id = document.getElementById('form-adjust-variant-id').value;
            const stock = parseInt(document.getElementById('form-adjust-stock').value);
            const reason = document.getElementById('form-adjust-reason').value;

            try {
                await AdminApi.request(`/api/v1/admin/variants/${id}/stock`, {
                    method: 'PUT',
                    body: JSON.stringify({ stock, reason })
                });
                showToast('Stok varian berhasil disesuaikan!');
                closeModal('modal-stock-adjust');
                loadLowStock();
            } catch (err) {
                showToast(err.message, 'error');
            }
        }

        // TAB 4 Part B: Stock Transfers
        async function loadStockTransfers() {
            const tbody = document.getElementById('transfers-table-body');
            try {
                const res = await AdminApi.request('/api/v1/admin/warehouses/transfers');
                const transfers = res.data || [];
                if (transfers.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="7" class="p-8 text-center text-gray-500">Belum ada aktivitas transfer antar-gudang.</td></tr>';
                    return;
                }
                tbody.innerHTML = transfers.map(t => `
                    <tr class="hover:bg-gray-800/30 transition">
                        <td class="p-4 font-mono font-bold text-white">${t.transfer_number}</td>
                        <td class="p-4 text-gray-300">${t.from_warehouse?.name || '-'}</td>
                        <td class="p-4 text-gray-300">${t.to_warehouse?.name || '-'}</td>
                        <td class="p-4 font-mono text-emerald-400">${t.variant?.sku || '-'}</td>
                        <td class="p-4 font-mono font-bold text-white">${t.quantity} unit</td>
                        <td class="p-4">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold ${t.status === 'received' ? 'bg-emerald-500/20 text-emerald-400' : (t.status === 'dispatched' ? 'bg-purple-500/20 text-purple-400' : 'bg-gray-800 text-gray-400')} uppercase font-mono">
                                ${t.status}
                            </span>
                        </td>
                        <td class="p-4 text-right space-x-1">
                            ${t.status === 'draft' ? `
                                <button onclick="updateTransferStatus(${t.id}, 'dispatched')" class="px-2 py-1 rounded bg-purple-900/40 text-purple-300 text-[11px] font-semibold cursor-pointer">Dispatch</button>
                            ` : ''}
                            ${t.status === 'dispatched' ? `
                                <button onclick="updateTransferStatus(${t.id}, 'received')" class="px-2 py-1 rounded bg-emerald-900/40 text-emerald-300 text-[11px] font-semibold cursor-pointer">Receive</button>
                                <button onclick="updateTransferStatus(${t.id}, 'cancelled')" class="px-2 py-1 rounded bg-red-900/40 text-red-300 text-[11px] font-semibold cursor-pointer">Batal</button>
                            ` : ''}
                        </td>
                    </tr>
                `).join('');
            } catch (err) {
                tbody.innerHTML = `<tr><td colspan="7" class="p-8 text-center text-red-400">Gagal memuat transfer: ${err.message}</td></tr>`;
            }
        }

        async function openCreateTransferModal() {
            // Load variants for select
            const select = document.getElementById('form-transfer-variant');
            try {
                const res = await AdminApi.request('/api/v1/products?limit=100');
                const products = res.data?.data || res.data || [];
                const variants = [];
                products.forEach(p => {
                    (p.variants || []).forEach(v => {
                        variants.push({ id: v.id, label: `${p.name} - ${v.name} (${v.sku})` });
                    });
                });
                select.innerHTML = variants.map(v => `<option value="${v.id}">${v.label}</option>`).join('');
                openModal('modal-create-transfer');
            } catch (e) {
                showToast('Gagal memuat daftar varian produk', 'error');
            }
        }

        async function saveCreateTransfer(e) {
            e.preventDefault();
            const fromId = document.getElementById('form-transfer-from').value;
            const toId = document.getElementById('form-transfer-to').value;
            if (fromId === toId) {
                showToast('Gudang asal dan tujuan tidak boleh sama!', 'warning');
                return;
            }
            const payload = {
                from_warehouse_id: fromId,
                to_warehouse_id: toId,
                product_variant_id: document.getElementById('form-transfer-variant').value,
                quantity: parseInt(document.getElementById('form-transfer-qty').value),
                notes: document.getElementById('form-transfer-notes').value
            };

            try {
                await AdminApi.request('/api/v1/admin/warehouses/transfers', {
                    method: 'POST',
                    body: JSON.stringify(payload)
                });
                showToast('Permintaan transfer stok berhasil dibuat!');
                closeModal('modal-create-transfer');
                loadStockTransfers();
            } catch (err) {
                showToast(err.message, 'error');
            }
        }

        async function updateTransferStatus(transferId, status) {
            try {
                await AdminApi.request(`/api/v1/admin/warehouses/transfers/${transferId}/status`, {
                    method: 'PATCH',
                    body: JSON.stringify({ status })
                });
                showToast(`Status transfer berhasil diubah ke ${status}!`);
                loadStockTransfers();
            } catch (err) {
                showToast(err.message, 'error');
            }
        }

        // TAB 5: AI Logs
        async function loadAiLogs() {
            const tbody = document.getElementById('ai-logs-table-body');
            try {
                const res = await AdminApi.request('/api/v1/admin/ai/logs');
                const logs = res.data || [];
                if (logs.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="7" class="p-8 text-center text-gray-500">Belum ada sesi percakapan AI tercatat.</td></tr>';
                    return;
                }
                tbody.innerHTML = logs.map(l => `
                    <tr class="hover:bg-gray-800/30 transition">
                        <td class="p-4 font-mono font-bold text-cyan-400 text-xs">#${l.id || '-'}</td>
                        <td class="p-4 text-gray-300">${l.user?.name || 'Guest Pembeli'}</td>
                        <td class="p-4 text-gray-400 text-[11px]">${l.last_context_vehicle?.model || 'Universal'}</td>
                        <td class="p-4 text-white text-xs max-w-xs truncate">${l.latest_prompt || l.messages?.[0]?.content || '-'}</td>
                        <td class="p-4 font-mono text-xs text-purple-400">${l.tool_calls_count || (l.tool_calls?.length || 0)} calls</td>
                        <td class="p-4">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold ${l.has_order ? 'bg-emerald-500/20 text-emerald-400' : 'bg-gray-800 text-gray-400'} font-mono uppercase">
                                ${l.has_order ? 'CHECKOUT' : 'KONSULTASI'}
                            </span>
                        </td>
                        <td class="p-4 text-right">
                            <button onclick="console.log('Log ID:', ${l.id}, 'Detail Tool Calls:', ${JSON.stringify(l.tool_calls || [])})" class="px-2 py-1 rounded bg-gray-800 hover:bg-gray-700 text-xs text-gray-300 cursor-pointer">Inspeksi (Console)</button>
                        </td>
                    </tr>
                `).join('');
            } catch (err) {
                tbody.innerHTML = `<tr><td colspan="7" class="p-8 text-center text-red-400">Gagal memuat log AI: ${err.message}</td></tr>`;
            }
        }

        // TAB 6: Sales Reports
        async function loadSalesReport() {
            const start = document.getElementById('report-date-start').value;
            const end = document.getElementById('report-date-end').value;
            const warehouse = document.getElementById('report-warehouse-id').value;

            let url = '/api/v1/admin/reports/sales?';
            if (start) url += `&start_date=${start}`;
            if (end) url += `&end_date=${end}`;
            if (warehouse) url += `&warehouse_id=${warehouse}`;

            try {
                const res = await AdminApi.request(url);
                const d = res.data || {};
                document.getElementById('report-total-revenue').textContent = `Rp ${(d.total_revenue || 0).toLocaleString('id-ID')}`;
                document.getElementById('report-total-orders').textContent = (d.total_orders || 0).toLocaleString('id-ID');
                document.getElementById('report-total-items').textContent = (d.total_items_sold || 0).toLocaleString('id-ID');
            } catch (err) {
                showToast('Gagal memuat laporan penjualan', 'error');
            }
        }

        function downloadSalesCsv() {
            const start = document.getElementById('report-date-start').value;
            const end = document.getElementById('report-date-end').value;
            const warehouse = document.getElementById('report-warehouse-id').value;

            let url = '/api/v1/admin/reports/sales/export?';
            if (currentToken) url += `token=${currentToken}&`;
            if (start) url += `&start_date=${start}`;
            if (end) url += `&end_date=${end}`;
            if (warehouse) url += `&warehouse_id=${warehouse}`;

            window.open(url, '_blank');
        }

        // On Page Load Initialization
        window.addEventListener('DOMContentLoaded', () => {
            const storedUser = localStorage.getItem('motovault_admin_user');
            if (storedUser) {
                try { currentUser = JSON.parse(storedUser); } catch(e) {}
            }
            updateAuthUI();
            switchTab('overview');
        });
    </script>
</body>
</html>

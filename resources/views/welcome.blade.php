<!DOCTYPE html>
<html lang="id" class="dark scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MotoVault — Enterprise Smart Automotive Parts & Omnichannel Platform</title>
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
                            dark: '#070b12',
                            card: '#0e1524',
                            surface: '#131d31',
                            border: '#1e2c47',
                            accent: '#f59e0b',
                            crimson: '#ef4444'
                        }
                    }
                }
            }
        }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        code, pre, .font-mono { font-family: 'JetBrains Mono', monospace; }
        .glass-panel {
            background: rgba(14, 21, 36, 0.7);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.07);
        }
        .glow-emerald {
            box-shadow: 0 0 40px -10px rgba(16, 185, 129, 0.25);
        }
    </style>

    <!-- Alpine.js CDN -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.5/dist/cdn.min.js"></script>
    <script>
        document.addEventListener('alpine:init', () => {
            // --- AUTH STORE ---
            Alpine.store('auth', {
                token: localStorage.getItem('motovault_token') || null,
                user: JSON.parse(localStorage.getItem('motovault_user') || 'null'),
                isAuthModalOpen: false,
                authTab: 'login', // 'login' or 'register'
                authError: '',
                isLoading: false,

                get isAuthenticated() {
                    return !!this.token;
                },

                setAuth(token, user) {
                    this.token = token;
                    this.user = user;
                    localStorage.setItem('motovault_token', token);
                    localStorage.setItem('motovault_user', JSON.stringify(user));
                },

                logout() {
                    this.token = null;
                    this.user = null;
                    localStorage.removeItem('motovault_token');
                    localStorage.removeItem('motovault_user');
                }
            });

            // --- GARAGE STORE ---
            Alpine.store('garage', {
                selectedVehicleId: localStorage.getItem('motovault_garage_id') || '',
                selectedVehicleName: localStorage.getItem('motovault_garage_name') || '',

                setVehicle(id, name) {
                    this.selectedVehicleId = id;
                    this.selectedVehicleName = name;
                    if (id) {
                        localStorage.setItem('motovault_garage_id', id);
                        localStorage.setItem('motovault_garage_name', name);
                    } else {
                        localStorage.removeItem('motovault_garage_id');
                        localStorage.removeItem('motovault_garage_name');
                    }
                },

                clearVehicle() {
                    this.setVehicle('', '');
                }
            });

            // --- CART STORE ---
            Alpine.store('cart', {
                items: JSON.parse(localStorage.getItem('motovault_cart') || '[]'),
                isDrawerOpen: false,
                isCheckoutOpen: false,
                isOrderTrackerOpen: false,
                checkoutStep: 1,
                isCheckingOut: false,
                checkoutError: '',
                checkoutSuccessData: null,

                // Shipping selection
                postalCode: localStorage.getItem('motovault_postal') || '12190',
                recipientName: '',
                recipientPhone: '',
                recipientEmail: '',
                recipientAddress: '',
                shippingRates: [],
                selectedCourier: null,
                isLoadingRates: false,
                paymentMethod: 'qris',

                get count() {
                    return this.items.reduce((sum, item) => sum + item.quantity, 0);
                },

                get subtotal() {
                    return this.items.reduce((sum, item) => sum + (item.price * item.quantity), 0);
                },

                get shippingCost() {
                    return this.selectedCourier ? Number(this.selectedCourier.cost) : 0;
                },

                get totalAmount() {
                    return this.subtotal + this.shippingCost;
                },

                save() {
                    localStorage.setItem('motovault_cart', JSON.stringify(this.items));
                    if (this.postalCode) localStorage.setItem('motovault_postal', this.postalCode);
                },

                addItem(variant) {
                    const existing = this.items.find(i => i.sku === variant.sku);
                    if (existing) {
                        if (existing.quantity < variant.stock) {
                            existing.quantity++;
                        } else {
                            alert('Maksimal stok tercapai untuk varian ini.');
                            return;
                        }
                    } else {
                        this.items.push({
                            variant_id: variant.id,
                            sku: variant.sku,
                            name: variant.name,
                            variant_name: variant.variant_name,
                            price: Number(variant.price),
                            stock: variant.stock,
                            quantity: 1,
                            image_url: variant.image_url || null,
                            weight_gram: variant.weight_gram || 500
                        });
                    }
                    this.save();
                    this.isDrawerOpen = true;
                },

                updateQty(sku, qty) {
                    const item = this.items.find(i => i.sku === sku);
                    if (!item) return;
                    const newQty = parseInt(qty);
                    if (newQty <= 0) {
                        this.removeItem(sku);
                    } else if (newQty <= item.stock) {
                        item.quantity = newQty;
                        this.save();
                    } else {
                        item.quantity = item.stock;
                        this.save();
                        alert(`Stok hanya tersedia ${item.stock} pcs.`);
                    }
                },

                removeItem(sku) {
                    this.items = this.items.filter(i => i.sku !== sku);
                    this.save();
                },

                clearCart() {
                    this.items = [];
                    this.save();
                }
            });
        });
    </script>
</head>
<body class="bg-[#070b12] text-gray-100 min-h-screen antialiased selection:bg-emerald-500 selection:text-black">

    <!-- Top Navigation Header -->
    <header class="sticky top-0 z-50 glass-panel border-b border-gray-800/80">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-18 flex items-center justify-between">
            <!-- Brand Logo -->
            <div class="flex items-center space-x-3.5">
                <div class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-emerald-500 via-teal-400 to-cyan-500 flex items-center justify-center shadow-lg shadow-emerald-500/25">
                    <svg class="w-5 h-5 text-black font-extrabold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                </div>
                <div>
                    <div class="flex items-center space-x-2">
                        <span class="text-xl font-extrabold tracking-tight text-white">MotoVault</span>
                        <span class="text-[10px] uppercase px-2 py-0.5 rounded-full font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/30">Enterprise v2.0</span>
                    </div>
                    <span class="text-[11px] text-gray-400 font-medium">Smart Automotive Parts & Logistics</span>
                </div>
            </div>

            <!-- Center Navigation Links -->
            <nav class="hidden md:flex items-center space-x-6 text-xs font-semibold text-gray-300">
                <a href="#ai-diagnostic" class="hover:text-emerald-400 transition">AI Diagnosa RAG</a>
                <a href="#katalog-produk" class="hover:text-emerald-400 transition">Katalog Terverifikasi</a>
                <a href="#logistik-3pl" class="hover:text-emerald-400 transition">3PL Cek Ongkir & Resi</a>
                <a href="#gudang-cabang" class="hover:text-emerald-400 transition">Routing Cabang</a>
                <a href="#api-reference" class="hover:text-emerald-400 transition">REST API Docs</a>
            </nav>

            <!-- Actions: Separate POS Cashier Button -->
            <div class="flex items-center space-x-3">
                <a href="/pos" target="_blank" class="px-4 py-2 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-black text-xs font-extrabold shadow-lg shadow-emerald-500/20 transition flex items-center space-x-1.5 active:scale-95">
                    <span>💳 Buka Terminal POS Kasir</span>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                    </svg>
                </a>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="relative pt-12 pb-16 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto overflow-hidden">
        <div class="text-center max-w-4xl mx-auto">
            <div class="inline-flex items-center space-x-2 px-3.5 py-1.5 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-xs text-emerald-400 font-semibold mb-6">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping mr-1"></span>
                <span>Platform Cerdas Suku Cadang & Logistik Multikanal Otomotif</span>
            </div>

            <h1 class="text-4xl sm:text-6xl font-extrabold text-white tracking-tight leading-[1.15]">
                Presisi Kompatibilitas Motor.<br>
                <span class="bg-gradient-to-r from-emerald-400 via-teal-300 to-cyan-400 bg-clip-text text-transparent">
                    Didukung AI Diagnostik & Logistik 3PL.
                </span>
            </h1>

            <p class="mt-5 text-gray-400 text-sm sm:text-base max-w-2xl mx-auto leading-relaxed">
                Backend terintegrasi dengan pencocokan suku cadang deterministik (*Zero Hallucination*), kalkulasi ongkir multi-ekspedisi real-time, routing jarak cabang gudang, dan sinkronisasi kasir POS.
            </p>

            <!-- Key Performance Counters -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-10 max-w-3xl mx-auto">
                <div class="glass-panel p-4 rounded-2xl text-center">
                    <div class="text-2xl font-extrabold text-white">{{ $stats['vehicles_count'] }}</div>
                    <div class="text-[11px] text-gray-400 mt-1 font-medium">Model Motor Terdata</div>
                </div>
                <div class="glass-panel p-4 rounded-2xl text-center">
                    <div class="text-2xl font-extrabold text-emerald-400">{{ $stats['products_count'] }}</div>
                    <div class="text-[11px] text-gray-400 mt-1 font-medium">Spareparts Terdaftar</div>
                </div>
                <div class="glass-panel p-4 rounded-2xl text-center">
                    <div class="text-2xl font-extrabold text-cyan-400">{{ $stats['warehouses_count'] ?? 3 }}</div>
                    <div class="text-[11px] text-gray-400 mt-1 font-medium">Jaringan Cabang Gudang</div>
                </div>
                <div class="glass-panel p-4 rounded-2xl text-center">
                    <div class="text-2xl font-extrabold text-amber-400">&lt; 50ms</div>
                    <div class="text-[11px] text-gray-400 mt-1 font-medium">Latensi Respon API</div>
                </div>
            </div>
        </div>
    </section>

    <!-- SECTION 1: AI DIAGNOSTIC STUDIO & COMPATIBILITY ASSISTANT -->
    <section id="ai-diagnostic" class="py-10 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
            
            <!-- Left: AI Chat Window with SSE Streaming Typewriter -->
            <div class="lg:col-span-7 glass-panel rounded-3xl p-6 border border-gray-800 glow-emerald flex flex-col shadow-2xl">
                <div class="flex items-center justify-between pb-4 border-b border-gray-800">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-center">
                            <span class="text-xl">🤖</span>
                        </div>
                        <div>
                            <h2 class="text-base font-bold text-white">AI Diagnostic & Sales Assistant</h2>
                            <p class="text-xs text-gray-400">SSE Live Stream • Tool Calling Terverifikasi</p>
                        </div>
                    </div>

                    <!-- Motor Selector Context -->
                    <div class="flex items-center space-x-2">
                        <label for="ai-vehicle-select" class="text-xs text-gray-400 font-semibold">Motor Anda:</label>
                        <select id="ai-vehicle-select" class="bg-gray-900 border border-gray-700 text-gray-200 text-xs rounded-xl px-3 py-1.5 focus:border-emerald-500 outline-none">
                            <option value="">Pilih Model Motor...</option>
                            @foreach($vehicles as $v)
                                <option value="{{ $v->id }}">{{ $v->full_name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <!-- Live Chat Message Stream Area -->
                <div id="chat-messages" class="flex-1 overflow-y-auto min-h-[360px] max-h-[440px] py-4 space-y-4 pr-2">
                    <div class="flex items-start space-x-3">
                        <div class="w-8 h-8 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-xs font-bold flex-shrink-0">
                            AI
                        </div>
                        <div class="bg-gray-900/90 border border-gray-800 rounded-2xl p-4 max-w-[85%] text-sm text-gray-200 leading-relaxed">
                            Halo! Saya asisten pintar MotoVault. Pilih motor Anda di atas atau langsung ceritakan keluhan motor (misal: <i>"motor gredek pas nanjak"</i> atau <i>"stang berat"</i>) untuk analisa suku cadang otomatis!
                        </div>
                    </div>
                </div>

                <!-- Diagnostic Trigger Prompt Pills -->
                <div class="pt-3 pb-3 flex flex-wrap gap-2 text-xs border-t border-gray-800/80">
                    <span class="text-[11px] text-gray-500 self-center mr-1 font-semibold">Keluhan Populer:</span>
                    <button onclick="sendQuickPrompt('Motor saya gredek dan getar parah pas nanjak')" class="px-3 py-1 rounded-xl bg-gray-900 border border-gray-800 hover:border-emerald-500 text-gray-300 transition text-[11px]">
                        🛵 Gredek CVT
                    </button>
                    <button onclick="sendQuickPrompt('Stang berat dan belok goyang')" class="px-3 py-1 rounded-xl bg-gray-900 border border-gray-800 hover:border-emerald-500 text-gray-300 transition text-[11px]">
                        🔧 Komstir / Stang
                    </button>
                    <button onclick="sendQuickPrompt('Rem depan bunyi berdecit dan kurang pakem')" class="px-3 py-1 rounded-xl bg-gray-900 border border-gray-800 hover:border-emerald-500 text-gray-300 transition text-[11px]">
                        🛑 Kampas Rem
                    </button>
                    <button onclick="sendQuickPrompt('Rekomendasikan oli mesin sintetis terbaik')" class="px-3 py-1 rounded-xl bg-gray-900 border border-gray-800 hover:border-emerald-500 text-gray-300 transition text-[11px]">
                        🛢️ Oli Mesin
                    </button>
                </div>

                <!-- Chat Input Controls -->
                <div class="pt-3 flex items-center space-x-2">
                    <input type="text" id="chat-input" placeholder="Tanyakan suku cadang atau keluhan motor Anda..." 
                           class="flex-1 bg-gray-900 border border-gray-700/80 text-gray-200 rounded-2xl px-4 py-3 text-xs sm:text-sm focus:border-emerald-500 focus:outline-none transition"
                           onkeypress="if(event.key === 'Enter') sendMessage()">
                    <button id="chat-send-btn" onclick="sendMessage()" class="px-5 py-3 bg-emerald-500 hover:bg-emerald-400 text-black font-extrabold rounded-2xl text-xs sm:text-sm transition flex items-center space-x-1.5 active:scale-95 shadow-md shadow-emerald-500/20">
                        <span>Kirim</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Right: Verified Catalog Explorer -->
            <div id="katalog-produk" class="lg:col-span-5 glass-panel rounded-3xl p-6 border border-gray-800 flex flex-col shadow-2xl">
                <div class="flex items-center justify-between pb-4 border-b border-gray-800">
                    <div>
                        <h2 class="text-base font-bold text-white">Katalog Suku Cadang</h2>
                        <p class="text-xs text-gray-400">Pencarian & filter kecocokan motor</p>
                    </div>
                    <button onclick="loadProducts()" class="text-xs font-semibold text-emerald-400 hover:text-emerald-300">Refresh</button>
                </div>

                <!-- Catalog Filters -->
                <div class="grid grid-cols-2 gap-2 my-3">
                    <select id="catalog-vehicle-filter" onchange="loadProducts()" class="bg-gray-900 border border-gray-700 text-gray-200 text-xs rounded-xl px-2.5 py-2 outline-none">
                        <option value="">Semua Motor</option>
                        @foreach($vehicles as $v)
                            <option value="{{ $v->id }}">{{ $v->brand }} {{ $v->model }}</option>
                        @endforeach
                    </select>

                    <select id="catalog-category-filter" onchange="loadProducts()" class="bg-gray-900 border border-gray-700 text-gray-200 text-xs rounded-xl px-2.5 py-2 outline-none">
                        <option value="">Semua Kategori</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Dynamic Products Scroll Area -->
                <div id="product-list" class="flex-1 overflow-y-auto min-h-[380px] max-h-[440px] space-y-2.5 pr-1 text-sm">
                    <div class="text-center py-12 text-gray-500 text-xs">Memuat katalog suku cadang...</div>
                </div>
            </div>

        </div>
    </section>

    <!-- SECTION 2: 3PL LOGISTICS & RESI TRACKER -->
    <section id="logistik-3pl" class="py-12 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto border-t border-gray-800/80">
        <div class="mb-8 text-center max-w-xl mx-auto">
            <h2 class="text-2xl sm:text-3xl font-extrabold text-white">3PL Multi-Kurir & Pelacakan Resi</h2>
            <p class="text-xs sm:text-sm text-gray-400 mt-1">Kalkulasi ongkir otomatis untuk JNE, J&T, SiCepat, dan GoSend berdasarkan berat nyata & volumetrik.</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
            <!-- Rate Calculator -->
            <div class="lg:col-span-7 glass-panel rounded-3xl p-6 border border-gray-800 shadow-xl">
                <div class="flex items-center justify-between pb-3 border-b border-gray-800 mb-4">
                    <h3 class="text-sm font-bold text-white flex items-center space-x-2">
                        <span>🚚 Hitung Tarif Ongkir Real-Time</span>
                    </h3>
                    <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">POST /api/v1/shipping/rates</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
                    <div>
                        <label class="block text-xs text-gray-400 mb-1">Kodepos Asal:</label>
                        <input type="text" id="ship-origin" value="12190" class="w-full bg-gray-900 border border-gray-700 rounded-xl px-3 py-2 text-xs text-gray-200 font-mono">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-400 mb-1">Kodepos Tujuan:</label>
                        <input type="text" id="ship-dest" value="40123" placeholder="Misal: 40123" class="w-full bg-gray-900 border border-gray-700 rounded-xl px-3 py-2 text-xs text-gray-200 font-mono">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-400 mb-1">Filter Kurir:</label>
                        <select id="ship-courier" class="w-full bg-gray-900 border border-gray-700 rounded-xl px-3 py-2 text-xs text-gray-200">
                            <option value="">Semua Kurir</option>
                            <option value="jne">JNE Express</option>
                            <option value="jnt">J&T Express</option>
                            <option value="sicepat">SiCepat Ekspres</option>
                            <option value="gosend">GoSend Instant/SameDay</option>
                        </select>
                    </div>
                </div>

                <button onclick="calculateShippingRates()" class="w-full py-2.5 bg-emerald-500 hover:bg-emerald-400 text-black font-extrabold rounded-xl text-xs transition mb-4">
                    Kalkulasi Tarif Sekarang
                </button>

                <div id="shipping-rates-results" class="space-y-2 max-h-[300px] overflow-y-auto pr-1">
                    <div class="text-center py-8 text-gray-500 text-xs">Klik tombol di atas untuk menampilkan opsi ekspedisi.</div>
                </div>
            </div>

            <!-- Waybill Tracker -->
            <div class="lg:col-span-5 glass-panel rounded-3xl p-6 border border-gray-800 shadow-xl">
                <div class="pb-3 border-b border-gray-800 mb-4">
                    <h3 class="text-sm font-bold text-white">🔍 Lacak Nomor Resi Pengiriman (AWB)</h3>
                    <p class="text-xs text-gray-400 mt-0.5">Lacak status pesanan logistik 3PL</p>
                </div>

                <div class="flex items-center space-x-2 mb-4">
                    <input type="text" id="track-waybill-input" placeholder="Misal: MV-JNE-260918-XYZ..." 
                           class="flex-1 bg-gray-900 border border-gray-700 rounded-xl px-3 py-2 text-xs text-gray-200 font-mono">
                    <button onclick="trackWaybill()" class="px-4 py-2 bg-cyan-500 hover:bg-cyan-400 text-black font-bold rounded-xl text-xs transition">
                        Lacak
                    </button>
                </div>

                <div id="waybill-tracking-result" class="bg-gray-900/80 p-4 rounded-2xl border border-gray-800 text-xs text-gray-400 min-h-[240px]">
                    <div class="text-center py-12 text-gray-500">Masukkan nomor resi untuk melihat riwayat checkpoint logistik.</div>
                </div>
            </div>
        </div>
    </section>

    <!-- SECTION 3: MULTI-WAREHOUSE PROXIMITY & GEOLOCATION -->
    <section id="gudang-cabang" class="py-12 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto border-t border-gray-800/80">
        <div class="mb-8 text-center max-w-xl mx-auto">
            <h2 class="text-2xl sm:text-3xl font-extrabold text-white">Jaringan Cabang & Proximity Routing</h2>
            <p class="text-xs sm:text-sm text-gray-400 mt-1">Pemenuhan pesanan dari gudang terdekat menggunakan rumus Geodesic Haversine.</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
            <div class="lg:col-span-7 glass-panel rounded-3xl p-6 border border-gray-800 shadow-xl">
                <label class="block text-xs text-gray-400 mb-2 font-semibold">Simulasi Titik Geolocation Pelanggan:</label>
                <div class="grid grid-cols-3 gap-2 mb-4">
                    <button onclick="setCoordinates(-6.2088, 106.8456, 'Jakarta')" class="px-3 py-2 rounded-xl bg-gray-900 border border-gray-800 hover:border-emerald-500 text-xs text-left transition">
                        <div class="font-bold text-white">📍 Jakarta</div>
                        <div class="text-[10px] text-gray-500 font-mono">-6.208, 106.845</div>
                    </button>
                    <button onclick="setCoordinates(-6.9024, 107.6186, 'Bandung')" class="px-3 py-2 rounded-xl bg-gray-900 border border-gray-800 hover:border-emerald-500 text-xs text-left transition">
                        <div class="font-bold text-white">📍 Bandung</div>
                        <div class="text-[10px] text-gray-500 font-mono">-6.902, 107.618</div>
                    </button>
                    <button onclick="setCoordinates(-7.2575, 112.7521, 'Surabaya')" class="px-3 py-2 rounded-xl bg-gray-900 border border-gray-800 hover:border-emerald-500 text-xs text-left transition">
                        <div class="font-bold text-white">📍 Surabaya</div>
                        <div class="text-[10px] text-gray-500 font-mono">-7.257, 112.752</div>
                    </button>
                </div>

                <div class="grid grid-cols-2 gap-3 mb-4">
                    <div>
                        <label class="block text-[11px] text-gray-400 mb-1">Latitude:</label>
                        <input type="text" id="geo-lat" value="-6.9024" class="w-full bg-gray-900 border border-gray-700 rounded-xl px-3 py-2 text-xs text-gray-200 font-mono">
                    </div>
                    <div>
                        <label class="block text-[11px] text-gray-400 mb-1">Longitude:</label>
                        <input type="text" id="geo-lng" value="107.6186" class="w-full bg-gray-900 border border-gray-700 rounded-xl px-3 py-2 text-xs text-gray-200 font-mono">
                    </div>
                </div>

                <button onclick="checkWarehouseRouting()" class="w-full py-2.5 bg-cyan-500 hover:bg-cyan-400 text-black font-extrabold rounded-xl text-xs transition mb-4">
                    Hitung Jarak & Rute Cabang Optimal
                </button>

                <div id="warehouse-routing-result" class="space-y-2 max-h-[260px] overflow-y-auto pr-1">
                    <div class="text-center py-6 text-gray-500 text-xs">Pilih lokasi di atas untuk melihat alokasi cabang terdekat.</div>
                </div>
            </div>

            <!-- Warehouses List -->
            <div class="lg:col-span-5 glass-panel rounded-3xl p-6 border border-gray-800 shadow-xl">
                <h3 class="text-sm font-bold text-white pb-3 border-b border-gray-800 mb-3">Daftar Gudang Cabang Aktif</h3>
                <div class="space-y-3">
                    @forelse($warehouses as $wh)
                        <div class="bg-gray-900/90 border border-gray-800 p-3.5 rounded-2xl">
                            <div class="flex items-start justify-between">
                                <div>
                                    <div class="flex items-center space-x-2">
                                        <span class="text-xs font-bold text-white">{{ $wh->name }}</span>
                                        @if($wh->is_central)
                                            <span class="px-2 py-0.2 rounded text-[9px] font-bold bg-amber-500/20 text-amber-400 border border-amber-500/30">Pusat</span>
                                        @endif
                                    </div>
                                    <div class="text-[11px] text-gray-400 mt-1">{{ $wh->address }}, {{ $wh->city }} {{ $wh->postal_code }}</div>
                                </div>
                                <span class="text-[10px] font-mono font-bold text-emerald-400">{{ $wh->code }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-6 text-gray-500 text-xs">Belum ada cabang terdaftar.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </section>

    <!-- SECTION 4: REST API REFERENCE & DOCUMENTATION -->
    <section id="api-reference" class="py-12 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto border-t border-gray-800/80">
        <div class="glass-panel rounded-3xl p-6 sm:p-8 border border-gray-800 shadow-2xl">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-gray-800">
                <div>
                    <h3 class="text-lg font-extrabold text-white">REST API Architecture & Sandbox</h3>
                    <p class="text-xs text-gray-400 mt-0.5">Teruji 100% pada 43 endpoint untuk integrasi Web, Mobile App, dan Kasir POS.</p>
                </div>
                <div class="flex items-center space-x-2 text-xs">
                    <span class="px-2.5 py-1 rounded-lg bg-blue-500/10 text-blue-400 border border-blue-500/20 font-mono">Accept: application/json</span>
                    <span class="px-2.5 py-1 rounded-lg bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 font-mono">Content-Type: application/json</span>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mt-6">
                <div class="bg-gray-900/80 p-4 rounded-2xl border border-gray-800">
                    <div class="flex items-center justify-between mb-2">
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-green-500/20 text-green-400">GET</span>
                        <span class="text-xs text-gray-500 font-mono">Public</span>
                    </div>
                    <div class="text-xs font-mono text-gray-200 font-bold mb-1">/api/v1/products</div>
                    <p class="text-xs text-gray-400">Katalog suku cadang dengan filter <code>vehicle_id</code>, harga, dan kategori.</p>
                </div>

                <div class="bg-gray-900/80 p-4 rounded-2xl border border-gray-800">
                    <div class="flex items-center justify-between mb-2">
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-500/20 text-blue-400">POST</span>
                        <span class="text-xs text-gray-500 font-mono">Public</span>
                    </div>
                    <div class="text-xs font-mono text-gray-200 font-bold mb-1">/api/v1/ai/chat/stream</div>
                    <p class="text-xs text-gray-400">Server-Sent Events streaming konsultasi AI & diagnosa RAG.</p>
                </div>

                <div class="bg-gray-900/80 p-4 rounded-2xl border border-gray-800">
                    <div class="flex items-center justify-between mb-2">
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-500/20 text-blue-400">POST</span>
                        <span class="text-xs text-gray-500 font-mono">Public</span>
                    </div>
                    <div class="text-xs font-mono text-gray-200 font-bold mb-1">/api/v1/shipping/rates</div>
                    <p class="text-xs text-gray-400">Tarif ongkir multi-kurir real-time (JNE, J&T, SiCepat, GoSend).</p>
                </div>

                <div class="bg-gray-900/80 p-4 rounded-2xl border border-gray-800">
                    <div class="flex items-center justify-between mb-2">
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-500/20 text-amber-400">POST</span>
                        <span class="text-xs text-gray-500 font-mono">Staff / Admin</span>
                    </div>
                    <div class="text-xs font-mono text-gray-200 font-bold mb-1">/api/v1/pos/orders</div>
                    <p class="text-xs text-gray-400">Transaksi kasir langsung toko fisik dengan pemotongan stok atomik.</p>
                </div>
            </div>

            <!-- Demo Account Credentials -->
            <div class="mt-6 pt-4 border-t border-gray-800 flex flex-wrap items-center justify-between gap-4 text-xs text-gray-400">
                <div>
                    <span class="font-bold text-gray-200">Akun Sandbox (Password: <code>password</code>):</span>
                    <span class="ml-2 font-mono text-emerald-400">admin@motovault.test</span> (Admin) |
                    <span class="font-mono text-cyan-400">staff@motovault.test</span> (Kasir) |
                    <span class="font-mono text-amber-400">customer@motovault.test</span> (User)
                </div>
                <div class="text-gray-500 font-mono">
                    MotoVault Enterprise Engine • Laravel 11/12
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="border-t border-gray-800/80 py-8 px-4 text-center text-xs text-gray-500">
        <p>© 2026 MotoVault Indonesia. All rights reserved.</p>
    </footer>

    <!-- Client-Side JavaScript Logic -->
    <script>
        let currentSessionToken = null;

        document.addEventListener('DOMContentLoaded', () => {
            loadProducts();
            calculateShippingRates();
            checkWarehouseRouting();
        });

        // 1. Catalog Loader
        async function loadProducts() {
            const vehicleId = document.getElementById('catalog-vehicle-filter')?.value;
            const categoryId = document.getElementById('catalog-category-filter')?.value;
            const container = document.getElementById('product-list');
            if (!container) return;

            let url = '/api/v1/products?per_page=15';
            if (vehicleId) url += `&vehicle_id=${vehicleId}`;
            if (categoryId) url += `&category_id=${categoryId}`;

            container.innerHTML = '<div class="text-center py-10 text-gray-500 text-xs">Memuat katalog...</div>';

            try {
                const res = await fetch(url);
                const json = await res.json();

                if (!json.success || !json.data || json.data.length === 0) {
                    container.innerHTML = '<div class="text-center py-10 text-gray-500 text-xs">Tidak ada produk ditemukan.</div>';
                    return;
                }

                container.innerHTML = json.data.map(p => `
                    <div class="bg-gray-900/90 border border-gray-800 p-3.5 rounded-2xl hover:border-emerald-500/40 transition">
                        <div class="flex items-start justify-between">
                            <div>
                                <span class="text-[10px] font-bold text-emerald-400 uppercase tracking-wider font-mono">${p.brand || 'Universal'}</span>
                                <h4 class="text-xs font-bold text-white line-clamp-1">${p.name}</h4>
                            </div>
                            <span class="text-xs font-bold text-emerald-400 font-mono">Rp ${(p.base_price || 0).toLocaleString('id-ID')}</span>
                        </div>
                        <div class="mt-2 flex items-center justify-between text-[11px] text-gray-400">
                            <span class="bg-gray-800 px-2 py-0.5 rounded-md text-gray-300 text-[10px]">${p.category?.name || 'General'}</span>
                            <span>Stok: <b class="${(p.total_stock || 0) > 5 ? 'text-gray-200' : 'text-amber-400'}">${p.total_stock || 0} unit</b></span>
                        </div>
                    </div>
                `).join('');
            } catch (err) {
                container.innerHTML = `<div class="text-center py-10 text-rose-500 text-xs">Gagal: ${err.message}</div>`;
            }
        }

        // 2. AI Chat with SSE Streaming Typewriter
        function sendQuickPrompt(text) {
            document.getElementById('chat-input').value = text;
            sendMessage();
        }

        async function sendMessage() {
            const input = document.getElementById('chat-input');
            const message = input.value.trim();
            if (!message) return;

            const vehicleId = document.getElementById('ai-vehicle-select').value;
            const messagesContainer = document.getElementById('chat-messages');
            const sendBtn = document.getElementById('chat-send-btn');

            messagesContainer.insertAdjacentHTML('beforeend', `
                <div class="flex items-start justify-end space-x-3">
                    <div class="bg-emerald-600 text-white rounded-2xl rounded-tr-sm p-3.5 max-w-[85%] text-sm shadow-md">
                        ${escapeHtml(message)}
                    </div>
                </div>
            `);
            input.value = '';
            messagesContainer.scrollTop = messagesContainer.scrollHeight;

            const loadingId = 'ai-loading-' + Date.now();
            messagesContainer.insertAdjacentHTML('beforeend', `
                <div id="${loadingId}" class="flex items-start space-x-3">
                    <div class="w-8 h-8 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-xs font-bold">AI</div>
                    <div class="bg-gray-900/90 border border-gray-800 rounded-2xl p-3 text-xs text-gray-400 flex items-center space-x-2">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                        <span>Menganalisis diagnosa keluhan & suku cadang...</span>
                    </div>
                </div>
            `);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;

            sendBtn.disabled = true;

            try {
                const payload = { message: message };
                if (vehicleId) payload.vehicle_id = parseInt(vehicleId);
                if (currentSessionToken) payload.session_token = currentSessionToken;

                const response = await fetch('/api/v1/ai/chat/stream', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'text/event-stream' },
                    body: JSON.stringify(payload),
                });

                document.getElementById(loadingId)?.remove();

                const aiBubbleId = 'ai-bubble-' + Date.now();
                messagesContainer.insertAdjacentHTML('beforeend', `
                    <div class="flex items-start space-x-3">
                        <div class="w-8 h-8 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-xs font-bold flex-shrink-0">AI</div>
                        <div class="bg-gray-900/90 border border-gray-800 rounded-2xl rounded-tl-sm p-4 max-w-[85%] text-sm text-gray-200 leading-relaxed shadow-md">
                            <div id="${aiBubbleId}" class="whitespace-pre-line"></div>
                            <div id="${aiBubbleId}-products"></div>
                        </div>
                    </div>
                `);

                const textElem = document.getElementById(aiBubbleId);
                const prodElem = document.getElementById(aiBubbleId + '-products');
                const reader = response.body.getReader();
                const decoder = new TextDecoder('utf-8');
                let buffer = '';

                while (true) {
                    const { value, done } = await reader.read();
                    if (done) break;

                    buffer += decoder.decode(value, { stream: true });
                    const lines = buffer.split('\n\n');
                    buffer = lines.pop();

                    for (const block of lines) {
                        if (!block.trim()) continue;
                        const eventMatch = block.match(/event:\s*([^\n]+)/);
                        const dataMatch = block.match(/data:\s*([^\n]+)/);
                        const event = eventMatch ? eventMatch[1].trim() : 'message';
                        const rawData = dataMatch ? dataMatch[1].trim() : '';

                        if (event === 'session') {
                            try { currentSessionToken = JSON.parse(rawData).session_token; } catch(e) {}
                        } else if (event === 'token') {
                            try {
                                textElem.textContent += JSON.parse(rawData).text;
                            } catch(e) {
                                textElem.textContent += rawData;
                            }
                            messagesContainer.scrollTop = messagesContainer.scrollHeight;
                        } else if (event === 'product_recommendations') {
                            try {
                                const prods = JSON.parse(rawData);
                                if (Array.isArray(prods) && prods.length > 0) {
                                    prodElem.innerHTML = `
                                        <div class="mt-3 space-y-2">
                                            <div class="text-[11px] font-bold text-emerald-400 uppercase tracking-wider">Rekomendasi Suku Cadang Terverifikasi:</div>
                                            ${prods.map(p => `
                                                <div class="bg-gray-950/80 border border-gray-800 p-2.5 rounded-xl flex items-center justify-between text-xs">
                                                    <div>
                                                        <div class="font-bold text-gray-200">${escapeHtml(p.name)}</div>
                                                        <div class="text-[11px] text-gray-400 font-mono">SKU: ${escapeHtml(p.sku)} ${p.compatibility_note ? '• '+escapeHtml(p.compatibility_note) : ''}</div>
                                                    </div>
                                                    <div class="text-right">
                                                        <div class="font-bold text-emerald-400">Rp ${Number(p.price).toLocaleString('id-ID')}</div>
                                                        <div class="text-[10px] text-gray-400">Stok: ${p.stock}</div>
                                                    </div>
                                                </div>
                                            `).join('')}
                                        </div>
                                    `;
                                    messagesContainer.scrollTop = messagesContainer.scrollHeight;
                                }
                            } catch(e) {}
                        }
                    }
                }
            } catch (err) {
                document.getElementById(loadingId)?.remove();
                messagesContainer.insertAdjacentHTML('beforeend', `<div class="text-xs text-rose-400">Error: ${escapeHtml(err.message)}</div>`);
            } finally {
                sendBtn.disabled = false;
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            }
        }

        // 3. Shipping Rates
        async function calculateShippingRates() {
            const dest = document.getElementById('ship-dest')?.value.trim() || '40123';
            const origin = document.getElementById('ship-origin')?.value.trim() || '12190';
            const courier = document.getElementById('ship-courier')?.value;
            const container = document.getElementById('shipping-rates-results');
            if (!container) return;

            container.innerHTML = '<div class="text-center py-6 text-gray-500 text-xs">Menghitung ongkos kirim...</div>';

            try {
                const payload = { destination_postal_code: dest, origin_postal_code: origin };
                if (courier) payload.courier = courier;

                const res = await fetch('/api/v1/shipping/rates', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify(payload),
                });
                const json = await res.json();

                if (!json.success || !json.data || !json.data.rates || json.data.rates.length === 0) {
                    container.innerHTML = '<div class="text-center py-6 text-gray-500 text-xs">Tidak ada tarif tersedia.</div>';
                    return;
                }

                container.innerHTML = json.data.rates.map(r => `
                    <div class="bg-gray-900/90 border border-gray-800 p-3 rounded-2xl flex items-center justify-between hover:border-gray-700">
                        <div class="flex items-center space-x-3">
                            <div class="w-8 h-8 rounded-xl bg-gray-800 flex items-center justify-center font-bold text-xs uppercase text-emerald-400">
                                ${r.courier_code}
                            </div>
                            <div>
                                <div class="text-xs font-bold text-white">${r.courier_name} — <span class="text-emerald-400">${r.service_code}</span></div>
                                <div class="text-[11px] text-gray-400">${r.service_name} • Estimasi: <b class="text-gray-200">${r.etd}</b></div>
                            </div>
                        </div>
                        <div class="text-right font-mono font-bold text-sm text-emerald-400">
                            ${r.formatted_price}
                        </div>
                    </div>
                `).join('');
            } catch (err) {
                container.innerHTML = `<div class="text-center py-6 text-rose-500 text-xs">Gagal: ${err.message}</div>`;
            }
        }

        // 4. Track Waybill
        async function trackWaybill() {
            const waybill = document.getElementById('track-waybill-input')?.value.trim();
            const container = document.getElementById('waybill-tracking-result');
            if (!waybill || !container) return;

            container.innerHTML = '<div class="text-center py-8 text-gray-500">Mencari data resi...</div>';

            try {
                const res = await fetch(`/api/v1/shipping/track/${encodeURIComponent(waybill)}`);
                const json = await res.json();

                if (!json.success) {
                    container.innerHTML = `<div class="text-center py-8 text-rose-400 font-semibold">${json.message}</div>`;
                    return;
                }

                const d = json.data;
                container.innerHTML = `
                    <div class="space-y-3">
                        <div class="flex items-center justify-between border-b border-gray-800 pb-2">
                            <div>
                                <div class="text-sm font-bold text-white">${d.waybill_number}</div>
                                <div class="text-[10px] text-gray-400 uppercase font-mono">${d.courier_code} ${d.courier_service}</div>
                            </div>
                            <span class="px-2.5 py-1 rounded-lg bg-emerald-500/20 text-emerald-400 font-bold uppercase text-[10px]">${d.tracking_status}</span>
                        </div>
                        <div class="text-xs text-gray-300">
                            Tujuan: <b>${d.destination_address || '-'}</b> (${d.destination_postal_code})
                        </div>
                        <div class="space-y-2 mt-3 pt-2 border-t border-gray-800">
                            <div class="text-[11px] font-bold text-gray-400 uppercase">Riwayat Milestone:</div>
                            ${(d.history || []).map(h => `
                                <div class="bg-gray-950 p-2.5 rounded-xl border border-gray-800/80">
                                    <div class="flex items-center justify-between font-bold text-emerald-400 text-[11px]">
                                        <span>${h.status.toUpperCase()} • ${h.location || ''}</span>
                                        <span class="text-gray-500 font-mono text-[10px]">${h.timestamp || ''}</span>
                                    </div>
                                    <div class="text-gray-300 text-[11px] mt-0.5">${h.description}</div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                `;
            } catch (err) {
                container.innerHTML = `<div class="text-center py-8 text-rose-400">Error: ${err.message}</div>`;
            }
        }

        // 5. Warehouse Geolocation Routing
        function setCoordinates(lat, lng, label) {
            document.getElementById('geo-lat').value = lat;
            document.getElementById('geo-lng').value = lng;
            checkWarehouseRouting();
        }

        async function checkWarehouseRouting() {
            const lat = parseFloat(document.getElementById('geo-lat')?.value) || -6.9024;
            const lng = parseFloat(document.getElementById('geo-lng')?.value) || 107.6186;
            const container = document.getElementById('warehouse-routing-result');
            if (!container) return;

            container.innerHTML = '<div class="text-center py-6 text-gray-500 text-xs">Menghitung jarak ke cabang...</div>';

            try {
                const res = await fetch('/api/v1/warehouses/route-nearest', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({
                        latitude: lat,
                        longitude: lng,
                        items: [{ sku: 'DAY-RLR-11G', quantity: 2 }]
                    })
                });
                const json = await res.json();

                if (!json.success || !json.data) {
                    container.innerHTML = '<div class="text-center py-6 text-gray-500 text-xs">Gagal menghitung rute.</div>';
                    return;
                }

                const bestWh = json.data.warehouse;
                const alts = json.data.alternatives || [];

                container.innerHTML = `
                    <div class="bg-emerald-950/40 border border-emerald-500/40 p-4 rounded-2xl mb-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500 text-black uppercase">Cabang Terpilih</span>
                                <h3 class="text-sm font-bold text-white mt-1">${bestWh.name}</h3>
                                <p class="text-xs text-gray-300">${bestWh.city} • Jarak: <b class="text-emerald-400">${bestWh.distance_km} km</b></p>
                            </div>
                            <span class="text-xs font-mono font-bold text-emerald-400">${bestWh.code}</span>
                        </div>
                    </div>
                    <div class="text-[11px] font-bold text-gray-400 uppercase mt-3 mb-1">Jarak Seluruh Cabang:</div>
                    ${alts.map(a => `
                        <div class="bg-gray-900/90 border border-gray-800 p-2.5 rounded-xl flex items-center justify-between text-xs">
                            <div>
                                <div class="font-bold text-gray-200">${a.warehouse.name}</div>
                                <div class="text-[10px] text-gray-400">${a.warehouse.city} • Stok: ${a.has_full_stock ? '<b class="text-emerald-400">Lengkap</b>' : '<b class="text-amber-400">Parsial</b>'}</div>
                            </div>
                            <span class="font-mono font-bold text-cyan-400">${a.distance_km} km</span>
                        </div>
                    `).join('')}
                `;
            } catch (err) {
                container.innerHTML = `<div class="text-center py-6 text-rose-500 text-xs">Error: ${err.message}</div>`;
            }
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.innerText = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>

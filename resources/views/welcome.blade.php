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
        [x-cloak] {
            display: none !important;
        }
    </style>

    <!-- Alpine.js CDN -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.5/dist/cdn.min.js"></script>
    <script>
        document.addEventListener('alpine:init', () => {
            // Helper for safe JSON localStorage retrieval
            function safeJsonParse(key, fallback) {
                try {
                    const item = localStorage.getItem(key);
                    return item ? JSON.parse(item) : fallback;
                } catch (e) {
                    return fallback;
                }
            }

            // --- AUTH STORE ---
            Alpine.store('auth', {
                token: localStorage.getItem('motovault_token') || null,
                user: safeJsonParse('motovault_user', null),
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
                items: safeJsonParse('motovault_cart', []),
                isDrawerOpen: false,
                isCheckoutOpen: false,
                isOrderTrackerOpen: false,
                checkoutStep: 1,
                isCheckingOut: false,
                checkoutError: '',
                checkoutSuccessData: null,
                isOrderSuccessOpen: false,

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

                openCheckout() {
                    this.isDrawerOpen = false;
                    this.isCheckoutOpen = true;
                    this.checkoutStep = 1;
                    this.checkoutError = '';
                    const authUser = Alpine.store('auth')?.user;
                    if (authUser) {
                        if (!this.recipientName) this.recipientName = authUser.name || '';
                        if (!this.recipientEmail) this.recipientEmail = authUser.email || '';
                        if (!this.recipientPhone && authUser.phone) this.recipientPhone = authUser.phone || '';
                    }
                },

                addItem(variant) {
                    if (!variant || variant.stock <= 0) {
                        alert('Stok habis');
                        return;
                    }
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
                    if (isNaN(newQty)) return;
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
            <nav class="hidden lg:flex items-center space-x-6 text-xs font-semibold text-gray-300">
                <a href="#katalog-produk" class="hover:text-emerald-400 transition">Katalog Terverifikasi</a>
                <a href="#ai-diagnostic" class="hover:text-emerald-400 transition">AI Diagnosa RAG</a>
                <a href="#logistik-3pl" class="hover:text-emerald-400 transition">3PL Cek Ongkir & Resi</a>
                <a href="#gudang-cabang" class="hover:text-emerald-400 transition">Routing Cabang</a>
                <a href="#api-reference" class="hover:text-emerald-400 transition">REST API Docs</a>
            </nav>

            <!-- Actions: Garage Indicator, Cart Button, Auth Button, POS Cashier Button -->
            <div class="flex items-center space-x-2 sm:space-x-3">
                <!-- My Garage Quick Indicator -->
                <button 
                    @click="document.getElementById('garage-bar')?.scrollIntoView({behavior: 'smooth'})"
                    x-show="$store.garage.selectedVehicleName"
                    x-cloak
                    class="hidden sm:inline-flex items-center space-x-1.5 px-3 py-1.5 rounded-xl bg-emerald-500/15 border border-emerald-500/30 text-emerald-300 text-xs font-semibold hover:bg-emerald-500/25 transition cursor-pointer"
                    title="Motor aktif di Garasi">
                    <span>🏍️</span>
                    <span x-text="$store.garage.selectedVehicleName" class="max-w-[120px] truncate"></span>
                </button>

                <!-- Customer Auth Button -->
                <button 
                    @click="$store.auth.isAuthModalOpen = true"
                    class="inline-flex items-center space-x-1.5 px-3 py-2 rounded-xl bg-gray-900/80 hover:bg-gray-800 border border-gray-700/80 text-gray-200 hover:text-white text-xs font-semibold transition active:scale-95 cursor-pointer">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    <span x-text="$store.auth.isAuthenticated ? ($store.auth.user?.name || 'Pelanggan') : 'Masuk / Daftar'"></span>
                </button>

                <!-- Cart Button with Count Badge -->
                <button 
                    @click="$store.cart.isDrawerOpen = true"
                    class="relative p-2 rounded-xl bg-gray-900/80 hover:bg-gray-800 border border-gray-700/80 text-gray-300 hover:text-white transition flex items-center justify-center active:scale-95 cursor-pointer"
                    aria-label="Keranjang Belanja">
                    <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                    </svg>
                    <span 
                        x-show="$store.cart.count > 0"
                        x-text="$store.cart.count"
                        x-cloak
                        class="absolute -top-1.5 -right-1.5 bg-emerald-500 text-black font-black text-[10px] w-5 h-5 rounded-full flex items-center justify-center shadow-lg shadow-emerald-500/50"></span>
                </button>

                <!-- POS Cashier Button -->
                <a href="/pos" target="_blank" class="px-3 sm:px-4 py-2 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-black text-xs font-extrabold shadow-lg shadow-emerald-500/20 transition flex items-center space-x-1.5 active:scale-95">
                    <span>💳 <span class="hidden sm:inline">Buka Terminal POS Kasir</span><span class="sm:hidden">POS</span></span>
                    <svg class="w-3.5 h-3.5 hidden sm:inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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

    <!-- "My Garage" Filter Bar -->
    <div id="garage-bar" class="sticky top-18 z-40 bg-[#070b12]/95 backdrop-blur-md border-y border-gray-800/80 py-3.5 px-4 sm:px-6 lg:px-8 shadow-2xl transition-all">
        <div class="max-w-7xl mx-auto flex flex-col md:flex-row items-center justify-between gap-3">
            
            <!-- Motor Selector -->
            <div class="flex items-center space-x-3 w-full md:w-auto">
                <div class="flex items-center space-x-2 text-white font-bold text-xs sm:text-sm whitespace-nowrap">
                    <span class="flex items-center justify-center w-8 h-8 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400">🏍️</span>
                    <span>Garasi Saya:</span>
                </div>

                <div class="flex-1 sm:w-80">
                    <select 
                        :value="$store.garage.selectedVehicleId"
                        @change="
                            const sel = $event.target;
                            const opt = sel.options[sel.selectedIndex];
                            if (sel.value) {
                                const motorName = opt.getAttribute('data-name') || opt.text;
                                $store.garage.setVehicle(sel.value, motorName);
                            } else {
                                $store.garage.clearVehicle();
                            }
                        "
                        class="w-full bg-gray-900 border border-gray-700/80 text-gray-200 text-xs rounded-xl px-3 py-2.5 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none transition cursor-pointer">
                        <option value="">-- Pilih Model Motor Anda --</option>
                        @foreach($vehicles as $v)
                            <option value="{{ $v->id }}" data-name="{{ $v->brand }} {{ $v->model }}" :selected="$store.garage.selectedVehicleId == {{ $v->id }}">
                                {{ $v->brand }} {{ $v->model }} ({{ $v->year_start }}{{ $v->year_end ? '-'.$v->year_end : '+' }})
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <!-- Active Status Banner or Prompt -->
            <div class="w-full md:w-auto flex items-center justify-between md:justify-end gap-2 text-xs">
                <div x-show="$store.garage.selectedVehicleId" x-cloak class="flex items-center gap-2 bg-emerald-950/70 border border-emerald-500/40 text-emerald-300 px-4 py-2 rounded-xl shadow-sm glow-emerald">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span class="text-xs">Motor Anda: <strong class="text-white" x-text="$store.garage.selectedVehicleName"></strong> — Menampilkan kecocokan suku cadang terverifikasi</span>
                    <button 
                        type="button"
                        @click="$store.garage.clearVehicle()" 
                        class="ml-2 px-2.5 py-1 rounded-lg bg-emerald-500/20 hover:bg-emerald-500/30 text-emerald-300 hover:text-white font-bold transition text-[11px] cursor-pointer"
                        title="Hapus / ganti pilihan motor">
                        ✕ Ganti
                    </button>
                </div>

                <div x-show="!$store.garage.selectedVehicleId" class="text-gray-400 text-xs italic flex items-center space-x-1.5 py-1">
                    <span>💡</span>
                    <span>Pilih motor Anda untuk melihat indikator kecocokan suku cadang secara instan.</span>
                </div>
            </div>

        </div>
    </div>

    <!-- SECTION: Dedicated Product Catalog Grid -->
    <section id="katalog-produk" 
        x-data="{
            searchQuery: '',
            activeCategory: 'all',
            filterProduct(catId, name, brand) {
                const matchesCat = (this.activeCategory === 'all' || String(this.activeCategory) === String(catId));
                const q = this.searchQuery.toLowerCase().trim();
                const matchesSearch = !q || name.toLowerCase().includes(q) || (brand && brand.toLowerCase().includes(q));
                return matchesCat && matchesSearch;
            }
        }"
        class="py-12 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto border-t border-gray-800/80">
        
        <!-- Section Header -->
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-8">
            <div>
                <div class="inline-flex items-center space-x-2 text-xs font-bold text-emerald-400 uppercase tracking-wider mb-1">
                    <span>🛒 Katalog Suku Cadang Terverifikasi</span>
                </div>
                <h2 class="text-2xl sm:text-3xl font-black text-white tracking-tight">
                    Suku Cadang Asli & Bergaransi Presisi
                </h2>
                <p class="text-xs sm:text-sm text-gray-400 mt-1">
                    Didukung sistem kompatibilitas deterministik agar suku cadang 100% pas dan tidak salah beli.
                </p>
            </div>

            <!-- Search Input -->
            <div class="relative w-full md:w-80">
                <input 
                    type="text" 
                    x-model="searchQuery" 
                    placeholder="Cari sparepart, merk, atau kode..." 
                    class="w-full bg-gray-900/90 border border-gray-800 rounded-2xl pl-10 pr-9 py-2.5 text-xs text-gray-200 placeholder-gray-500 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none transition">
                <svg class="w-4 h-4 text-gray-400 absolute left-3.5 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <button 
                    x-show="searchQuery" 
                    @click="searchQuery = ''" 
                    class="absolute right-3 top-2.5 text-gray-400 hover:text-white text-xs cursor-pointer">
                    ✕
                </button>
            </div>
        </div>

        <!-- Category Pills Filter -->
        <div class="flex items-center gap-2 overflow-x-auto pb-4 mb-6 scrollbar-none border-b border-gray-800/80">
            <button 
                type="button"
                @click="activeCategory = 'all'"
                :class="activeCategory === 'all' ? 'bg-emerald-500 text-black font-extrabold shadow-md shadow-emerald-500/20' : 'bg-gray-900/90 text-gray-300 hover:text-white border border-gray-800 hover:border-gray-700'"
                class="px-4 py-2 rounded-xl text-xs whitespace-nowrap transition cursor-pointer font-semibold">
                Semua Produk ({{ $products->count() }})
            </button>
            @foreach($categories as $cat)
                <button 
                    type="button"
                    @click="activeCategory = {{ $cat->id }}"
                    :class="activeCategory === {{ $cat->id }} ? 'bg-emerald-500 text-black font-extrabold shadow-md shadow-emerald-500/20' : 'bg-gray-900/90 text-gray-300 hover:text-white border border-gray-800 hover:border-gray-700'"
                    class="px-4 py-2 rounded-xl text-xs whitespace-nowrap transition cursor-pointer font-semibold">
                    {{ $cat->name }}
                </button>
            @endforeach
        </div>

        <!-- Products Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
            @forelse($products as $product)
                @php
                    $compatIds = $product->compatibleVehicles->pluck('id')->values()->all();
                    $variantsJson = $product->variants->map(function ($v) use ($product) {
                        return [
                            'id' => $v->id,
                            'sku' => $v->sku,
                            'variant_name' => $v->variant_name,
                            'additional_price' => (float) $v->additional_price,
                            'final_price' => (float) ($product->base_price + $v->additional_price),
                            'stock' => (int) $v->stock,
                            'weight_gram' => (int) ($v->weight_gram ?: ($product->weight_gram ?: 500)),
                        ];
                    })->values()->all();

                    if (empty($variantsJson)) {
                        $variantsJson[] = [
                            'id' => null,
                            'sku' => $product->slug,
                            'variant_name' => 'Standar',
                            'additional_price' => 0,
                            'final_price' => (float) $product->base_price,
                            'stock' => 0,
                            'weight_gram' => (int) ($product->weight_gram ?: 500),
                        ];
                    }
                @endphp

                <div 
                    x-data="{
                        productId: {{ $product->id }},
                        categoryId: {{ $product->category_id ?? 0 }},
                        name: {{ json_encode($product->name) }},
                        brand: {{ json_encode($product->brand ?? '') }},
                        basePrice: {{ (float) $product->base_price }},
                        compatibleVehicles: {{ json_encode($compatIds) }},
                        variants: {{ json_encode($variantsJson) }},
                        selectedVariantIndex: 0,

                        get currentVariant() {
                            return this.variants[this.selectedVariantIndex] || this.variants[0];
                        },

                        get currentPrice() {
                            return this.currentVariant ? this.currentVariant.final_price : this.basePrice;
                        },

                        get currentStock() {
                            return this.currentVariant ? this.currentVariant.stock : 0;
                        },

                        get isCompatible() {
                            const garageId = $store.garage.selectedVehicleId;
                            if (!garageId) return null;
                            return this.compatibleVehicles.some(id => String(id) === String(garageId));
                        },

                        addToCart() {
                            const v = this.currentVariant;
                            if (!v || v.stock <= 0) {
                                alert('Stok habis');
                                return;
                            }
                            $store.cart.addItem({
                                id: v.id,
                                sku: v.sku,
                                name: this.name,
                                variant_name: v.variant_name,
                                price: v.final_price,
                                stock: v.stock,
                                weight_gram: v.weight_gram
                            });
                        }
                    }"
                    x-show="filterProduct(categoryId, name, brand)"
                    class="glass-panel p-5 rounded-2xl border border-gray-800/90 hover:border-emerald-500/40 transition-all flex flex-col justify-between group shadow-xl hover:shadow-emerald-500/10">
                    
                    <!-- Top Card Content -->
                    <div>
                        <!-- Brand & Category -->
                        <div class="flex items-center justify-between gap-2 mb-3">
                            <span class="text-[10px] font-mono font-black uppercase px-2 py-0.5 rounded-md bg-gray-800/90 border border-gray-700 text-gray-300">
                                {{ $product->brand ?: 'Universal' }}
                            </span>
                            <span class="text-[11px] text-emerald-400 font-semibold">
                                {{ $product->category?->name ?: 'Suku Cadang' }}
                            </span>
                        </div>

                        <!-- Product Avatar Placeholder -->
                        <div class="w-full h-32 rounded-xl bg-gray-950/60 border border-gray-800/80 flex items-center justify-center mb-3 group-hover:border-emerald-500/30 transition-colors">
                            <div class="text-center">
                                <div class="text-3xl mb-1">⚙️</div>
                                <div class="text-[10px] text-gray-500 font-mono">SKU: {{ $product->variants->first()?->sku ?? $product->slug }}</div>
                            </div>
                        </div>

                        <!-- Product Title -->
                        <h3 class="text-sm font-bold text-white group-hover:text-emerald-300 transition-colors line-clamp-2 leading-snug">
                            {{ $product->name }}
                        </h3>

                        <!-- Compatibility Badge -->
                        <div class="mt-2.5 min-h-[26px] flex items-center">
                            <template x-if="isCompatible === true">
                                <div class="inline-flex items-center space-x-1 px-2.5 py-1 rounded-lg bg-emerald-500/15 border border-emerald-500/40 text-emerald-400 text-[11px] font-bold">
                                    <span>✓ Pasti Pas untuk <span x-text="$store.garage.selectedVehicleName"></span></span>
                                </div>
                            </template>
                            <template x-if="isCompatible === false">
                                <div class="inline-flex items-center space-x-1 px-2.5 py-1 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-400 text-[11px] font-medium">
                                    <span>⚠️ Belum terverifikasi untuk <span x-text="$store.garage.selectedVehicleName"></span></span>
                                </div>
                            </template>
                            <template x-if="isCompatible === null">
                                <div class="text-[10px] text-gray-500 italic">
                                    Pilih motor di Garasi untuk cek kecocokan
                                </div>
                            </template>
                        </div>

                        <!-- Variant Selection -->
                        <template x-if="variants.length > 1">
                            <div class="mt-3.5 pt-2 border-t border-gray-800/60">
                                <label class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block mb-1">Pilih Varian:</label>
                                <select 
                                    x-model="selectedVariantIndex"
                                    class="w-full bg-gray-900 border border-gray-700/80 rounded-xl px-2.5 py-1.5 text-xs text-gray-200 outline-none focus:border-emerald-500 transition cursor-pointer">
                                    <template x-for="(v, idx) in variants" :key="v.id">
                                        <option :value="idx" x-text="`${v.variant_name} — Rp ${v.final_price.toLocaleString('id-ID')} (Stok: ${v.stock})`"></option>
                                    </template>
                                </select>
                            </div>
                        </template>

                        <template x-if="variants.length === 1">
                            <div class="mt-2 flex items-center justify-between text-[11px] text-gray-400">
                                <span class="font-mono text-gray-400" x-text="variants[0].variant_name !== 'Default' ? variants[0].variant_name : variants[0].sku"></span>
                                <span class="text-[11px]" :class="currentStock > 0 ? 'text-gray-300' : 'text-rose-400 font-bold'" x-text="currentStock > 0 ? `Stok: ${currentStock}` : 'Stok Habis'"></span>
                            </div>
                        </template>
                    </div>

                    <!-- Bottom Card Footer: Price & Add to Cart -->
                    <div class="mt-4 pt-3 border-t border-gray-800/80 flex items-center justify-between gap-3">
                        <div>
                            <div class="text-[10px] text-gray-400 font-medium">Harga Satuan</div>
                            <div class="text-base font-black text-emerald-400 font-mono" x-text="'Rp ' + currentPrice.toLocaleString('id-ID')"></div>
                        </div>

                        <button 
                            type="button"
                            @click="addToCart()"
                            :disabled="currentStock <= 0"
                            :class="currentStock <= 0 ? 'bg-gray-800 text-gray-500 cursor-not-allowed border border-gray-700' : 'bg-emerald-500 hover:bg-emerald-400 text-black hover:shadow-emerald-500/25 active:scale-95 shadow-md shadow-emerald-500/15 cursor-pointer'"
                            class="px-3.5 py-2 rounded-xl text-xs font-extrabold transition-all flex items-center space-x-1.5">
                            <template x-if="currentStock > 0">
                                <span class="flex items-center space-x-1">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                                    </svg>
                                    <span>Tambah</span>
                                </span>
                            </template>
                            <template x-if="currentStock <= 0">
                                <span>Habis</span>
                            </template>
                        </button>
                    </div>

                </div>
            @empty
                <div class="col-span-full text-center py-12 glass-panel rounded-2xl border border-gray-800">
                    <div class="text-3xl mb-2">📦</div>
                    <div class="text-white font-bold text-sm">Belum ada suku cadang terdaftar</div>
                    <div class="text-gray-400 text-xs mt-1">Silakan tambahkan data produk melalui database seeder atau panel admin.</div>
                </div>
            @endforelse
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
            <div id="ai-quick-catalog" class="lg:col-span-5 glass-panel rounded-3xl p-6 border border-gray-800 flex flex-col shadow-2xl">
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

    <!-- Floating Cart Pill -->
    <div 
        x-show="$store.cart.count > 0"
        x-cloak
        x-transition:enter="transition ease-out duration-300 transform"
        x-transition:enter-start="opacity-0 translate-y-4 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-200 transform"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-4 scale-95"
        class="fixed bottom-6 right-6 z-40">
        <button 
            type="button"
            @click="$store.cart.isDrawerOpen = true"
            class="flex items-center space-x-3 px-4 py-3 bg-gradient-to-r from-emerald-500 via-teal-400 to-emerald-500 hover:from-emerald-400 hover:to-teal-300 text-black rounded-2xl shadow-2xl shadow-emerald-500/40 border border-emerald-300/40 hover:scale-105 active:scale-95 transition-all duration-200 cursor-pointer group">
            
            <!-- Cart Icon & Count Badge -->
            <div class="relative flex items-center justify-center">
                <svg class="w-6 h-6 text-black" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                </svg>
                <span 
                    x-text="$store.cart.count"
                    class="absolute -top-2 -right-2 bg-black text-emerald-400 font-mono font-black text-[10px] w-5 h-5 rounded-full flex items-center justify-center border border-emerald-400/50 shadow-md"></span>
            </div>

            <!-- Total Price Label -->
            <div class="text-left pl-1">
                <div class="text-[10px] font-bold uppercase tracking-wider text-black/70 leading-none">Keranjang</div>
                <div class="text-xs sm:text-sm font-black font-mono text-black leading-tight" x-text="'Rp ' + Number($store.cart.subtotal).toLocaleString('id-ID')"></div>
            </div>

            <!-- Arrow Icon -->
            <div class="pl-1 group-hover:translate-x-0.5 transition-transform">
                <svg class="w-4 h-4 text-black font-bold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                </svg>
            </div>
        </button>
    </div>

    <!-- Slide-Over Cart Drawer -->
    <div 
        x-show="$store.cart.isDrawerOpen"
        x-cloak
        @keydown.window.escape="$store.cart.isDrawerOpen = false"
        class="fixed inset-0 z-50 overflow-hidden"
        role="dialog" 
        aria-modal="true" 
        aria-labelledby="cart-drawer-title">

        <!-- Backdrop -->
        <div 
            x-show="$store.cart.isDrawerOpen"
            x-transition:enter="ease-in-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="ease-in-out duration-300"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            @click="$store.cart.isDrawerOpen = false"
            class="fixed inset-0 bg-black/75 backdrop-blur-sm transition-opacity"></div>

        <div class="fixed inset-y-0 right-0 max-w-full flex pl-10">
            <!-- Drawer Panel -->
            <div 
                x-show="$store.cart.isDrawerOpen"
                x-transition:enter="transform transition ease-in-out duration-300 sm:duration-500"
                x-transition:enter-start="translate-x-full"
                x-transition:enter-end="translate-x-0"
                x-transition:leave="transform transition ease-in-out duration-300 sm:duration-500"
                x-transition:leave-start="translate-x-0"
                x-transition:leave-end="translate-x-full"
                class="w-screen max-w-md bg-[#0e1524] border-l border-gray-800 text-gray-100 shadow-2xl flex flex-col justify-between">

                <!-- Header -->
                <div class="px-5 py-4 sm:px-6 sm:py-5 border-b border-gray-800 flex items-center justify-between bg-gray-900/60 backdrop-blur-md">
                    <div class="flex items-center space-x-2.5">
                        <span class="w-8 h-8 rounded-xl bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-center text-emerald-400">🛒</span>
                        <h2 id="cart-drawer-title" class="text-base sm:text-lg font-extrabold text-white">Keranjang Belanja</h2>
                        <span 
                            x-show="$store.cart.count > 0" 
                            x-text="$store.cart.count" 
                            class="bg-emerald-500/20 text-emerald-400 text-xs font-mono font-bold px-2 py-0.5 rounded-full border border-emerald-500/30"></span>
                    </div>

                    <div class="flex items-center space-x-2">
                        <!-- Clear Cart Button -->
                        <button 
                            type="button" 
                            x-show="$store.cart.count > 0" 
                            @click="if (confirm('Kosongkan semua item di keranjang?')) $store.cart.clearCart()" 
                            class="text-[11px] text-rose-400 hover:text-rose-300 font-semibold px-2 py-1 rounded-lg hover:bg-rose-500/10 transition cursor-pointer">
                            Kosongkan
                        </button>

                        <!-- Close Button -->
                        <button 
                            type="button" 
                            @click="$store.cart.isDrawerOpen = false" 
                            class="p-1.5 rounded-xl text-gray-400 hover:text-white hover:bg-gray-800 transition cursor-pointer" 
                            aria-label="Tutup Keranjang">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Body / Items List / Empty State -->
                <div class="flex-1 overflow-y-auto px-5 py-4 sm:px-6 space-y-4">
                    <!-- Empty State -->
                    <div x-show="$store.cart.count === 0" class="text-center py-16 flex flex-col items-center justify-center space-y-4">
                        <div class="w-20 h-20 rounded-3xl bg-gray-900 border border-gray-800 flex items-center justify-center text-4xl shadow-inner">
                            🛒
                        </div>
                        <div class="space-y-1">
                            <h3 class="text-base font-bold text-white">Keranjang belanja Anda masih kosong</h3>
                            <p class="text-xs text-gray-400 max-w-xs leading-relaxed">
                                Jelajahi katalog suku cadang bergaransi presisi untuk motor kesayangan Anda.
                            </p>
                        </div>
                        <button 
                            type="button" 
                            @click="$store.cart.isDrawerOpen = false; document.getElementById('katalog-produk')?.scrollIntoView({behavior: 'smooth'})" 
                            class="px-5 py-2.5 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-black text-xs font-extrabold transition shadow-lg shadow-emerald-500/20 active:scale-95 cursor-pointer">
                            Mulai Belanja
                        </button>
                    </div>

                    <!-- Items List -->
                    <div x-show="$store.cart.count > 0" class="divide-y divide-gray-800/80">
                        <template x-for="item in $store.cart.items" :key="item.sku">
                            <div class="py-4 first:pt-0 flex gap-3.5 items-start">
                                <!-- Item Avatar/Thumbnail -->
                                <div class="w-16 h-16 rounded-xl bg-gray-950 border border-gray-800 flex-shrink-0 flex items-center justify-center text-2xl shadow-inner">
                                    ⚙️
                                </div>

                                <!-- Item Details -->
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-start justify-between gap-2">
                                        <h4 class="text-xs sm:text-sm font-bold text-white truncate" x-text="item.name"></h4>
                                        <!-- Remove Item Button -->
                                        <button 
                                            type="button" 
                                            @click="$store.cart.removeItem(item.sku)" 
                                            class="text-gray-400 hover:text-rose-400 p-1 rounded-lg hover:bg-rose-500/10 transition cursor-pointer flex-shrink-0" 
                                            title="Hapus dari keranjang"
                                            aria-label="Hapus item">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </div>

                                    <div class="text-[11px] text-gray-400 flex items-center space-x-2 mt-0.5">
                                        <span class="text-gray-300" x-text="item.variant_name"></span>
                                        <span>•</span>
                                        <span class="font-mono text-gray-500" x-text="'SKU: ' + item.sku"></span>
                                    </div>

                                    <div class="mt-1 flex items-baseline space-x-2">
                                        <span class="text-xs font-extrabold text-emerald-400 font-mono" x-text="'Rp ' + Number(item.price).toLocaleString('id-ID')"></span>
                                        <span x-show="item.quantity > 1" class="text-[10px] text-gray-500 font-mono" x-text="'(Total: Rp ' + Number(item.price * item.quantity).toLocaleString('id-ID') + ')'"></span>
                                    </div>

                                    <!-- Max Stock Indicator -->
                                    <div x-show="item.quantity >= item.stock" class="mt-1 text-[10px] text-amber-400 font-semibold flex items-center space-x-1">
                                        <span>⚠️ Stok Maksimal (<span x-text="item.stock"></span> pcs)</span>
                                    </div>

                                    <!-- Stepper Controls -->
                                    <div class="mt-2.5 flex items-center justify-between">
                                        <div class="flex items-center border border-gray-700/80 rounded-lg bg-gray-900 overflow-hidden">
                                            <button 
                                                type="button" 
                                                @click="$store.cart.updateQty(item.sku, item.quantity - 1)" 
                                                class="w-7 h-7 flex items-center justify-center text-gray-300 hover:text-white hover:bg-gray-800 transition cursor-pointer text-xs font-bold"
                                                aria-label="Kurang satu">
                                                -
                                            </button>
                                            <input 
                                                type="number" 
                                                min="1" 
                                                :max="item.stock" 
                                                :value="item.quantity" 
                                                @change="$store.cart.updateQty(item.sku, $event.target.value)" 
                                                class="w-10 h-7 bg-transparent text-center text-xs font-mono font-bold text-white outline-none border-x border-gray-700/80 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none">
                                            <button 
                                                type="button" 
                                                :disabled="item.quantity >= item.stock" 
                                                @click="$store.cart.updateQty(item.sku, item.quantity + 1)" 
                                                :class="item.quantity >= item.stock ? 'opacity-40 cursor-not-allowed' : 'hover:bg-gray-800 text-gray-300 hover:text-white cursor-pointer'" 
                                                class="w-7 h-7 flex items-center justify-center transition text-xs font-bold"
                                                aria-label="Tambah satu">
                                                +
                                            </button>
                                        </div>

                                        <div class="text-[11px] text-gray-400 font-mono">
                                            <span x-text="'Rp ' + Number(item.price * item.quantity).toLocaleString('id-ID')"></span>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Footer -->
                <div class="p-5 sm:p-6 border-t border-gray-800 bg-gray-900/90 backdrop-blur-md space-y-3.5">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-400 font-medium">Subtotal</span>
                        <span class="font-mono font-black text-emerald-400 text-lg" x-text="'Rp ' + Number($store.cart.subtotal).toLocaleString('id-ID')"></span>
                    </div>

                    <div class="flex items-start space-x-2 text-[11px] text-gray-400 bg-gray-950/60 p-2.5 rounded-xl border border-gray-800/80">
                        <span class="text-emerald-400 text-xs flex-shrink-0">ℹ️</span>
                        <span>Ongkir multi-ekspedisi real-time (JNE/J&T/SiCepat/GoSend) dihitung otomatis pada langkah pembayaran.</span>
                    </div>

                    <button 
                        type="button" 
                        :disabled="$store.cart.count === 0"
                        @click="$store.cart.openCheckout()"
                        :class="$store.cart.count === 0 ? 'bg-gray-800 text-gray-500 cursor-not-allowed border border-gray-700' : 'bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-black hover:shadow-emerald-500/25 active:scale-95 shadow-lg shadow-emerald-500/20 cursor-pointer'"
                        class="w-full py-3.5 rounded-xl text-xs sm:text-sm font-black transition-all flex items-center justify-center space-x-2">
                        <span>Lanjut ke Pembayaran</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                        </svg>
                    </button>
                </div>

            </div>
        </div>
    </div>

    <!-- 3-Step Checkout Modal with Live 3PL Rates & Payment -->
    <div 
        x-show="$store.cart.isCheckoutOpen" 
        x-cloak
        x-init="$watch('$store.cart.isCheckoutOpen', open => {
            if (open && $store.auth.user) {
                if (!$store.cart.recipientName) $store.cart.recipientName = $store.auth.user.name || '';
                if (!$store.cart.recipientEmail) $store.cart.recipientEmail = $store.auth.user.email || '';
                if (!$store.cart.recipientPhone && $store.auth.user.phone) $store.cart.recipientPhone = $store.auth.user.phone || '';
            }
        })"
        @keydown.window.escape="if (!$store.cart.isCheckingOut) $store.cart.isCheckoutOpen = false"
        class="fixed inset-0 z-50 overflow-y-auto" 
        role="dialog" 
        aria-modal="true" 
        aria-labelledby="checkout-modal-title">

        <!-- Modal Backdrop -->
        <div 
            x-show="$store.cart.isCheckoutOpen"
            x-transition:enter="ease-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            @click="if (!$store.cart.isCheckingOut) $store.cart.isCheckoutOpen = false"
            class="fixed inset-0 bg-black/80 backdrop-blur-md transition-opacity"></div>

        <!-- Modal Dialog Container -->
        <div class="min-h-full flex items-center justify-center p-3 sm:p-6 relative z-10">
            <div 
                x-show="$store.cart.isCheckoutOpen"
                x-transition:enter="ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave="ease-in duration-200"
                x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                class="w-full max-w-2xl bg-[#0e1524] border border-gray-800 rounded-3xl shadow-2xl overflow-hidden text-gray-100 flex flex-col my-6">
                
                <!-- Stepper Header -->
                <div class="px-5 py-4 sm:px-6 sm:py-5 border-b border-gray-800 bg-gray-900/70 backdrop-blur-md">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center space-x-2.5">
                            <span class="w-8 h-8 rounded-xl bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-center text-emerald-400">📦</span>
                            <div>
                                <h2 id="checkout-modal-title" class="text-base sm:text-lg font-extrabold text-white">Checkout & Pengiriman Multi-Ekspedisi</h2>
                                <p class="text-[11px] text-gray-400">Jaminan suku cadang presisi & garansi kirim MotoVault</p>
                            </div>
                        </div>
                        <button 
                            type="button" 
                            :disabled="$store.cart.isCheckingOut"
                            @click="$store.cart.isCheckoutOpen = false" 
                            class="p-1.5 rounded-xl text-gray-400 hover:text-white hover:bg-gray-800 transition cursor-pointer"
                            aria-label="Tutup Checkout">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <!-- 3-Step Progress Header -->
                    <div class="grid grid-cols-3 gap-2 text-xs">
                        <!-- Step 1 Button/Pill -->
                        <div 
                            @click="if ($store.cart.checkoutStep > 1 && !$store.cart.isCheckingOut) $store.cart.checkoutStep = 1"
                            class="flex items-center space-x-2 p-2 rounded-xl transition border cursor-pointer"
                            :class="$store.cart.checkoutStep === 1 ? 'bg-emerald-500/15 border-emerald-500/40 text-emerald-300 shadow-sm' : ($store.cart.checkoutStep > 1 ? 'bg-gray-900 border-gray-800 text-emerald-400 hover:border-gray-700' : 'bg-gray-900/40 border-gray-800/60 text-gray-500')">
                            <div class="w-6 h-6 rounded-full flex items-center justify-center font-bold text-xs flex-shrink-0"
                                :class="$store.cart.checkoutStep === 1 ? 'bg-emerald-500 text-black' : ($store.cart.checkoutStep > 1 ? 'bg-emerald-500/20 text-emerald-400' : 'bg-gray-800 text-gray-400')">
                                <template x-if="$store.cart.checkoutStep > 1">
                                    <span>✓</span>
                                </template>
                                <template x-if="$store.cart.checkoutStep <= 1">
                                    <span>1</span>
                                </template>
                            </div>
                            <div class="leading-tight truncate">
                                <div class="font-bold text-[11px] truncate">1. Alamat & Kontak</div>
                                <div class="text-[9px] opacity-75 hidden sm:block">Data Penerima</div>
                            </div>
                        </div>

                        <!-- Step 2 Button/Pill -->
                        <div 
                            @click="if ($store.cart.checkoutStep > 2 && !$store.cart.isCheckingOut) $store.cart.checkoutStep = 2"
                            class="flex items-center space-x-2 p-2 rounded-xl transition border"
                            :class="[
                                $store.cart.checkoutStep === 2 ? 'bg-emerald-500/15 border-emerald-500/40 text-emerald-300 shadow-sm' : ($store.cart.checkoutStep > 2 ? 'bg-gray-900 border-gray-800 text-emerald-400 hover:border-gray-700 cursor-pointer' : 'bg-gray-900/40 border-gray-800/60 text-gray-500')
                            ]">
                            <div class="w-6 h-6 rounded-full flex items-center justify-center font-bold text-xs flex-shrink-0"
                                :class="$store.cart.checkoutStep === 2 ? 'bg-emerald-500 text-black' : ($store.cart.checkoutStep > 2 ? 'bg-emerald-500/20 text-emerald-400' : 'bg-gray-800 text-gray-400')">
                                <template x-if="$store.cart.checkoutStep > 2">
                                    <span>✓</span>
                                </template>
                                <template x-if="$store.cart.checkoutStep <= 2">
                                    <span>2</span>
                                </template>
                            </div>
                            <div class="leading-tight truncate">
                                <div class="font-bold text-[11px] truncate">2. Ekspedisi 3PL</div>
                                <div class="text-[9px] opacity-75 hidden sm:block">Pilih Kurir & Tarif</div>
                            </div>
                        </div>

                        <!-- Step 3 Button/Pill -->
                        <div 
                            class="flex items-center space-x-2 p-2 rounded-xl transition border"
                            :class="$store.cart.checkoutStep === 3 ? 'bg-emerald-500/15 border-emerald-500/40 text-emerald-300 shadow-sm' : 'bg-gray-900/40 border-gray-800/60 text-gray-500'">
                            <div class="w-6 h-6 rounded-full flex items-center justify-center font-bold text-xs flex-shrink-0"
                                :class="$store.cart.checkoutStep === 3 ? 'bg-emerald-500 text-black' : 'bg-gray-800 text-gray-400'">
                                <span>3</span>
                            </div>
                            <div class="leading-tight truncate">
                                <div class="font-bold text-[11px] truncate">3. Pembayaran</div>
                                <div class="text-[9px] opacity-75 hidden sm:block">Metode & Bayar</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STEP 1: Customer Details & Shipping Destination -->
                <div x-show="$store.cart.checkoutStep === 1" class="p-5 sm:p-6 space-y-4">
                    <!-- Auth State Banner -->
                    <template x-if="$store.auth.isAuthenticated">
                        <div class="flex items-center justify-between p-3 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-xs">
                            <div class="flex items-center space-x-2">
                                <span>👤</span>
                                <span>Masuk sebagai <strong class="text-white" x-text="$store.auth.user?.name"></strong> (<span x-text="$store.auth.user?.email"></span>)</span>
                            </div>
                            <span class="text-[10px] px-2 py-0.5 rounded-md bg-emerald-500/20 text-emerald-400 font-bold">Terverifikasi</span>
                        </div>
                    </template>
                    <template x-if="!$store.auth.isAuthenticated">
                        <div class="flex items-start space-x-2.5 p-3.5 rounded-2xl bg-gray-900/90 border border-gray-800 text-gray-400 text-xs leading-relaxed">
                            <span class="text-emerald-400 text-sm flex-shrink-0">💡</span>
                            <div>
                                <span class="font-bold text-gray-200">Checkout Instan Tanpa Ribet:</span>
                                <span> Akun customer akan dibuatkan otomatis menggunakan nama, email & no. HP di bawah sehingga Anda bisa langsung memantau resi pengiriman dan status transaksi.</span>
                            </div>
                        </div>
                    </template>

                    <!-- Form Inputs -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                        <div>
                            <label class="block text-xs font-bold text-gray-300 mb-1.5">Nama Lengkap Penerima <span class="text-rose-400">*</span></label>
                            <input 
                                type="text" 
                                x-model="$store.cart.recipientName" 
                                placeholder="Misal: Budi Santoso"
                                class="w-full bg-gray-900 border border-gray-700/80 rounded-xl px-3.5 py-2.5 text-xs text-white placeholder-gray-500 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none transition">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-300 mb-1.5">Nomor WhatsApp / HP <span class="text-rose-400">*</span></label>
                            <input 
                                type="tel" 
                                x-model="$store.cart.recipientPhone" 
                                placeholder="Misal: 081234567890"
                                class="w-full bg-gray-900 border border-gray-700/80 rounded-xl px-3.5 py-2.5 text-xs text-white placeholder-gray-500 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none transition">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-300 mb-1.5">Email Aktif (untuk invoice & update pengiriman) <span class="text-rose-400">*</span></label>
                        <input 
                            type="email" 
                            x-model="$store.cart.recipientEmail" 
                            placeholder="Misal: budi@example.com"
                            class="w-full bg-gray-900 border border-gray-700/80 rounded-xl px-3.5 py-2.5 text-xs text-white placeholder-gray-500 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none transition">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-300 mb-1.5">Alamat Lengkap Pengiriman <span class="text-rose-400">*</span></label>
                        <textarea 
                            x-model="$store.cart.recipientAddress" 
                            rows="3" 
                            placeholder="Nama Jalan, Nomor Rumah/Gedung, RT/RW, Kelurahan, Kecamatan, Kota / Kabupaten..."
                            class="w-full bg-gray-900 border border-gray-700/80 rounded-xl px-3.5 py-2.5 text-xs text-white placeholder-gray-500 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none transition"></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-300 mb-1.5">Kodepos Tujuan (5 Digit) <span class="text-rose-400">*</span></label>
                        <div class="relative w-full sm:w-48">
                            <input 
                                type="text" 
                                maxlength="10"
                                x-model="$store.cart.postalCode" 
                                placeholder="Misal: 12190"
                                class="w-full bg-gray-900 border border-gray-700/80 rounded-xl px-3.5 py-2.5 text-xs font-mono font-bold text-white placeholder-gray-500 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none transition">
                            <span class="absolute right-3 top-2.5 text-gray-500 text-xs">📮</span>
                        </div>
                        <p class="text-[11px] text-gray-400 mt-1">Kodepos menentukan akurasi perhitungan tarif kurir 3PL (JNE, J&T, SiCepat, GoSend).</p>
                    </div>

                    <!-- Step 1 Actions -->
                    <div class="pt-4 border-t border-gray-800 flex items-center justify-between">
                        <button 
                            type="button" 
                            @click="$store.cart.isCheckoutOpen = false; $store.cart.isDrawerOpen = true" 
                            class="px-4 py-2.5 rounded-xl bg-gray-900 hover:bg-gray-800 border border-gray-700 text-xs font-semibold text-gray-300 transition cursor-pointer">
                            ← Kembali ke Keranjang
                        </button>

                        <button 
                            type="button" 
                            :disabled="$store.cart.isLoadingRates"
                            @click="fetchShippingRatesForCheckout()" 
                            class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-black text-xs font-black shadow-lg shadow-emerald-500/20 active:scale-95 transition flex items-center space-x-2 cursor-pointer">
                            <template x-if="$store.cart.isLoadingRates">
                                <span class="flex items-center space-x-1.5">
                                    <svg class="w-4 h-4 animate-spin text-black" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span>Mencari Ekspedisi...</span>
                                </span>
                            </template>
                            <template x-if="!$store.cart.isLoadingRates">
                                <span class="flex items-center space-x-1.5">
                                    <span>Lanjut: Pilih Ekspedisi</span>
                                    <span>→</span>
                                </span>
                            </template>
                        </button>
                    </div>
                </div>

                <!-- STEP 2: 3PL Courier Selection -->
                <div x-show="$store.cart.checkoutStep === 2" class="p-5 sm:p-6 space-y-4">
                    <!-- Route Information Header -->
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 p-3.5 rounded-2xl bg-gray-950/60 border border-gray-800 text-xs">
                        <div class="flex items-center space-x-2">
                            <span class="text-base">🚚</span>
                            <div>
                                <span class="text-gray-400">Pengiriman ke Kodepos:</span>
                                <strong class="text-white font-mono ml-1" x-text="$store.cart.postalCode"></strong>
                            </div>
                        </div>
                        <div class="text-[11px] text-gray-400">
                            Asal: <span class="text-emerald-400 font-semibold">Hub Jakarta Selatan (12190)</span>
                        </div>
                    </div>

                    <!-- Loading Spinner for Rates -->
                    <div x-show="$store.cart.isLoadingRates" class="py-12 text-center space-y-3">
                        <div class="inline-flex items-center justify-center w-12 h-12 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400">
                            <svg class="w-6 h-6 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </div>
                        <div class="text-xs text-gray-200 font-bold">Menghubungi Rate Engine Ekspedisi 3PL...</div>
                        <div class="text-[11px] text-gray-500">Mengkalkulasi ongkir real-time JNE, J&T, SiCepat, dan GoSend berdasarkan berat paket</div>
                    </div>

                    <!-- Rates Card List -->
                    <div x-show="!$store.cart.isLoadingRates && $store.cart.shippingRates.length > 0" class="space-y-2.5 max-h-[340px] overflow-y-auto pr-1">
                        <template x-for="rate in $store.cart.shippingRates" :key="rate.courier_code + '-' + rate.service_code">
                            <div 
                                @click="$store.cart.selectedCourier = { courier: rate.courier_code, service: rate.service_code, name: rate.courier_name, service_name: rate.service_name, cost: Number(rate.price), formatted_price: rate.formatted_price, etd: rate.etd }"
                                :class="($store.cart.selectedCourier && $store.cart.selectedCourier.courier === rate.courier_code && $store.cart.selectedCourier.service === rate.service_code) ? 'bg-emerald-950/40 border-emerald-500 ring-1 ring-emerald-500 shadow-md shadow-emerald-500/10' : 'bg-gray-900/80 border-gray-800 hover:border-gray-700'"
                                class="p-3.5 rounded-2xl border transition-all cursor-pointer flex items-center justify-between group">
                                
                                <div class="flex items-center space-x-3">
                                    <!-- Radio Indicator -->
                                    <div class="w-4 h-4 rounded-full border flex items-center justify-center transition flex-shrink-0"
                                        :class="($store.cart.selectedCourier && $store.cart.selectedCourier.courier === rate.courier_code && $store.cart.selectedCourier.service === rate.service_code) ? 'border-emerald-400 bg-emerald-500' : 'border-gray-600 bg-gray-800'">
                                        <div x-show="($store.cart.selectedCourier && $store.cart.selectedCourier.courier === rate.courier_code && $store.cart.selectedCourier.service === rate.service_code)" class="w-1.5 h-1.5 rounded-full bg-black"></div>
                                    </div>

                                    <!-- Courier Badge -->
                                    <div class="px-2.5 py-1 rounded-xl text-[10px] font-black uppercase tracking-wider font-mono flex-shrink-0"
                                        :class="{
                                            'bg-blue-500/20 text-blue-300 border border-blue-500/30': rate.courier_code === 'jne',
                                            'bg-rose-500/20 text-rose-300 border border-rose-500/30': rate.courier_code === 'jnt',
                                            'bg-amber-500/20 text-amber-300 border border-amber-500/30': rate.courier_code === 'sicepat',
                                            'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30': rate.courier_code === 'gosend'
                                        }"
                                        x-text="rate.courier_code">
                                    </div>

                                    <div>
                                        <div class="flex items-center space-x-1.5">
                                            <span class="text-xs font-bold text-white" x-text="rate.courier_name"></span>
                                            <span class="text-[11px] font-mono font-bold text-emerald-400" x-text="rate.service_code"></span>
                                            <span x-show="rate.is_instant" class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-amber-500/20 text-amber-400 border border-amber-500/30">⚡ Instant</span>
                                        </div>
                                        <div class="text-[11px] text-gray-400 mt-0.5">
                                            <span x-text="rate.service_name"></span> • Estimasi Tiba: <strong class="text-gray-200" x-text="rate.etd"></strong>
                                        </div>
                                    </div>
                                </div>

                                <div class="text-right flex-shrink-0 pl-2">
                                    <div class="text-xs sm:text-sm font-black text-emerald-400 font-mono" x-text="rate.formatted_price"></div>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Empty Rates State -->
                    <div x-show="!$store.cart.isLoadingRates && $store.cart.shippingRates.length === 0" class="text-center py-10 glass-panel rounded-2xl border border-gray-800 space-y-2">
                        <div class="text-2xl">⚠️</div>
                        <div class="text-xs text-white font-bold">Tidak ada ekspedisi yang melayani rute ini</div>
                        <p class="text-[11px] text-gray-400 max-w-sm mx-auto">Pastikan kodepos tujuan terisi dengan benar atau gunakan kodepos alternatif.</p>
                        <button 
                            type="button" 
                            @click="$store.cart.checkoutStep = 1" 
                            class="px-4 py-2 rounded-xl bg-gray-800 hover:bg-gray-700 text-xs text-white font-semibold mt-2 cursor-pointer">
                            ← Periksa Kodepos
                        </button>
                    </div>

                    <!-- Step 2 Actions -->
                    <div class="pt-4 border-t border-gray-800 flex items-center justify-between">
                        <button 
                            type="button" 
                            @click="$store.cart.checkoutStep = 1" 
                            class="px-4 py-2.5 rounded-xl bg-gray-900 hover:bg-gray-800 border border-gray-700 text-xs font-semibold text-gray-300 transition cursor-pointer">
                            ← Kembali ke Alamat
                        </button>

                        <button 
                            type="button" 
                            :disabled="!$store.cart.selectedCourier" 
                            @click="$store.cart.checkoutStep = 3" 
                            :class="!$store.cart.selectedCourier ? 'opacity-40 cursor-not-allowed bg-gray-800 text-gray-400' : 'bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-black shadow-lg shadow-emerald-500/20 active:scale-95 cursor-pointer'"
                            class="px-6 py-2.5 rounded-xl text-xs font-black transition flex items-center space-x-1.5">
                            <span>Lanjut ke Pembayaran</span>
                            <span>→</span>
                        </button>
                    </div>
                </div>

                <!-- STEP 3: Payment & Confirmation -->
                <div x-show="$store.cart.checkoutStep === 3" class="p-5 sm:p-6 space-y-4">
                    <!-- Review Recipient & Shipping Summary -->
                    <div class="p-4 rounded-2xl bg-gray-950/60 border border-gray-800/90 text-xs space-y-2">
                        <div class="flex items-center justify-between border-b border-gray-800/80 pb-2">
                            <div class="flex items-center space-x-2">
                                <span class="text-emerald-400">📍</span>
                                <span class="font-bold text-white">Tujuan Pengiriman</span>
                            </div>
                            <button 
                                type="button" 
                                @click="$store.cart.checkoutStep = 1" 
                                class="text-[11px] text-emerald-400 hover:underline font-semibold cursor-pointer">
                                Ubah Alamat
                            </button>
                        </div>
                        <div class="text-gray-300 leading-relaxed">
                            <strong class="text-white" x-text="$store.cart.recipientName"></strong> 
                            (<span x-text="$store.cart.recipientPhone"></span>) • <span x-text="$store.cart.recipientEmail"></span><br>
                            <span class="text-gray-400" x-text="$store.cart.recipientAddress"></span> 
                            (Kodepos: <span class="font-mono text-gray-200" x-text="$store.cart.postalCode"></span>)
                        </div>
                        <div class="pt-2 border-t border-gray-800/80 flex items-center justify-between text-[11px]">
                            <div class="text-gray-400">
                                Ekspedisi: <strong class="text-white" x-text="$store.cart.selectedCourier?.name"></strong> (<span class="font-mono text-emerald-400" x-text="$store.cart.selectedCourier?.service"></span>) • Estimasi: <span x-text="$store.cart.selectedCourier?.etd"></span>
                            </div>
                            <button 
                                type="button" 
                                @click="$store.cart.checkoutStep = 2" 
                                class="text-[11px] text-emerald-400 hover:underline font-semibold cursor-pointer">
                                Ubah Kurir
                            </button>
                        </div>
                    </div>

                    <!-- Items Breakdown List -->
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between text-xs text-gray-400 font-bold uppercase tracking-wider">
                            <span>Item Pesanan (<span x-text="$store.cart.count"></span> pcs)</span>
                            <span>Subtotal</span>
                        </div>
                        <div class="divide-y divide-gray-800/70 max-h-36 overflow-y-auto pr-1 bg-gray-900/50 rounded-2xl border border-gray-800/80 p-3">
                            <template x-for="item in $store.cart.items" :key="item.sku">
                                <div class="py-2 first:pt-0 last:pb-0 flex items-center justify-between text-xs">
                                    <div class="truncate max-w-[240px] sm:max-w-xs">
                                        <div class="font-bold text-white truncate" x-text="item.name"></div>
                                        <div class="text-[10px] text-gray-400">
                                            <span x-text="item.variant_name"></span> • <span class="font-mono" x-text="item.sku"></span> × <span class="font-bold text-emerald-400" x-text="item.quantity"></span>
                                        </div>
                                    </div>
                                    <div class="text-right font-mono font-bold text-gray-200">
                                        <span x-text="'Rp ' + Number(item.price * item.quantity).toLocaleString('id-ID')"></span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Payment Methods -->
                    <div class="space-y-2">
                        <label class="block text-xs font-bold text-gray-300 uppercase tracking-wider">Pilih Metode Pembayaran:</label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                            <!-- QRIS -->
                            <label 
                                @click="$store.cart.paymentMethod = 'qris'"
                                :class="$store.cart.paymentMethod === 'qris' ? 'bg-emerald-950/40 border-emerald-500 ring-1 ring-emerald-500' : 'bg-gray-900/80 border-gray-800 hover:border-gray-700'"
                                class="p-3 rounded-2xl border transition cursor-pointer flex items-start space-x-2.5">
                                <input type="radio" name="checkout_payment" value="qris" :checked="$store.cart.paymentMethod === 'qris'" class="mt-1 text-emerald-500 focus:ring-emerald-500 bg-gray-800 border-gray-700">
                                <div>
                                    <div class="text-xs font-bold text-white flex items-center space-x-1.5">
                                        <span>QRIS Instant</span>
                                        <span class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-emerald-500/20 text-emerald-400">Otomatis</span>
                                    </div>
                                    <p class="text-[10px] text-gray-400 mt-0.5">GoPay, ShopeePay, Dana, LinkAja, & BCA</p>
                                </div>
                            </label>

                            <!-- BCA VA -->
                            <label 
                                @click="$store.cart.paymentMethod = 'bca_va'"
                                :class="$store.cart.paymentMethod === 'bca_va' ? 'bg-emerald-950/40 border-emerald-500 ring-1 ring-emerald-500' : 'bg-gray-900/80 border-gray-800 hover:border-gray-700'"
                                class="p-3 rounded-2xl border transition cursor-pointer flex items-start space-x-2.5">
                                <input type="radio" name="checkout_payment" value="bca_va" :checked="$store.cart.paymentMethod === 'bca_va'" class="mt-1 text-emerald-500 focus:ring-emerald-500 bg-gray-800 border-gray-700">
                                <div>
                                    <div class="text-xs font-bold text-white">BCA Virtual Account</div>
                                    <p class="text-[10px] text-gray-400 mt-0.5">Verifikasi otomatis 24 jam via m-BCA / KlikBCA</p>
                                </div>
                            </label>

                            <!-- Mandiri VA -->
                            <label 
                                @click="$store.cart.paymentMethod = 'mandiri_va'"
                                :class="$store.cart.paymentMethod === 'mandiri_va' ? 'bg-emerald-950/40 border-emerald-500 ring-1 ring-emerald-500' : 'bg-gray-900/80 border-gray-800 hover:border-gray-700'"
                                class="p-3 rounded-2xl border transition cursor-pointer flex items-start space-x-2.5">
                                <input type="radio" name="checkout_payment" value="mandiri_va" :checked="$store.cart.paymentMethod === 'mandiri_va'" class="mt-1 text-emerald-500 focus:ring-emerald-500 bg-gray-800 border-gray-700">
                                <div>
                                    <div class="text-xs font-bold text-white">Mandiri Virtual Account</div>
                                    <p class="text-[10px] text-gray-400 mt-0.5">Verifikasi otomatis via Livin' by Mandiri</p>
                                </div>
                            </label>

                            <!-- Bank Transfer -->
                            <label 
                                @click="$store.cart.paymentMethod = 'bank_transfer'"
                                :class="$store.cart.paymentMethod === 'bank_transfer' ? 'bg-emerald-950/40 border-emerald-500 ring-1 ring-emerald-500' : 'bg-gray-900/80 border-gray-800 hover:border-gray-700'"
                                class="p-3 rounded-2xl border transition cursor-pointer flex items-start space-x-2.5">
                                <input type="radio" name="checkout_payment" value="bank_transfer" :checked="$store.cart.paymentMethod === 'bank_transfer'" class="mt-1 text-emerald-500 focus:ring-emerald-500 bg-gray-800 border-gray-700">
                                <div>
                                    <div class="text-xs font-bold text-white">Transfer Bank Manual</div>
                                    <p class="text-[10px] text-gray-400 mt-0.5">Konfirmasi manual via WhatsApp CS MotoVault</p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Error Alert Banner -->
                    <div x-show="$store.cart.checkoutError" x-cloak class="p-3.5 bg-rose-500/15 border border-rose-500/40 rounded-2xl text-xs text-rose-300 flex items-start space-x-2.5">
                        <span class="text-rose-400 font-bold text-sm flex-shrink-0">⚠️</span>
                        <div class="flex-1">
                            <span class="font-bold">Gagal memproses transaksi:</span>
                            <div class="mt-0.5" x-text="$store.cart.checkoutError"></div>
                        </div>
                    </div>

                    <!-- Price Summary Box -->
                    <div class="p-4 rounded-2xl bg-gray-900/90 border border-gray-800 space-y-2 text-xs">
                        <div class="flex items-center justify-between text-gray-400">
                            <span>Subtotal Produk</span>
                            <span class="font-mono font-bold text-white" x-text="'Rp ' + Number($store.cart.subtotal).toLocaleString('id-ID')"></span>
                        </div>
                        <div class="flex items-center justify-between text-gray-400">
                            <span>Biaya Pengiriman (<span x-text="($store.cart.selectedCourier?.courier || '3PL').toUpperCase()"></span> - <span x-text="$store.cart.selectedCourier?.service || 'REG'"></span>)</span>
                            <span class="font-mono font-bold text-white" x-text="'Rp ' + Number($store.cart.shippingCost).toLocaleString('id-ID')"></span>
                        </div>
                        <div class="pt-2.5 border-t border-gray-800 flex items-center justify-between text-sm font-extrabold">
                            <span class="text-white">Total Pembayaran</span>
                            <span class="font-mono text-emerald-400 text-lg sm:text-xl" x-text="'Rp ' + Number($store.cart.totalAmount).toLocaleString('id-ID')"></span>
                        </div>
                    </div>

                    <!-- Step 3 Actions -->
                    <div class="pt-4 border-t border-gray-800 flex items-center justify-between">
                        <button 
                            type="button" 
                            :disabled="$store.cart.isCheckingOut"
                            @click="$store.cart.checkoutStep = 2" 
                            class="px-4 py-2.5 rounded-xl bg-gray-900 hover:bg-gray-800 border border-gray-700 text-xs font-semibold text-gray-300 transition cursor-pointer">
                            ← Kembali ke Ekspedisi
                        </button>

                        <button 
                            type="button" 
                            :disabled="$store.cart.isCheckingOut" 
                            @click="submitCheckout()" 
                            class="px-6 sm:px-8 py-3 rounded-xl bg-gradient-to-r from-emerald-500 via-teal-400 to-emerald-500 hover:from-emerald-400 hover:to-teal-300 text-black text-xs sm:text-sm font-black shadow-xl shadow-emerald-500/25 active:scale-95 transition flex items-center space-x-2 cursor-pointer">
                            <template x-if="$store.cart.isCheckingOut">
                                <span class="flex items-center space-x-2">
                                    <svg class="w-4 h-4 animate-spin text-black" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span>Memproses Pesanan...</span>
                                </span>
                            </template>
                            <template x-if="!$store.cart.isCheckingOut">
                                <span class="flex items-center space-x-1.5">
                                    <span>Bayar Sekarang (</span>
                                    <span class="font-mono font-black" x-text="'Rp ' + Number($store.cart.totalAmount).toLocaleString('id-ID')"></span>
                                    <span>) →</span>
                                </span>
                            </template>
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Order Success Modal -->
    <div 
        x-show="$store.cart.isOrderSuccessOpen && $store.cart.checkoutSuccessData" 
        x-cloak
        @keydown.window.escape="$store.cart.isOrderSuccessOpen = false"
        class="fixed inset-0 z-50 overflow-y-auto" 
        role="dialog" 
        aria-modal="true" 
        aria-labelledby="success-modal-title">

        <!-- Backdrop -->
        <div 
            x-show="$store.cart.isOrderSuccessOpen"
            x-transition:enter="ease-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            @click="$store.cart.isOrderSuccessOpen = false"
            class="fixed inset-0 bg-black/85 backdrop-blur-md transition-opacity"></div>

        <div class="min-h-full flex items-center justify-center p-4 relative z-10">
            <div 
                x-show="$store.cart.isOrderSuccessOpen"
                x-transition:enter="ease-out duration-300"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="ease-in duration-200"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                class="w-full max-w-lg bg-[#0e1524] border border-emerald-500/40 rounded-3xl shadow-2xl overflow-hidden text-gray-100 p-6 sm:p-8 text-center space-y-5 my-6">
                
                <!-- Success Icon -->
                <div class="w-16 h-16 rounded-full bg-emerald-500/20 border-2 border-emerald-400 text-emerald-400 flex items-center justify-center text-3xl mx-auto shadow-lg shadow-emerald-500/25">
                    ✓
                </div>

                <div class="space-y-1.5">
                    <h2 id="success-modal-title" class="text-xl sm:text-2xl font-black text-white">Pesanan Berhasil Dibuat!</h2>
                    <p class="text-xs text-gray-400">Invoice transaksi resmi MotoVault Enterprise telah diterbitkan.</p>
                </div>

                <!-- Order Number Badge -->
                <div class="p-4 rounded-2xl bg-gray-900/90 border border-gray-800 space-y-2">
                    <div class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Nomor Pesanan / Invoice:</div>
                    <div class="font-mono font-black text-emerald-400 text-base sm:text-lg tracking-wide" x-text="$store.cart.checkoutSuccessData?.order_number"></div>
                    <div class="flex items-center justify-center space-x-2 text-[11px] text-gray-400 pt-1 border-t border-gray-800">
                        <span>Total: <strong class="text-white font-mono" x-text="'Rp ' + Number($store.cart.checkoutSuccessData?.total_amount || 0).toLocaleString('id-ID')"></strong></span>
                        <span>•</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/10 text-amber-400 border border-amber-500/30 uppercase" x-text="$store.cart.checkoutSuccessData?.payment_status || 'UNPAID'"></span>
                    </div>
                </div>

                <!-- Payment Instruction Preview -->
                <div class="p-4 rounded-2xl bg-gray-950/80 border border-gray-800 text-left text-xs space-y-2">
                    <div class="flex items-center justify-between text-[11px] font-bold text-gray-300 uppercase">
                        <span>Instruksi Pembayaran</span>
                        <span class="font-mono text-emerald-400 uppercase" x-text="$store.cart.checkoutSuccessData?.payment_method || 'QRIS'"></span>
                    </div>
                    <p class="text-gray-400 text-[11px] leading-relaxed">
                        Silakan selesaikan pembayaran sesuai nominal tepat di atas. Pesanan akan otomatis diproses ke tahap pengepakan dan penyerahan ke kurir 3PL setelah pembayaran terverifikasi.
                    </p>
                </div>

                <!-- Action CTA Buttons -->
                <div class="pt-2 flex flex-col sm:flex-row gap-2.5">
                    <button 
                        type="button" 
                        @click="$store.cart.isOrderSuccessOpen = false; document.getElementById('katalog-produk')?.scrollIntoView({behavior: 'smooth'})" 
                        class="flex-1 py-3 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-black text-xs font-black shadow-lg shadow-emerald-500/20 active:scale-95 transition cursor-pointer">
                        Belanja Suku Cadang Lainnya
                    </button>
                    <button 
                        type="button" 
                        @click="$store.cart.isOrderSuccessOpen = false" 
                        class="px-4 py-3 rounded-xl bg-gray-900 hover:bg-gray-800 border border-gray-700 text-xs font-bold text-gray-300 transition cursor-pointer">
                        Tutup
                    </button>
                </div>

            </div>
        </div>
    </div>

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

        // 6. Checkout Flow: Fetch Live 3PL Shipping Rates
        async function fetchShippingRatesForCheckout() {
            const cart = Alpine.store('cart');
            
            // Validate Step 1 Inputs
            const name = (cart.recipientName || '').trim();
            const phone = (cart.recipientPhone || '').trim();
            const email = (cart.recipientEmail || '').trim();
            const address = (cart.recipientAddress || '').trim();
            const postalCode = (cart.postalCode || '').trim();

            if (!name) {
                alert('Silakan masukkan nama lengkap penerima.');
                return false;
            }
            if (!phone) {
                alert('Silakan masukkan nomor WhatsApp / HP penerima.');
                return false;
            }
            if (!email || !email.includes('@') || !email.includes('.')) {
                alert('Silakan masukkan alamat email yang valid.');
                return false;
            }
            if (!address) {
                alert('Silakan masukkan alamat lengkap pengiriman.');
                return false;
            }
            if (!postalCode || postalCode.length < 5) {
                alert('Silakan masukkan kode pos tujuan 5 digit yang valid.');
                return false;
            }

            cart.isLoadingRates = true;
            cart.checkoutError = '';
            cart.save();
            cart.checkoutStep = 2; // Move to step 2 to show loading / rates

            try {
                const payload = {
                    destination_postal_code: postalCode,
                    items: cart.items.map(item => {
                        const it = {
                            quantity: item.quantity,
                            weight_gram: item.weight_gram || 500
                        };
                        if (item.variant_id) {
                            it.variant_id = item.variant_id;
                        }
                        return it;
                    })
                };

                const res = await fetch('/api/v1/shipping/rates', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });

                const json = await res.json();

                if (res.ok && json.success && json.data && Array.isArray(json.data.rates)) {
                    cart.shippingRates = json.data.rates;

                    // Preserve existing courier choice if present in new rates, else select first
                    if (cart.shippingRates.length > 0) {
                        const existingMatch = cart.selectedCourier && cart.shippingRates.find(r => 
                            r.courier_code === cart.selectedCourier.courier && r.service_code === cart.selectedCourier.service
                        );
                        if (existingMatch) {
                            cart.selectedCourier = {
                                courier: existingMatch.courier_code,
                                service: existingMatch.service_code,
                                name: existingMatch.courier_name,
                                service_name: existingMatch.service_name,
                                cost: Number(existingMatch.price),
                                formatted_price: existingMatch.formatted_price,
                                etd: existingMatch.etd
                            };
                        } else {
                            const first = cart.shippingRates[0];
                            cart.selectedCourier = {
                                courier: first.courier_code,
                                service: first.service_code,
                                name: first.courier_name,
                                service_name: first.service_name,
                                cost: Number(first.price),
                                formatted_price: first.formatted_price,
                                etd: first.etd
                            };
                        }
                    } else {
                        cart.selectedCourier = null;
                    }
                    return true;
                } else {
                    const errMessage = json.message || 'Gagal mengambil tarif pengiriman ekspedisi.';
                    cart.checkoutError = errMessage;
                    alert(errMessage);
                    return false;
                }
            } catch (err) {
                const netErr = 'Terjadi gangguan jaringan saat menghitung tarif pengiriman: ' + err.message;
                cart.checkoutError = netErr;
                alert(netErr);
                return false;
            } finally {
                cart.isLoadingRates = false;
            }
        }

        // 7. Checkout Flow: Submit Final Order & Payment Confirmation
        async function submitCheckout() {
            const cart = Alpine.store('cart');
            const auth = Alpine.store('auth');

            if (cart.items.length === 0) {
                alert('Keranjang belanja Anda kosong.');
                return;
            }
            if (!cart.selectedCourier) {
                alert('Silakan pilih salah satu opsi ekspedisi 3PL terlebih dahulu.');
                cart.checkoutStep = 2;
                return;
            }

            cart.isCheckingOut = true;
            cart.checkoutError = '';

            try {
                // Ensure authentication token exists (auto-register if guest)
                let token = auth.token;
                if (!token) {
                    const regPayload = {
                        name: (cart.recipientName || 'Pelanggan MotoVault').trim(),
                        email: (cart.recipientEmail || '').trim(),
                        phone: (cart.recipientPhone || '081234567890').trim(),
                        password: 'Password123!',
                        password_confirmation: 'Password123!'
                    };

                    const regRes = await fetch('/api/v1/auth/register', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify(regPayload)
                    });

                    const regJson = await regRes.json();

                    if (regRes.ok && regJson.success && regJson.data?.token) {
                        auth.setAuth(regJson.data.token, regJson.data.user);
                        token = regJson.data.token;
                    } else if (regJson.errors?.email || (regJson.message && regJson.message.toLowerCase().includes('sudah terdaftar'))) {
                        // Email already registered: attempt fallback login with default password
                        const loginRes = await fetch('/api/v1/auth/login', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                email: regPayload.email,
                                password: 'Password123!'
                            })
                        });
                        const loginJson = await loginRes.json();
                        if (loginRes.ok && loginJson.success && loginJson.data?.token) {
                            auth.setAuth(loginJson.data.token, loginJson.data.user);
                            token = loginJson.data.token;
                        } else {
                            throw new Error('Email ' + regPayload.email + ' telah terdaftar dengan password lain. Silakan login terlebih dahulu melalui tombol Masuk di menu atas.');
                        }
                    } else {
                        const errMsg = regJson.errors 
                            ? Object.values(regJson.errors).flat().join(', ') 
                            : (regJson.message || 'Gagal mendaftarkan akun untuk pesanan.');
                        throw new Error(errMsg);
                    }
                }

                const payload = {
                    items: cart.items.map(i => ({ sku: i.sku, quantity: i.quantity })),
                    shipping_address: `${cart.recipientName} (${cart.recipientPhone}) - ${cart.recipientAddress}, Kodepos ${cart.postalCode}`,
                    destination_postal_code: cart.postalCode.trim(),
                    courier_code: cart.selectedCourier.courier,
                    courier_service: cart.selectedCourier.service,
                    shipping_cost: Number(cart.shippingCost),
                    payment_method: cart.paymentMethod || 'qris'
                };

                const res = await fetch('/api/v1/orders/checkout', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'Authorization': `Bearer ${token}`
                    },
                    body: JSON.stringify(payload)
                });

                const json = await res.json();

                if (!res.ok || !json.success) {
                    throw new Error(json.message || 'Checkout gagal diproses oleh server.');
                }

                // Save order number to recent orders in localStorage
                try {
                    const recentOrders = JSON.parse(localStorage.getItem('motovault_recent_orders') || '[]');
                    const orderNumber = json.data?.order_number;
                    if (orderNumber && !recentOrders.includes(orderNumber)) {
                        recentOrders.unshift(orderNumber);
                        localStorage.setItem('motovault_recent_orders', JSON.stringify(recentOrders));
                    }
                } catch (e) {
                    console.error('Failed to update recent orders', e);
                }

                // Success handling
                cart.checkoutSuccessData = json.data;
                cart.clearCart();
                cart.isCheckoutOpen = false;
                cart.isOrderSuccessOpen = true;
            } catch (err) {
                cart.checkoutError = err.message;
            } finally {
                cart.isCheckingOut = false;
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

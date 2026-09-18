<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Vehicle;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $vehicles = Vehicle::orderBy('brand')->orderBy('model')->get();
    $categories = Category::orderBy('name')->get();
    $warehouses = Warehouse::orderBy('name')->get();
    $products = Product::with(['category', 'variants', 'compatibleVehicles'])
        ->where('is_active', true)
        ->latest('id')
        ->get();
    $stats = [
        'vehicles_count' => Vehicle::count(),
        'products_count' => Product::count(),
        'categories_count' => Category::count(),
        'warehouses_count' => Warehouse::count(),
    ];

    return view('welcome', compact('vehicles', 'categories', 'warehouses', 'products', 'stats'));
});

Route::get('/pos', function () {
    $categories = Category::orderBy('name')->get();
    $variants = ProductVariant::with(['product.category'])->where('stock', '>', 0)->get();
    $warehouses = Warehouse::where('is_active', true)->get();

    return view('pos', compact('categories', 'variants', 'warehouses'));
})->name('pos.index');

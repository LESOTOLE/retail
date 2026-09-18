<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Channel untuk staff toko & admin kasir
Broadcast::channel('orders.staff', function ($user) {
    return $user->canManageStore();
}, ['guards' => ['web', 'sanctum']]);

// Channel pesanan personal pelanggan
Broadcast::channel('orders.user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId || $user->canManageStore();
}, ['guards' => ['web', 'sanctum']]);

// Channel monitoring stok kritis untuk staff/admin
Broadcast::channel('inventory.staff', function ($user) {
    return $user->canManageStore();
}, ['guards' => ['web', 'sanctum']]);

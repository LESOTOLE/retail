<?php

namespace App\Enums;

/**
 * Peran pengguna sesuai PRD bagian 2 (User Personas & Roles).
 */
enum UserRole: string
{
    case Admin = 'admin';
    case Staff = 'staff';
    case Customer = 'customer';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Super Admin / Owner',
            self::Staff => 'Cashier / Store Staff',
            self::Customer => 'Customer',
        };
    }

    /**
     * Role yang boleh mengelola data master & operasional toko.
     */
    public function isStoreSide(): bool
    {
        return in_array($this, [self::Admin, self::Staff], true);
    }
}

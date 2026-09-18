<?php

namespace App\Exceptions;

use Exception;

/**
 * Dilempar saat stok varian tidak mencukupi ketika proses checkout
 * mengunci stok (PRD bagian 4.4).
 */
class InsufficientStockException extends Exception
{
    /**
     * @param  array<int, array<string, mixed>>  $issues
     */
    public function __construct(
        protected array $issues = [],
        string $message = 'Stok tidak mencukupi untuk beberapa item pesanan.'
    ) {
        parent::__construct($message);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function issues(): array
    {
        return $this->issues;
    }
}

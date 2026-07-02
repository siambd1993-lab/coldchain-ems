<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Kinds of inventory movement. The {@see sign()} drives ledger math: how a
 * movement of a given type changes a lot's remaining quantity.
 */
enum StockMovementType: string
{
    case StockIn    = 'stock_in';
    case StockOut   = 'stock_out';
    case Transfer   = 'transfer';
    case Adjustment = 'adjustment';

    /**
     * The effect on a lot's remaining quantity, per unit of movement quantity.
     *  +1 → increases stock (receipt)
     *  -1 → decreases stock (release)
     *   0 → relocation only; quantity unchanged (transfer)
     *
     * Adjustments are signed by the caller (the movement quantity itself may be
     * negative), so the multiplier is +1 and the sign lives in the amount.
     */
    public function sign(): int
    {
        return match ($this) {
            self::StockIn     => 1,
            self::StockOut    => -1,
            self::Transfer    => 0,
            self::Adjustment  => 1,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::StockIn     => 'Stock In',
            self::StockOut    => 'Stock Out',
            self::Transfer    => 'Transfer',
            self::Adjustment  => 'Adjustment',
        };
    }
}

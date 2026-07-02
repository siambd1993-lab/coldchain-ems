<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Lifecycle states of an invoice. Helpers keep the billing service's state
 * machine readable and prevent illegal transitions (e.g. paying a void invoice).
 */
enum InvoiceStatus: string
{
    case Draft         = 'draft';
    case Issued        = 'issued';
    case PartiallyPaid = 'partially_paid';
    case Paid          = 'paid';
    case Overdue       = 'overdue';
    case Void          = 'void';

    /** Can this invoice still receive payments? */
    public function isPayable(): bool
    {
        return in_array($this, [self::Issued, self::PartiallyPaid, self::Overdue], true);
    }

    /** Is this a finalised (immutable) state? */
    public function isClosed(): bool
    {
        return in_array($this, [self::Paid, self::Void], true);
    }

    /** May the invoice's lines/amounts still be edited? */
    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }
}

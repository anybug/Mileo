<?php

namespace App\Enum;

enum InvoiceStatus: string
{
    case DRAFT = 'Draft';
    case SENT = 'Sent';
    case PAID = 'Paid';
    case CANCELLED = 'Cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Brouillon',
            self::SENT => 'Envoyée',
            self::PAID => 'Payée',
            self::CANCELLED => 'Annulée',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::DRAFT => 'bg-secondary-subtle text-secondary-emphasis',
            self::SENT => 'bg-info-subtle text-info-emphasis',
            self::PAID => 'bg-success-subtle text-success-emphasis',
            self::CANCELLED => 'bg-danger-subtle text-danger-emphasis',
        };
    }

    public function canBeSent(): bool
    {
        return $this === self::DRAFT;
    }
}
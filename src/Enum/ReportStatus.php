<?php

declare(strict_types=1);

namespace App\Enum;

enum ReportStatus: string
{
    case DRAFT = 'Draft';
    case SENT = 'Sent';
    case VALIDATED = 'Validated';
    case INVALIDATED = 'Invalidated';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Brouillon',
            self::SENT => 'En attente de validation',
            self::VALIDATED => 'Validé',
            self::INVALIDATED => 'Invalidé',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::DRAFT => 'secondary',
            self::SENT => 'warning',
            self::VALIDATED => 'success',
            self::INVALIDATED => 'danger',
        };
    }

    /**
     * @return array<string, self>
     */
    public static function choices(): array
    {
        $choices = [];

        foreach (self::cases() as $status) {
            $choices[$status->label()] = $status;
        }

        return $choices;
    }

    /**
     * @return array<string, string>
     */
    public static function badges(): array
    {
        $badges = [];

        foreach (self::cases() as $status) {
            $badges[$status->value] = $status->badge();
        }

        return $badges;
    }
}
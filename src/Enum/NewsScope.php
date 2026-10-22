<?php

namespace App\Enum;

enum NewsScope: string
{
    case ALL = 'all';
    case APP = 'app';
    case TEAM = 'team';
    case CABINET = 'cabinet';

    public function getLabel(): string
    {
        return match ($this) {
            self::ALL => 'Toutes les interfaces',
            self::APP => 'Application',
            self::TEAM => 'Team',
            self::CABINET => 'Cabinet',
        };
    }
}
<?php

namespace App\Enum;

enum PlanCode: string
{
    case FREE = 'free';
    case PRO = 'pro';
    case TEAM = 'team';
    case CABINET = 'cabinet';
}

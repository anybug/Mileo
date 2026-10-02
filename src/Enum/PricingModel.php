<?php

namespace App\Enum;

enum PricingModel: string
{
    /** Free, Pro : prix fixe, la quantité est ignorée. */
    case FLAT = 'flat';

    /** Team (par siège), Cabinet (par client) : prix unitaire × quantité, par paliers. */
    case PER_UNIT = 'per_unit';
}

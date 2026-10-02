<?php

declare(strict_types=1);

namespace App\Service\Billing;

use App\Service\Billing\TeamReportInvoicePricingLine;

final readonly class TeamReportInvoicePricingDetails
{
    /**
     * @param TeamReportInvoicePricingLine[] $lines
     */
    public function __construct(
        public array $lines,
        public int $totalActiveUserMonths,
        public float $totalHt,
        public float $vatRate,
        public float $vatAmount,
        public float $totalTtc,
    ) {
    }
}
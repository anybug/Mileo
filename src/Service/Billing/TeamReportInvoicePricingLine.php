<?php

declare(strict_types=1);

namespace App\Service\Billing;

final readonly class TeamReportInvoicePricingLine
{
    public function __construct(
        public string $periodKey,
        public string $periodLabel,
        public int $activeUserCount,
        public float $unitPriceMonthlyHt,
        public float $totalHt,
    ) {
    }
}
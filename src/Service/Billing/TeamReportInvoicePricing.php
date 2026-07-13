<?php

declare(strict_types=1);

namespace App\Service\Billing;

use App\Entity\Invoice;
use App\Enum\PlanCode;
use App\Repository\PlanRepository;
use App\Service\Billing\TeamReportInvoicePricingDetails;
use App\Service\Billing\TeamReportInvoicePricingLine;
use DateTimeImmutable;
use DateTimeInterface;
use IntlDateFormatter;
use LogicException;

final class TeamReportInvoicePricing
{
    private const VAT_RATE = 20.0;

    public function __construct(
        private readonly PlanRepository $planRepository,
    ) {
    }

    public function computeForInvoice(Invoice $invoice): TeamReportInvoicePricingDetails
    {
        $usersByConcernedMonth = $this->groupActiveUsersByConcernedMonth($invoice);

        if ($usersByConcernedMonth === []) {
            throw new LogicException('Impossible de calculer le prix : aucun rapport rattaché à cette facture.');
        }

        ksort($usersByConcernedMonth);

        $lines = [];
        $totalActiveUserMonths = 0;
        $totalHt = 0.0;

        foreach ($usersByConcernedMonth as $periodKey => $userIds) {
            $activeUserCount = count($userIds);

            if ($activeUserCount === 0) {
                continue;
            }

            $unitPriceMonthlyHt = $this->findUnitPriceForQuantity($activeUserCount);
            $lineTotalHt = round($activeUserCount * $unitPriceMonthlyHt, 2);

            $lines[] = new TeamReportInvoicePricingLine(
                periodKey: $periodKey,
                periodLabel: $this->formatPeriodLabel($periodKey),
                activeUserCount: $activeUserCount,
                unitPriceMonthlyHt: $unitPriceMonthlyHt,
                totalHt: $lineTotalHt,
            );

            $totalActiveUserMonths += $activeUserCount;
            $totalHt += $lineTotalHt;
        }

        $totalHt = round($totalHt, 2);
        $vatAmount = round($totalHt * self::VAT_RATE / 100, 2);
        $totalTtc = round($totalHt + $vatAmount, 2);

        return new TeamReportInvoicePricingDetails(
            lines: $lines,
            totalActiveUserMonths: $totalActiveUserMonths,
            totalHt: $totalHt,
            vatRate: self::VAT_RATE,
            vatAmount: $vatAmount,
            totalTtc: $totalTtc,
        );
    }

    /**
     * @return array<string, array<int, true>>
     */
    private function groupActiveUsersByConcernedMonth(Invoice $invoice): array
    {
        $usersByMonth = [];

        foreach ($invoice->getReports() as $report) {
            $user = $report->getUser();

            if ($user === null || $user->getId() === null) {
                continue;
            }

            $concernedDate = $report->getStartDate();

            if (!$concernedDate instanceof DateTimeInterface) {
                $concernedDate = $report->getCreatedAt();
            }

            if (!$concernedDate instanceof DateTimeInterface) {
                continue;
            }

            $periodKey = $concernedDate->format('Y-m');

            $usersByMonth[$periodKey][$user->getId()] = true;
        }

        return $usersByMonth;
    }

    private function findUnitPriceForQuantity(int $activeUserCount): float
    {
        $plan = $this->planRepository->findOneBy([
            'code' => PlanCode::TEAM,
        ]);

        if ($plan === null) {
            throw new LogicException('Impossible de calculer le prix : le plan Team est introuvable.');
        }

        $tierQuantity = max($activeUserCount, 3);

        foreach ($plan->getPriceTiers() as $tier) {
            if (!$tier->matches($tierQuantity)) {
                continue;
            }

            if ($tier->isQuoteOnly()) {
                throw new LogicException(sprintf(
                    'Le tarif Team pour %d utilisateurs actifs sur un mois est sur devis.',
                    $activeUserCount,
                ));
            }

            if ($tier->getUnitPriceYearly() === null) {
                throw new LogicException('Le palier Team trouvé n’a pas de prix annuel configuré.');
            }

            return (float) $tier->getUnitPriceMonthlyDisplay();
        }

        throw new LogicException(sprintf(
            'Aucun palier tarifaire Team ne correspond à %d utilisateurs actifs.',
            $activeUserCount,
        ));
    }

    private function formatPeriodLabel(string $periodKey): string
    {
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $periodKey.'-01');

        if (!$date instanceof DateTimeInterface) {
            return $periodKey;
        }

        $formatter = new IntlDateFormatter(
            'fr_FR',
            IntlDateFormatter::NONE,
            IntlDateFormatter::NONE,
            null,
            null,
            'MMMM yyyy',
        );

        $label = $formatter->format($date);

        if ($label === false) {
            return $periodKey;
        }

        return ucfirst($label);
    }
}
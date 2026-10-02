<?php
// src/Service/MistralPromptBuilder.php
namespace App\Service;

use App\Entity\Report;
use App\Entity\ReportLine;

/** Abandon de cette fonctionnalité pour l'instant: duplication de trajets beaucoup plus fiable en PHP qu'en IA ! 
 * TODO: utiliser l'IA pour d'autres fonctionnalités
*/

class MistralPromptBuilder
{
    public function buildPrompt(string $actionType, Report $report, array $parameters): string
    {
        switch ($actionType) {
            case 'duplicate_week':
                return $this->buildDuplicateWeekPrompt($report, $parameters);
            case 'copy_trip':
                return $this->buildCopyTripPrompt($report, $parameters);
            case 'repeat_monthly':
                return $this->buildRepeatMonthlyPrompt($report, $parameters);
            default:
                throw new \InvalidArgumentException("Type d'action non supporté : $actionType");
        }
    }

    private function buildDuplicateWeekPrompt(Report $report, array $params): string
    {
        $sourceWeek = $params['source_week'];
        $targetOption = $params['target_option'];
        $sourceDate = new \DateTime($sourceWeek);
        $sourceWeekStart = $sourceDate->format('d/m/Y');
        $sourceWeekEnd = (clone $sourceDate)->modify('+6 days')->format('d/m/Y');
        $sourceMonthName = $sourceDate->format('F Y');

        // Récupérer les trajets avec leur jour de la semaine
        $trips = [];
        foreach ($this->getTripsForWeek($report, $sourceWeek) as $trip) {
            $tripDate = new \DateTime($trip['date']);
            $trips[] = array_merge($trip, [
                'jour' => $tripDate->format('N') // 'N' retourne 1 (lundi) à 7 (dimanche) en string, mais (int) le convertit
            ]);
        }

        if ($targetOption === 'next_week') {
            $targetWeekStart = (clone $sourceDate)->modify('+7 days')->format('d/m/Y');
            $targetWeekEnd = (clone $sourceDate)->modify('+13 days')->format('d/m/Y');
            $targetDescription = "la semaine suivante (du $targetWeekStart au $targetWeekEnd)";
        } else { // full_month
            $monthName = $sourceDate->format('F Y');
            $targetDescription = "toutes les semaines suivantes du mois de $sourceMonthName";
        }

        return sprintf(
            "Contexte :
    - Rapport kilométrique pour %s (du %s au %s).
    - Semaine source : du %s (%s) au %s (%s).
    - Semaines cibles : %s.

    Instructions :
    1. Pour CHAQUE trajet de la semaine source, crée une copie dans CHAQUE semaine cible en respectant :
    - Le même jour de la semaine (ex: un trajet du %s doit être copié vers tous les %s des semaines cibles).
    - Les adresses de départ/arrivée EXACTES (copie conforme, sans modification).
    - Les mêmes valeurs pour km, is_return, km_total, vehicule_id, amount et commentaire.

    2. Ne retourne PAS les trajets de la semaine source.
    3. Format de réponse : UNIQUEMENT les trajets générés pour les semaines cibles.

    Trajets de la semaine source (à ne PAS inclure dans la réponse) :
    %s

    Format de réponse attendu (JSON strict) :
    [{\"date\": \"YYYY-MM-DD\", \"depart\": \"Adresse Départ (copie conforme)\", \"arrivee\": \"Adresse Arrivée (copie conforme)\", \"km\": X, \"is_return\": BOOLEAN, \"km_total\": X, \"vehicule_id\": X, \"amount\": X, \"commentaire\": \"...\"}]",
            $sourceMonthName,
            $report->getStartDate()->format('d/m/Y'),
            $report->getEndDate()->format('d/m/Y'),
            $sourceWeekStart,
            $this->getFrenchDayName($sourceDate->format('N')),
            $sourceWeekEnd,
            $this->getFrenchDayName((clone $sourceDate)->modify('+6 days')->format('N')),
            $targetDescription,
            !empty($trips) ? $this->getFrenchDayName($trips[0]['jour']) : 'lundi',
            !empty($trips) ? strtolower($this->getFrenchDayName($trips[0]['jour'])) : 'lundi',
            json_encode($trips, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    private function getFrenchDayName($dayNumber): string
    {
        $dayNumber = (int)$dayNumber; // Conversion forcée en entier

        $days = [
            1 => 'lundi',
            2 => 'mardi',
            3 => 'mercredi',
            4 => 'jeudi',
            5 => 'vendredi',
            6 => 'samedi',
            7 => 'dimanche'
        ];

        return $days[$dayNumber] ?? 'lundi';
    }

    // Copier un trajet spécifique vers des dates cibles
    private function buildCopyTripPrompt(Report $report, array $params): string
    {
        $trip = $params['trip'];
        $targetDates = $params['target_dates'];

        $formattedDates = array_map(function($date) {
            return (new \DateTime($date))->format('d/m/Y');
        }, $targetDates);

        return sprintf(
            "Copie le trajet du %s (véhicule %d, %s → %s, %d km, %d km totaux, aller-retour : %s, montant : %s€, %s) vers les dates suivantes : %s.
            Format de réponse attendu (JSON strict) :
            [{\"date\": \"YYYY-MM-DD\", \"depart\": \"Adresse Départ\", \"arrivee\": \"Adresse Arrivée\", \"km\": X, \"is_return\": BOOLEAN, \"km_total\": X, \"vehicule_id\": X, \"amount\": X, \"commentaire\": \"...\"}]",
            (new \DateTime($trip['date']))->format('d/m/Y'),
            $trip['vehicule_id'],
            $trip['depart'],
            $trip['arrivee'],
            $trip['km'],
            $trip['is_return'] ? 'oui' : 'non',
            $trip['km_total'],
            $trip['amount'],
            $trip['commentaire'],
            implode(', ', $formattedDates)
        );
    }

    // Répéter un trajet toutes les semaines du mois
    private function buildRepeatMonthlyPrompt(Report $report, array $params): string
    {
        $trip = $params['trip'];
        $sourceDate = new \DateTime($trip['date']);
        $monthName = $sourceDate->format('F Y');
        $dayOfWeek = $sourceDate->format('l'); // "lundi", "mardi", etc.

        return sprintf(
            "Répète le trajet du %s (véhicule %d, %s → %s, %d km, aller-retour, %d km totaux : %s, montant : %s€, %s) chaque %s du mois de %s. N'affiche pas le trajet source dans le résultat vu qu'on l'a déjà.
            Format de réponse attendu (JSON strict) :
            [{\"date\": \"YYYY-MM-DD\", \"depart\": \"Adresse Départ\", \"arrivee\": \"Adresse Arrivée\", \"km\": X, \"is_return\": BOOLEAN, \"km_total\": X, \"vehicule_id\": X, \"amount\": X, \"commentaire\": \"...\"}]",
            $sourceDate->format('d/m/Y'),
            $trip['vehicule_id'],
            $trip['depart'],
            $trip['arrivee'],
            $trip['km'],
            $trip['km_total'],
            $trip['is_return'] ? 'oui' : 'non',
            $trip['amount'],
            $trip['commentaire'],
            $dayOfWeek,
            $monthName
        );
    }

    // Récupère les trajets d'une semaine donnée
    private function getTripsForWeek(Report $report, string $weekDate): array
    {
        $startDate = new \DateTime($weekDate);
        $endDate = (clone $startDate)->modify('+6 days');
        $trips = [];

        foreach ($report->getLines() as $line) {
            $tripDate = $line->getTravelDate();
            if ($tripDate >= $startDate && $tripDate <= $endDate) {
                $trips[] = [
                    'date' => $tripDate->format('Y-m-d'),
                    'depart' => $line->getStartAdress(),
                    'arrivee' => $line->getEndAdress(),
                    'km' => $line->getKm(),
                    'is_return' => $line->getIsReturn(),
                    'km_total' => $line->getKmTotal(),
                    'vehicule_id' => $line->getVehicule()->getId(),
                    'amount' => $line->getAmount(),
                    'commentaire' => $line->getComment(),
                ];
            }
        }

        return $trips;
    }
}

<?php

namespace App\Controller\App\Filter;

use App\Entity\Report;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\ChoiceFilterType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Security;

class ReportYearFilterType extends AbstractType
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security
    ) {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $periodData = $this->getPeriodsData();

        $resolver->setDefaults([
            'comparison_type_options' => [
                'type' => 'choice',
            ],
            'value_type' => ChoiceType::class,
            'value_type_options' => [
                'choices' => $periodData['choices'],

                /*
                 * Désactive les anciennes périodes pour les comptes gratuits.
                 */
                'choice_attr' => static function (
                    mixed $choice,
                    string $label,
                    mixed $value
                ) use ($periodData): array {
                    $isLocked = in_array(
                        (string) $value,
                        $periodData['locked_values'],
                        true
                    );

                    if (!$isLocked) {
                        return [];
                    }

                    return [
                        'disabled' => 'disabled',
                        'class' => 'tm-history-period-locked',
                        'title' => 'Cette période est disponible avec la version Pro.',
                    ];
                },

                'attr' => [
                    'class' => 'js-report-period-filter',
                ],
            ],
        ]);
    }

    public function getParent(): string
    {
        return ChoiceFilterType::class;
    }

    /**
     * @return array{
     *     choices: array<string, string>,
     *     locked_values: list<string>
     * }
     */
    private function getPeriodsData(): array
    {
        /** @var User|null $user */
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return [
                'choices' => [],
                'locked_values' => [],
            ];
        }

        $reports = $this->entityManager
            ->getRepository(Report::class)
            ->getReportsForUser();

        $choices = [];
        $lockedValues = [];

        /*
         * La version gratuite donne accès aux deux dernières années glissantes.
         */
        $historyLimit = new \DateTimeImmutable('-2 years');
        $isFreeUser = !$user->canAddVehicule();

        foreach ($reports as $report) {
            $period = $user->generateBalancePeriodByReport($report);

            $label = $user->getTranslattedBalancePeriod($period);
            $value = $user->getFormattedBalancePeriod($period);

            $isLocked = $isFreeUser
                && $report->getEndDate() instanceof \DateTimeInterface
                && $report->getEndDate() < $historyLimit;

            /*
             * On ajoute un cadenas dans le libellé pour que la limitation
             * soit visible même si le navigateur colore mal les <option>.
             */
            if ($isLocked) {
                $label .= ' 🔒';
                $lockedValues[] = $value;
            }

            $choices[$label] = $value;
        }

        if ($choices === []) {
            $period = $user->getCurrentFiscalPeriod();

            $choices[
                $user->getTranslattedBalancePeriod($period)
            ] = $user->getFormattedBalancePeriod($period);
        }

        return [
            'choices' => $choices,
            'locked_values' => array_values(
                array_unique($lockedValues)
            ),
        ];
    }
}
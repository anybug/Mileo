<?php

namespace App\Controller\App\Filter;

use App\Entity\ReportLine;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\ChoiceFilterType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Security;

class LineDateFilterType extends AbstractType
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security
    ) {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $data = $this->getChoicesData();

        $resolver->setDefaults([
            'comparison_type_options' => [
                'type' => 'choice',
            ],
            'value_type' => ChoiceType::class,
            'value_type_options' => [
                'choices' => $data['choices'],

                'choice_attr' => static function (
                    mixed $choice,
                    string $label,
                    mixed $value
                ) use ($data): array {
                    $isLocked = in_array(
                        (string) $value,
                        $data['locked_values'],
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
            ],
        ]);
    }

    public function getParent(): string
    {
        return ChoiceFilterType::class;
    }

    private function getChoicesData(): array
    {
        /** @var User|null $user */
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return [
                'choices' => [],
                'locked_values' => [],
            ];
        }

        $reportLines = $this->entityManager
            ->getRepository(ReportLine::class)
            ->getLineForUser();

        $choices = [];
        $lockedValues = [];

        $historyLimit = new \DateTimeImmutable('-2 years');
        $isFreeUser = !$user->canAddVehicule();

        foreach ($reportLines as $line) {
            $travelDate = $line->getTravelDate();

            if (!$travelDate) {
                continue;
            }

            $fmt = new \IntlDateFormatter(
                'fr_FR',
                \IntlDateFormatter::FULL,
                \IntlDateFormatter::FULL,
                'Europe/Paris',
                \IntlDateFormatter::GREGORIAN,
                'LLLL'
            );

            $month = ucfirst($fmt->format($travelDate));

            $value = $travelDate->format('F/Y');
            $label = $month.' '.$travelDate->format('Y');

            $isLocked =
                $isFreeUser
                && $travelDate < $historyLimit;

            if ($isLocked) {
                $label .= ' 🔒';
                $lockedValues[] = $value;
            }

            $choices[
                $travelDate->format('Y')
            ][$label] = $value;
        }

        /*
        * On garantit que l'année courante existe toujours
        * dans le filtre, même sans trajet.
        */
        $currentYear = (new \DateTimeImmutable())->format('Y');

        if (!isset($choices[$currentYear])) {
            $choices[$currentYear] = [];
        }

        /*
        * Années les plus récentes en premier.
        */
        krsort($choices);

        return [
            'choices' => $choices,
            'locked_values' => array_values(
                array_unique($lockedValues)
            ),
        ];
    }
}
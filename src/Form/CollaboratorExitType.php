<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\NotBlank;

final class CollaboratorExitType extends AbstractType
{
    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ): void {
        $minimumExitDate = $options['minimum_exit_date'];

        $constraints = [
            new NotBlank(
                message: 'La date de sortie est obligatoire.'
            ),
        ];

        if ($minimumExitDate instanceof \DateTimeInterface) {
            $constraints[] = new GreaterThan(
                value: $minimumExitDate,
                message: sprintf(
                    'La date de sortie doit être postérieure au %s.',
                    $minimumExitDate->format('d/m/Y')
                )
            );
        }

        $builder
            ->add('exitDate', DateType::class, [
                'label' => 'Date de sortie de l’effectif',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => true,
                'constraints' => $constraints,
                'attr' => [
                    'min' => $minimumExitDate instanceof \DateTimeInterface
                        ? \DateTimeImmutable::createFromInterface($minimumExitDate)
                            ->modify('+1 day')
                            ->format('Y-m-d')
                        : null,
                ],
                'help_attr' => [
                    'class' => 'form-text text-muted',
                ],
                'help' => $minimumExitDate instanceof \DateTimeInterface
                    ? sprintf(
                        'Première date autorisée : %s.',
                        \DateTimeImmutable::createFromInterface($minimumExitDate)
                            ->modify('+1 day')
                            ->format('d/m/Y')
                    )
                    : 'Sélectionnez la date effective de sortie.',
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Confirmer la sortie',
                'attr' => [
                    'class' => 'btn btn-danger',
                ],
            ]);
    }

    public function configureOptions(
        OptionsResolver $resolver
    ): void {
        $resolver->setDefaults([
            /*
             * Le formulaire retourne directement un tableau :
             *
             * [
             *     'exitDate' => DateTimeImmutable,
             * ]
             */
            'data_class' => null,
            'minimum_exit_date' => null,
        ]);

        $resolver->setAllowedTypes(
            'minimum_exit_date',
            [
                'null',
                \DateTimeInterface::class,
            ]
        );
    }
}
<?php

declare(strict_types=1);

namespace Payplug\Bundle\PaymentBundle\Form\Type;

use Oro\Bundle\LocaleBundle\Form\Type\LocalizedFallbackValueCollectionType;
use Payplug\Bundle\PaymentBundle\Constant\PayplugSettingsConstant;
use Payplug\Bundle\PaymentBundle\Entity\PayplugSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class PayplugSettingsType extends AbstractType
{
    public const BLOCK_PREFIX = 'payplug_settings';

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add(
                'labels',
                LocalizedFallbackValueCollectionType::class,
                [
                    'label' => 'payplug.settings.labels.label',
                    'required' => true,
                ]
            )
            ->add(
                'shortLabels',
                LocalizedFallbackValueCollectionType::class,
                [
                    'label' => 'payplug.settings.short_labels.label',
                    'required' => false,
                ]
            )
            ->add(
                'apiKeyTest',
                HiddenType::class
            )
            ->add(
                'apiKeyLive',
                HiddenType::class
            )
        ;

        $formModifier = function (FormInterface $form, PayplugSettings $payplugSettings = null): void {
            $form->add(
                'login',
                TextType::class,
                [
                    'label' => 'payplug.settings.login.label',
                    'required' => true,
                    'constraints' => [new NotBlank()],
                    'disabled' => $payplugSettings->isConnected() ? true : false,
                ]
            );

            if (!$payplugSettings->isConnected() && null !== $payplugSettings->getId()) {
                $form->add(
                    'password',
                    PasswordType::class,
                    [
                        'label' => 'payplug.settings.password.label',
                        'mapped' => false,
                    ]
                );
            }

            if ($payplugSettings->isConnected()) {
                $form->add(
                    'mode',
                    ChoiceType::class,
                    [
                        'label' => 'payplug.settings.mode.label',
                        'choices' => [
                            'Test' => PayplugSettingsConstant::MODE_TEST,
                            'Live' => PayplugSettingsConstant::MODE_LIVE,
                        ],
                        'empty_data' => PayplugSettingsConstant::MODE_TEST,
                        'choice_attr' => function ($choice, $key, $value) use ($payplugSettings) {
                            return !$payplugSettings->isLiveModeAvailable() && PayplugSettingsConstant::MODE_LIVE == $value ?
                                ['disabled' => 'disabled'] : []
                            ;
                        },
                    ]
                );
                $form->add(
                    'debugMode',
                    CheckboxType::class,
                    [
                        'label' => 'payplug.settings.debug_mode.label',
                        'tooltip' => 'payplug.settings.debug_mode.tooltip',
                    ]
                );
            }
        };

        $builder->addEventListener(
            FormEvents::PRE_SET_DATA,
            function (FormEvent $event) use ($formModifier): void {
                $payplugSettings = $event->getData();

                if (!$payplugSettings) {
                    return;
                }

                $formModifier($event->getForm(), $payplugSettings);
            }
        );
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(
            [
                'data_class' => PayplugSettings::class,
                'allow_extra_fields' => true,
            ]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix(): string
    {
        return self::BLOCK_PREFIX;
    }
}

<?php

declare(strict_types=1);

namespace Payplug\Bundle\PaymentBundle\Validator\Constraints;

use Symfony\Component\Form\Extension\Core\DataTransformer\NumberToLocalizedStringTransformer;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\RangeValidator;

class LocalizedRange extends Range
{
    public function __construct($options = null)
    {
        $transformer = new NumberToLocalizedStringTransformer();

        $options['min'] = $transformer->reverseTransform($options['min']);
        $options['max'] = $transformer->reverseTransform($options['max']);

        parent::__construct($options);
    }

    public function validatedBy(): string
    {
        return RangeValidator::class;
    }
}

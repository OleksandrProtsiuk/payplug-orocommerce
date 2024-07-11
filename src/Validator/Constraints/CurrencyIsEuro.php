<?php

declare(strict_types=1);

namespace Payplug\Bundle\PaymentBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

class CurrencyIsEuro extends Constraint
{
    public $message = 'payplug.constraints.payment_rule.euro_currency';

    public function validatedBy(): string
    {
        return 'payplug.validator.payment_rule.currency_is_euro';
    }

    public function getTargets(): string|array
    {
        return self::CLASS_CONSTRAINT;
    }
}

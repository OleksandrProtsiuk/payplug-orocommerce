<?php

declare(strict_types=1);

namespace Payplug\Bundle\PaymentBundle\Tests\Unit\Method\Config;

use Oro\Bundle\PaymentBundle\Tests\Unit\Method\Config\AbstractPaymentConfigTestCase;
use Payplug\Bundle\PaymentBundle\Constant\PayplugSettingsConstant;
use Payplug\Bundle\PaymentBundle\Method\Config\PayplugConfig;
use Payplug\Bundle\PaymentBundle\Method\Config\PayplugConfigInterface;

/**
 * @internal
 */
class PayplugConfigTest extends AbstractPaymentConfigTestCase
{
    /**
     * @var PayplugConfigInterface
     */
    protected $config;

    public function testGetLogin(): void
    {
        $this->assertSame('login', $this->config->getLogin());
    }

    public function testIsDebugMode(): void
    {
        $this->assertTrue($this->config->isDebugMode());
    }

    public function testGetApiKeyLive(): void
    {
        $this->assertSame('sk_live_43b7e007298f57f7aedee32800a52301', $this->config->getApiKeyLive());
    }

    public function testGetApiKeyTest(): void
    {
        $this->assertSame('sk_test_7c5cb3b54abcf5062f056639e809368c', $this->config->getApiKeyTest());
    }

    public function testGetMode(): void
    {
        $this->assertSame(PayplugSettingsConstant::MODE_LIVE, $this->config->getMode());
    }

    /**
     * {@inheritdoc}
     */
    protected function getPaymentConfig(): \Oro\Bundle\PaymentBundle\Method\Config\PaymentConfigInterface
    {
        $params = [
            PayplugConfig::FIELD_PAYMENT_METHOD_IDENTIFIER => 'test_payment_method_identifier',
            PayplugConfig::FIELD_ADMIN_LABEL => 'test admin label',
            PayplugConfig::FIELD_LABEL => 'test label',
            PayplugConfig::FIELD_SHORT_LABEL => 'test short label',
            PayplugConfig::LOGIN => 'login',
            PayplugConfig::DEBUG_MODE => true,
            PayplugConfig::API_KEY_LIVE => 'sk_live_43b7e007298f57f7aedee32800a52301',
            PayplugConfig::API_KEY_TEST => 'sk_test_7c5cb3b54abcf5062f056639e809368c',
            PayplugConfig::MODE => PayplugSettingsConstant::MODE_LIVE,
        ];

        return new PayplugConfig($params);
    }
}

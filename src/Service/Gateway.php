<?php

namespace Payplug\Bundle\PaymentBundle\Service;

use Oro\Bundle\AddressBundle\Entity\AbstractAddress;
use Oro\Bundle\AddressBundle\Entity\AddressType;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\OrderBundle\Entity\Order;
use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Payplug\Authentication;
use Payplug\Bundle\PaymentBundle\Constant\PayplugSettingsConstant;
use Payplug\Bundle\PaymentBundle\Method\Config\PayplugConfigInterface;
use Payplug\Exception\PayplugException;
use Payplug\Notification;
use Payplug\Payment;
use Payplug\Payplug;
use Payplug\Refund;
use Payplug\Resource\IVerifiableAPIResource;
use Payplug\Resource\Payment as ResourcePayment;
use Payplug\Resource\Refund as ResourceRefund;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

class Gateway
{
    /**
     * @var DoctrineHelper
     */
    private $doctrineHelper;

    /**
     * @var PropertyAccessor $propertyAccessor
     */
    private $propertyAccessor;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var Logger
     */
    private $logger;

    public function __construct(
        DoctrineHelper $doctrineHelper,
        PropertyAccessor $propertyAccessor,
        RouterInterface $router,
        Logger $logger
    ) {
        $this->doctrineHelper = $doctrineHelper;
        $this->propertyAccessor = $propertyAccessor;
        $this->router = $router;
        $this->logger = $logger;
    }


    public function authenticate(string $login, string $password): ?array
    {
        try {
            $response = Authentication::getKeysByLogin($login, $password);
            return $response['httpResponse']['secret_keys'];
        } catch (PayplugException $exception) {
            return [];
        }
    }

    public function getTransactionInfos($paymentId, PayplugConfigInterface $config): ResourcePayment
    {
        $payplugClient = $this->initPayplugClientAndSetDebugModeForLogger($config);

        $this->logger->debug(__METHOD__ . ' BEGIN');

        $payment = Payment::retrieve($paymentId, $payplugClient);

        $this->logger->debug(__METHOD__ . ' END');

        return $payment;
    }

    public function getRefundList($paymentId, PayplugConfigInterface $config): array
    {
        $payplugClient = $this->initPayplugClientAndSetDebugModeForLogger($config);

        $this->logger->debug(__METHOD__ . ' BEGIN');

        $refunds = Refund::listRefunds($paymentId, $payplugClient);

        $this->logger->debug(__METHOD__ . ' END');

        return $refunds;
    }

    public function getMaximumRefundAmount($paymentId, PayplugConfigInterface $config): float
    {
        $payplugClient = $this->initPayplugClientAndSetDebugModeForLogger($config);

        $this->logger->debug(__METHOD__ . ' BEGIN');

        $payment = Payment::retrieve($paymentId, $payplugClient);

        if (!empty($payment->amount_refunded)) {
            return (float) ($payment->amount - $payment->amount_refunded) / 100;
        }

        $this->logger->debug(__METHOD__ . ' END');

        return (float) ($payment->amount / 100);
    }

    public function treatNotify(PayplugConfigInterface $config): IVerifiableAPIResource
    {
        $payplugClient = $this->initPayplugClientAndSetDebugModeForLogger($config);

        $this->logger->debug(__METHOD__ . ' BEGIN');

        $notification = Notification::treat(file_get_contents('php://input'), $payplugClient);

        $this->logger->debug(__METHOD__ . ' END');

        return $notification;
    }

    public function createPayment(PaymentTransaction $paymentTransaction, PayplugConfigInterface $config)
    {
        $payplugClient = $this->initPayplugClientAndSetDebugModeForLogger($config);

        $this->logger->debug(__METHOD__ . ' BEGIN');

        $data = [
            'amount' => (int) ($paymentTransaction->getAmount() * 100),
            'currency' => $paymentTransaction->getCurrency(),
            'shipping' => $this->getAddressValues(AddressType::TYPE_SHIPPING, $paymentTransaction),
            'billing' => $this->getAddressValues(AddressType::TYPE_BILLING, $paymentTransaction),
            'hosted_payment' => $this->getCallbackUrls($paymentTransaction),
            'notification_url' => $this->getNotificationUrl($paymentTransaction)
        ];

        $this->logger->debug('Payment::create from data ' . $this->logger->anonymizeAndJsonEncodeArray($data));
        $payment = Payment::create($data, $payplugClient);

        $this->logger->debug('Payment reference is ' . $payment->id);
        $this->logger->debug('Payment url is ' . $payment->hosted_payment->payment_url);

        $this->logger->debug(__METHOD__ . ' END');

        return $payment;
    }

    public function refundPayment(
        PaymentTransaction $paymentTransaction,
        PayplugConfigInterface $config,
        float $amount
    ): ?ResourceRefund {
        $payplugClient = $this->initPayplugClientAndSetDebugModeForLogger($config);

        $this->logger->debug(__METHOD__ . ' BEGIN');
        $amountToRefund = (int) ($amount * 100);

        $refund = null;

        try {
            $refund = Refund::create($paymentTransaction->getReference(), ['amount' => $amountToRefund], $payplugClient);
        } catch (PayplugException $exception) {
            $this->logger->debug($exception->getHttpResponse());
            throw new \Exception('Refund action cannot be performed');
        } finally {
            $this->logger->debug('Refund reference is ' . $refund->id);
            $this->logger->debug(__METHOD__ . ' END');
        }

        return $refund;
    }

    protected function initPayplugClientAndSetDebugModeForLogger(PayplugConfigInterface $config)
    {
        $this->logger->setDebugMode($config->isDebugMode());

        switch ($config->getMode()) {
            case PayplugSettingsConstant::MODE_LIVE:
                $this->logger->debug('Payplug is in LIVE mode');
                return Payplug::init(['secretKey' => $config->getApiKeyLive()]);

            case PayplugSettingsConstant::MODE_TEST:
            default:
                $this->logger->debug('Payplug is in TEST mode');
                return Payplug::init(['secretKey' => $config->getApiKeyTest()]);
        }
    }

    public function getAddressValues(string $type, PaymentTransaction $paymentTransaction): array
    {
        /** @var Order $entity */
        $entity = $this->doctrineHelper->getEntityReference(
            $paymentTransaction->getEntityClass(),
            $paymentTransaction->getEntityIdentifier()
        );

        if (!$entity) {
            return [];
        }

        switch ($type) {
            case AddressType::TYPE_BILLING:
                $propertyPath = 'billingAddress';
                break;

            case AddressType::TYPE_SHIPPING:
                $propertyPath = 'shippingAddress';
                break;

            default:
                throw new \Exception('Invalid address type');
                break;
        }

        try {
            $address = $this->propertyAccessor->getValue($entity, $propertyPath);
        } catch (NoSuchPropertyException $e) {
            return [];
        }

        if (!$address instanceof AbstractAddress) {
            return [];
        }

        $data = [
            "email" => $this->propertyAccessor->getValue($entity, 'email'),
            "first_name" => $address->getFirstName(),
            "last_name" => $address->getLastName(),
            "address1" => $address->getStreet(),
            "address2" => $address->getStreet2(),
            "city" => $address->getCity(),
            "postcode" => $address->getPostalCode(),
            "country" => $address->getCountryIso2(),
        ];

        if ($type == AddressType::TYPE_SHIPPING) {
            $data["delivery_type"] = "BILLING";
        }

        return $data;
    }

    protected function getCallbackUrls(PaymentTransaction $paymentTransaction): array
    {
        return [
            'cancel_url' => $this->router->generate(
                'oro_payment_callback_error',
                ['accessIdentifier' => $paymentTransaction->getAccessIdentifier()],
                UrlGeneratorInterface::ABSOLUTE_URL
            ),
            'return_url' => $this->router->generate(
                'oro_payment_callback_return',
                ['accessIdentifier' => $paymentTransaction->getAccessIdentifier()],
                UrlGeneratorInterface::ABSOLUTE_URL
            ),
        ];
    }

    protected function getNotificationUrl(PaymentTransaction $paymentTransaction): string
    {
        $routePath = $this->router->generate(
            'oro_payment_callback_notify',
            [
                'accessIdentifier' => $paymentTransaction->getAccessIdentifier(),
                'accessToken' => $paymentTransaction->getAccessToken(),
            ],
            UrlGeneratorInterface::ABSOLUTE_PATH // ABSOLUTE_URL
        );

        return "http://0b56b84e.ngrok.io" . $routePath; // TODO Remove
    }
}

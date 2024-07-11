<?php

declare(strict_types=1);

namespace Payplug\Bundle\PaymentBundle\EventListener\Callback;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Event\AbstractCallbackEvent;
use Oro\Bundle\PaymentBundle\Method\Provider\PaymentMethodProviderInterface;
use Payplug\Bundle\PaymentBundle\Constant\PayplugFailureConstant;
use Payplug\Bundle\PaymentBundle\Method\Payplug;
use Payplug\Bundle\PaymentBundle\Service\Logger;
use Payplug\Bundle\PaymentBundle\Service\RefundManager;
use Payplug\Resource\Payment;
use Payplug\Resource\Refund;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\Session;

class PayplugCheckoutListener
{
    /**
     * @var PaymentMethodProviderInterface
     */
    protected $paymentMethodProvider;

    /**
     * @var RefundManager
     */
    protected $refundManager;

    /**
     * @var \Symfony\Component\HttpFoundation\RequestStack
     */
    protected $session;

    /**
     * @var Logger
     */
    protected $logger;

    public function __construct(
        PaymentMethodProviderInterface $paymentMethodProvider,
        RefundManager $refundManager,
        \Symfony\Component\HttpFoundation\RequestStack $session,
        Logger $logger
    ) {
        $this->paymentMethodProvider = $paymentMethodProvider;
        $this->refundManager = $refundManager;
        $this->session = $session;
        $this->logger = $logger;
    }

    /**
     * @param AbstractCallbackEvent $event
     */
    public function onError(AbstractCallbackEvent $event): void
    {
        $paymentTransaction = $event->getPaymentTransaction();

        if (!$paymentTransaction) {
            $this->logger->error('No payment transaction found onError event');

            return;
        }

        /** @var Payplug $paymentMethod */
        $paymentMethod = $this->paymentMethodProvider->getPaymentMethod($paymentTransaction->getPaymentMethod());

        if (!$paymentMethod) {
            $this->logger->error('No payment method found onError event');

            return;
        }

        $this->logger->setDebugMode($paymentMethod->isDebugMode());
        $this->logger->debug(__METHOD__ . ' BEGIN');

        $paymentTransaction
            ->setSuccessful(false)
            ->setActive(false);

        $this->logger->debug('Payment transaction set to NOT successfull and NOT active');

        try {
            $this->displayPayplugErrorMessage($paymentTransaction);
        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage());
        } finally {
            $this->logger->debug(__METHOD__ . ' END');
        }

        $event->markSuccessful();
    }

    /**
     * @param AbstractCallbackEvent $event
     */
    public function onReturn(AbstractCallbackEvent $event): void
    {
        $paymentTransaction = $event->getPaymentTransaction();

        if (!$paymentTransaction) {
            $this->logger->error('No payment transaction found onReturn event');

            return;
        }

        /** @var Payplug $paymentMethod */
        $paymentMethod = $this->paymentMethodProvider->getPaymentMethod($paymentTransaction->getPaymentMethod());

        if (!$paymentMethod) {
            $this->logger->error('No payment method found onReturn event');
            $this->redirectToFailureUrl($paymentTransaction, $event);

            return;
        }

        $this->logger->setDebugMode($paymentMethod->isDebugMode());
        $this->logger->debug(__METHOD__ . ' BEGIN');

        $payplugResponse = $paymentMethod->getTransactionInfos($paymentTransaction);

        if (!$payplugResponse) {
            $this->logger->error('Payplug API response is empty');
            $this->redirectToFailureUrl($paymentTransaction, $event);

            return;
        }

        if (true === $payplugResponse->is_paid) {
            $this->logger->debug('"is_paid" value from Payplug API is TRUE');

            $paymentTransaction
                ->setSuccessful(true)
                ->setActive(true);

            $this->logger->debug('Payment transaction set to successfull and active');

            $event->markSuccessful();

            $this->logger->debug(__METHOD__ . ' END');

            return;
        }

        $this->redirectToFailureUrl($paymentTransaction, $event);

        $this->logger->debug(__METHOD__ . ' END');
    }

    /**
     * @param AbstractCallbackEvent $event
     */
    public function onNotify(AbstractCallbackEvent $event): void
    {
        $paymentTransaction = $event->getPaymentTransaction();

        if (!$paymentTransaction) {
            $this->logger->error('No payment transaction found onNotify event');

            return;
        }

        /** @var Payplug $paymentMethod */
        $paymentMethod = $this->paymentMethodProvider->getPaymentMethod($paymentTransaction->getPaymentMethod());

        if (!$paymentMethod) {
            $this->logger->error('No payment method found onNotify event');

            return;
        }

        $this->logger->setDebugMode($paymentMethod->isDebugMode());
        $this->logger->debug(__METHOD__ . ' BEGIN');

        $payplugResponse = $paymentMethod->treatNotify();

        if (!$payplugResponse) {
            $this->logger->error('PayPlug API response is empty');

            return;
        }

        switch (true) {
            case $payplugResponse instanceof Payment:
                if (true === $payplugResponse->is_paid) {
                    $this->logger->debug('"is_paid" value from PayPlug API is TRUE');

                    $paymentTransaction
                        ->setSuccessful(true)
                        ->setActive(true);

                    $this->logger->debug('Payment transaction set to successfull and active');
                }
                break;

            case $payplugResponse instanceof Refund:
                $this->refundManager->createRefundTransaction($paymentTransaction, $payplugResponse);
                $this->logger->debug('Refund transaction created with ID: ' . $payplugResponse->id);
                break;

            default:
                throw new \Exception('Unrecognized PayPlug response type');
        }

        $event->markSuccessful();

        $this->logger->debug(__METHOD__ . ' END');
    }

    private function redirectToFailureUrl(
        PaymentTransaction $paymentTransaction,
        AbstractCallbackEvent $event
    ): void {
        $event->stopPropagation();

        $transactionOptions = $paymentTransaction->getTransactionOptions();
        if (!empty($transactionOptions['failureUrl'])) {
            $event->setResponse(new RedirectResponse($transactionOptions['failureUrl']));
        } else {
            $event->markFailed();
        }
    }

    private function displayPayplugErrorMessage(PaymentTransaction $paymentTransaction): void
    {
        $this->logger->debug(__METHOD__ . ' BEGIN');

        /** @var Payplug $paymentMethod */
        $paymentMethod = $this->paymentMethodProvider->getPaymentMethod($paymentTransaction->getPaymentMethod());
        $payplugResponse = $paymentMethod->getTransactionInfos($paymentTransaction);

        if (!$payplugResponse) {
            $this->logger->error('PayPlug API response is empty');

            return;
        }

        if (\in_array($payplugResponse->failure->code, PayplugFailureConstant::getAll(), true)) {
            $this->logger->debug('Warning message sent to customer with code: ' . $payplugResponse->failure->code);
            $this->session->getFlashBag()->add('warning', 'payplug.on_return.' . $payplugResponse->failure->code . '.label');
        } else {
            $this->logger->debug('Unknown failure code from PayPlug API: ' . $payplugResponse->failure->code);
        }

        $this->logger->debug(__METHOD__ . ' END');
    }
}

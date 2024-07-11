<?php

declare(strict_types=1);

namespace Payplug\Bundle\PaymentBundle\Controller;

use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\IntegrationBundle\Entity\Channel;
use Oro\Bundle\IntegrationBundle\Form\Type\ChannelType;
use Oro\Bundle\SecurityBundle\Attribute\CsrfProtection;
use Payplug\Bundle\PaymentBundle\Constant\PayplugSettingsConstant;
use Payplug\Bundle\PaymentBundle\Entity\PayplugSettings;
use Payplug\Bundle\PaymentBundle\Service\Gateway;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ConnectionController extends AbstractController
{
    /**
     * @throws \InvalidArgumentException
     */
    #[\Symfony\Component\Routing\Attribute\Route(path: '/login/{channelId}/', name: 'payplug_login', methods: ['POST'])]
    #[ParamConverter('channel', class: 'OroIntegrationBundle:Channel', options: ['id' => 'channelId'])]
    #[CsrfProtection]
    public function loginAction(
        Request $request,
        Channel $channel = null,
        RouterInterface $router,
        TranslatorInterface $translator,
        Gateway $gateway,
        SessionInterface $session
    ): JsonResponse {
        $form = $this->createForm(
            ChannelType::class,
            $channel
        );
        $form->handleRequest($request);

        /** @var PayplugSettings $settings */
        $settings = $channel->getTransport();

        if (empty($request->get('oro_integration_channel_form')['transport']['password'])) {
            return new JsonResponse([
                'success' => false,
                'message' => $translator->trans('payplug.settings.login.result.no_password.message'),
            ]);
        }

        $apiKeys = $gateway->authenticate(
            $settings->getLogin(),
            $request->get('oro_integration_channel_form')['transport']['password']
        );

        if (empty($apiKeys)) {
            return new JsonResponse([
                'success' => false,
                'message' => $translator->trans('payplug.settings.login.result.error.message'),
            ]);
        }

        $session->getFlashBag()->add(
            'success',
            $translator->trans('payplug.settings.login.result.success.message')
        );

        return new JsonResponse([
            'success' => true,
            'url' => $router->generate('oro_integration_index'),
            'api_key_test' => $apiKeys['test'],
            'api_key_live' => $apiKeys['live'],
        ]);
    }

    /**
     * @throws \InvalidArgumentException
     */
    #[\Symfony\Component\Routing\Attribute\Route(path: '/logout/{channelId}/', name: 'payplug_logout', methods: ['POST'])]
    #[ParamConverter('channel', class: 'OroIntegrationBundle:Channel', options: ['id' => 'channelId'])]
    #[CsrfProtection]
    public function logoutAction(Request $request, Channel $channel = null, SessionInterface $session, TranslatorInterface $translator, ManagerRegistry $managerRegistry): JsonResponse
    {
        $form = $this->createForm(
            ChannelType::class,
            $channel
        );
        $form->handleRequest($request);

        /** @var PayplugSettings $settings */
        $settings = $channel->getTransport();

        $settings->setApiKeyLive(null);
        $settings->setApiKeyTest(null);
        $settings->setMode(PayplugSettingsConstant::MODE_TEST);
        $managerRegistry->getManager()->persist($settings);
        $managerRegistry->getManager()->flush();
        $session->getFlashBag()->add(
            'success',
            $translator->trans('payplug.settings.logout.result.success.message')
        );

        return new JsonResponse([
            'success' => true,
            'disconnect' => true,
        ]);
    }

    public static function getSubscribedServices(): array
    {
        return array_merge(
            [
                TranslatorInterface::class,
                Gateway::class,
            ],
            parent::getSubscribedServices()
        );
    }
}

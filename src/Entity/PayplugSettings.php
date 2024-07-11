<?php

declare(strict_types=1);

namespace Payplug\Bundle\PaymentBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Oro\Bundle\IntegrationBundle\Entity\Transport;
use Oro\Bundle\LocaleBundle\Entity\LocalizedFallbackValue;
use Payplug\Bundle\PaymentBundle\Constant\PayplugSettingsConstant;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: \Payplug\Bundle\PaymentBundle\Entity\Repository\PayplugSettingsRepository::class)]
class PayplugSettings extends Transport
{
    /**
     * @var string
     */
    #[ORM\Column(name: 'payplug_login', type: 'string')]
    protected $login;

    /**
     * @var bool
     */
    #[ORM\Column(name: 'payplug_debug_mode', type: 'boolean', options: ['default' => false])]
    protected $debugMode = false;

    /**
     * @var string
     */
    #[ORM\Column(name: 'payplug_api_key_test', type: 'string', nullable: true)]
    protected $apiKeyTest;

    /**
     * @var string
     */
    #[ORM\Column(name: 'payplug_api_key_live', type: 'string', nullable: true)]
    protected $apiKeyLive;

    /**
     * @var string
     */
    #[ORM\Column(name: 'payplug_mode', type: 'string', nullable: false, options: ['default' => PayplugSettingsConstant::MODE_TEST])]
    protected $mode = PayplugSettingsConstant::MODE_TEST;
    /**
     * @var Collection|LocalizedFallbackValue[]
     */
    #[Assert\NotBlank]
    #[ORM\ManyToMany(targetEntity: \Oro\Bundle\LocaleBundle\Entity\LocalizedFallbackValue::class, cascade: ['ALL'], orphanRemoval: true)]
    #[ORM\JoinTable(name: 'payplug_trans_label', joinColumns: [new ORM\JoinColumn(name: 'transport_id', referencedColumnName: 'id', onDelete: 'CASCADE')], inverseJoinColumns: [new ORM\JoinColumn(name: 'localized_value_id', referencedColumnName: 'id', onDelete: 'CASCADE', unique: true)])]
    private $labels;

    /**
     * @var Collection|LocalizedFallbackValue[]
     */
    #[Assert\NotBlank]
    #[ORM\ManyToMany(targetEntity: \Oro\Bundle\LocaleBundle\Entity\LocalizedFallbackValue::class, cascade: ['ALL'], orphanRemoval: true)]
    #[ORM\JoinTable(name: 'payplug_short_label', joinColumns: [new ORM\JoinColumn(name: 'transport_id', referencedColumnName: 'id', onDelete: 'CASCADE')], inverseJoinColumns: [new ORM\JoinColumn(name: 'localized_value_id', referencedColumnName: 'id', onDelete: 'CASCADE', unique: true)])]
    private $shortLabels;

    /**
     * @var ParameterBag
     */
    private $settings;

    public function __construct()
    {
        $this->labels = new ArrayCollection();
        $this->shortLabels = new ArrayCollection();
    }

    /**
     * @return Collection|LocalizedFallbackValue[]
     */
    public function getLabels()
    {
        return $this->labels;
    }

    /**
     * @param LocalizedFallbackValue $label
     *
     * @return $this
     */
    public function addLabel(LocalizedFallbackValue $label)
    {
        if (!$this->labels->contains($label)) {
            $this->labels->add($label);
        }

        return $this;
    }

    /**
     * @param LocalizedFallbackValue $label
     *
     * @return $this
     */
    public function removeLabel(LocalizedFallbackValue $label)
    {
        if ($this->labels->contains($label)) {
            $this->labels->removeElement($label);
        }

        return $this;
    }

    /**
     * @return Collection|LocalizedFallbackValue[]
     */
    public function getShortLabels()
    {
        return $this->shortLabels;
    }

    /**
     * @param LocalizedFallbackValue $label
     *
     * @return $this
     */
    public function addShortLabel(LocalizedFallbackValue $label)
    {
        if (!$this->shortLabels->contains($label)) {
            $this->shortLabels->add($label);
        }

        return $this;
    }

    /**
     * @param LocalizedFallbackValue $label
     *
     * @return $this
     */
    public function removeShortLabel(LocalizedFallbackValue $label)
    {
        if ($this->shortLabels->contains($label)) {
            $this->shortLabels->removeElement($label);
        }

        return $this;
    }

    /**
     * @return string|null
     */
    public function getLogin(): ?string
    {
        return $this->login;
    }

    /**
     * @param string|null $login
     *
     * @return PayplugSettings
     */
    public function setLogin(?string $login): PayplugSettings
    {
        $this->login = $login;

        return $this;
    }

    /**
     * @return bool
     */
    public function isDebugMode(): bool
    {
        return $this->debugMode;
    }

    /**
     * @param bool $debugMode
     *
     * @return PayplugSettings
     */
    public function setDebugMode(bool $debugMode): PayplugSettings
    {
        $this->debugMode = $debugMode;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getApiKeyTest(): ?string
    {
        return $this->apiKeyTest;
    }

    /**
     * @param string|null $apiKeyTest
     *
     * @return PayplugSettings
     */
    public function setApiKeyTest(?string $apiKeyTest): PayplugSettings
    {
        $this->apiKeyTest = $apiKeyTest;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getApiKeyLive(): ?string
    {
        return $this->apiKeyLive;
    }

    /**
     * @param string|null $apiKeyLive
     *
     * @return PayplugSettings
     */
    public function setApiKeyLive(?string $apiKeyLive): PayplugSettings
    {
        $this->apiKeyLive = $apiKeyLive;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getMode(): ?string
    {
        return $this->mode;
    }

    /**
     * @param string $mode
     *
     * @return PayplugSettings
     */
    public function setMode(string $mode): PayplugSettings
    {
        $this->mode = $mode;

        return $this;
    }

    /**
     * @return ParameterBag
     */
    public function getSettingsBag()
    {
        if (null === $this->settings) {
            $this->settings = new ParameterBag(
                [
                    'labels' => $this->getLabels(),
                    'short_labels' => $this->getShortLabels(),
                    'login' => $this->getLogin(),
                    'debugMode' => $this->isDebugMode(),
                    'apiKeyTest' => $this->getApiKeyTest(),
                    'apiKeyLive' => $this->getApiKeyLive(),
                    'mode' => $this->getMode(),
                ]
            );
        }

        return $this->settings;
    }

    public function isConnected()
    {
        return !empty($this->apiKeyLive) || !empty($this->apiKeyTest);
    }

    public function isLiveModeAvailable()
    {
        return !empty($this->apiKeyLive);
    }
}

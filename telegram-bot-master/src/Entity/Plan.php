<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Class Plan
 * @package App\Entity
 * @ORM\Table(name="plans")
 * @ORM\Entity(repositoryClass="App\Repository\PlanRepository")
 */
class Plan
{
    public const CURRENCY_RUB = 'RUB';
    public const CURRENCY_USD = 'USD';
    public const BASE_CURRENCY = self::CURRENCY_RUB;
    public const ENABLED_CURRENCIES = [self::CURRENCY_RUB, self::CURRENCY_USD];

    public const PROVIDER_TINKOFF = 'TINKOFF';
    public const PROVIDER_CRYPTO = 'CRYPTO';
    public const PROVIDER_STRIPE = 'STRIPE';
    public const CURRENCY_PROVIDERS = [
        self::CURRENCY_RUB => [self::PROVIDER_TINKOFF],
        self::CURRENCY_USD => [self::PROVIDER_STRIPE, self::PROVIDER_CRYPTO],
    ];

    /**
     * @var int
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;
    /**
     * @var string
     * @ORM\Column(type="text", name="bothub_id")
     */
    private $bothubId;
    /**
     * @var string
     * @ORM\Column(type="text")
     */
    private $type;
    /**
     * @var float
     * @ORM\Column(type="float")
     */
    private $price;
    /**
     * @var string
     * @ORM\Column(type="text")
     */
    private $currency;
    /**
     * @var integer
     * @ORM\Column(type="integer")
     */
    private $tokens;

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getBothubId(): string
    {
        return $this->bothubId;
    }

    /**
     * @param string $bothubId
     * @return Plan
     */
    public function setBothubId(string $bothubId): Plan
    {
        $this->bothubId = $bothubId;
        return $this;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @param string $type
     * @return Plan
     */
    public function setType(string $type): Plan
    {
        $this->type = $type;
        return $this;
    }

    /**
     * @return float
     */
    public function getPrice(): float
    {
        return $this->price;
    }

    /**
     * @param float $price
     * @return Plan
     */
    public function setPrice(float $price): Plan
    {
        $this->price = $price;
        return $this;
    }

    /**
     * @return string
     */
    public function getCurrency(): string
    {
        return $this->currency;
    }

    /**
     * @param string $currency
     * @return Plan
     */
    public function setCurrency(string $currency): Plan
    {
        $this->currency = $currency;
        return $this;
    }

    /**
     * @return int
     */
    public function getTokens(): int
    {
        return $this->tokens;
    }

    /**
     * @param int $tokens
     * @return Plan
     */
    public function setTokens(int $tokens): Plan
    {
        $this->tokens = $tokens;
        return $this;
    }
}
<?php

namespace App\Service\DTO;

class PaymentDataDTO
{
    /** @var string */
    public $planId;
    /** @var string */
    public $provider;

    /**
     * PaymentDataDTO constructor.
     * @param string $planId
     * @param string $provider
     */
    public function __construct(string $planId, string $provider)
    {
        $this->planId = $planId;
        $this->provider = $provider;
    }
}
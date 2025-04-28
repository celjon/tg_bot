<?php

namespace App\Service\DTO\Bothub;

class ReferralTemplateDTO
{
    /** @var string */
    public $currency;
    /** @var bool */
    public $disabled;
    /** @var float */
    public $encouragePercentage;
    /** @var string */
    public $id;
    /** @var string */
    public $locale;
    /** @var int */
    public $minWithdrawAmount;
    /** @var string */
    public $name;
    /** @var PlanDTO */
    public $plan;
    /** @var string */
    public $planId;
    /** @var bool */
    public $private;
    /** @var int */
    public $tokens;

    /**
     * ReferralTemplateDTO constructor.
     * @param array $data
     */
    public function __construct(array $data)
    {
        $this->currency             = $data['currency'];
        $this->disabled             = $data['disabled'];
        $this->encouragePercentage  = $data['encouragement_percentage'];
        $this->id                   = $data['id'];
        $this->locale               = $data['locale'];
        $this->minWithdrawAmount    = $data['min_withdraw_amount'];
        $this->name                 = $data['name'];
        $this->plan                 = new PlanDTO($data['plan']);
        $this->planId               = $data['plan_id'];
        $this->private              = $data['private'];
        $this->tokens               = $data['tokens'];
    }
}
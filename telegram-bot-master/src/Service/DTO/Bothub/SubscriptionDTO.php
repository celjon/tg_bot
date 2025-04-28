<?php

namespace App\Service\DTO\Bothub;

use DateTimeImmutable;
use Exception;

class SubscriptionDTO
{
    /** @var string */
    public $paymentPlan;
    /** @var integer */
    public $balance;
    /** @var integer */
    public $creditLimit;
    /** @var DateTimeImmutable */
    public $createdAt;
    /** @var PlanDTO|null */
    public $plan = null;

    /**
     * SubscriptionDTO constructor.
     * @param array $data
     * @throws Exception
     */
    public function __construct(array $data)
    {
        $this->paymentPlan = $data['payment_plan'];
        $this->balance = $data['balance'];
        $this->creditLimit = $data['credit_limit'];
        $this->createdAt = new DateTimeImmutable($data['created_at']);
        if (!empty($data['plan'])) {
            $this->plan = new PlanDTO($data['plan']);
        }
    }
}
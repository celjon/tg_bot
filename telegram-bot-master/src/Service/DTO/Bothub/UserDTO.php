<?php

namespace App\Service\DTO\Bothub;

use Exception;

class UserDTO
{
    /** @var string */
    public $name;
    /** @var SubscriptionDTO */
    public $subscription;

    /**
     * UserDTO constructor.
     * @param array $data
     * @throws Exception
     */
    public function __construct(array $data)
    {
        $subscription = $data['subscription'];
        if (
            !empty($data['employees']) &&
            !empty($data['employees'][0]) &&
            !empty($data['employees'][0]['enterprise']) &&
            !empty($data['employees'][0]['enterprise']['subscription']) &&
            $data['employees'][0]['enterprise']['common_pool']
        ) {
            $subscription = $data['employees'][0]['enterprise']['subscription'];
        }
        $this->name = $data['name'];
        $this->subscription = new SubscriptionDTO($subscription);
    }
}
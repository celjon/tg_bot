<?php

namespace App\Service\DTO\Bothub;

class PlanDTO
{
    /** @var string */
    public $id;
    /** @var string */
    public $type;
    /** @var float */
    public $price;
    /** @var string */
    public $currency;
    /** @var integer */
    public $tokens;

    /**
     * PlanDTO constructor.
     * @param array $data
     */
    public function __construct(array $data)
    {
        $this->id       = $data['id'];
        $this->type     = $data['type'];
        $this->price    = $data['price'];
        $this->currency = $data['currency'];
        $this->tokens   = $data['tokens'];
    }
}
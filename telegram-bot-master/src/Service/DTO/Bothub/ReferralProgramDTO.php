<?php

namespace App\Service\DTO\Bothub;

class ReferralProgramDTO
{
    /** @var int */
    public $amountSpendByUsers;
    /** @var int */
    public $balance;
    /** @var string */
    public $code;
    /** @var bool */
    public $disabled;
    /** @var string */
    public $id;
    /** @var string */
    public $lastWithdrawedAt;
    /** @var string|null */
    public $name;
    /** @var string */
    public $ownerId;
    /** @var int */
    public $participants;
    /** @var ReferralTemplateDTO */
    public $template;
    /** @var string */
    public $templateId;

    /**
     * ReferralProgramDTO constructor.
     * @param array $data
     */
    public function __construct(array $data)
    {
        $this->amountSpendByUsers   = isset($data['amount_spend_by_users']) ? $data['amount_spend_by_users'] : null;
        $this->balance              = $data['balance'];
        $this->code                 = $data['code'];
        $this->disabled             = $data['disabled'];
        $this->id                   = $data['id'];
        $this->lastWithdrawedAt     = $data['last_withdrawed_at'];
        $this->name                 = $data['name'];
        $this->ownerId              = $data['owner_id'];
        $this->participants         = count($data['participants']);//ToDo: возможно, нужно сохранять сущности участников
        $this->template             = new ReferralTemplateDTO($data['template']);
        $this->templateId           = $data['template_id'];
    }
}
<?php

namespace App\Service\DTO\Bothub;

class ModelDTO
{
    /** @var string */
    public $id;
    /** @var string|null */
    public $label;
    /** @var int */
    public $maxTokens;
    /** @var bool */
    public $disabled;
    /** @var bool */
    public $disabledTelegram;
    /** @var bool  */
    public $isDefault;
    /** @var string  */
    public $parentId;
    /** @var mixed  */
    public $isAllowed;
    /** @var string[] */
    public $features;

    /**
     * ModelDTO constructor.
     * @param array $data
     */
    public function __construct(array $data)
    {
        $this->id               = $data['id'];
        $this->label            = $data['label'];
        $this->maxTokens        = $data['max_tokens'];
        $this->disabled         = $data['disabled'];
        $this->disabledTelegram = $data['disabledTelegram'];
        $this->isDefault        = $data['is_default'];
        $this->parentId         = $data['parent_id'];
        $this->isAllowed        = $data['is_allowed'];
        $this->features         = $data['features'];
    }
}
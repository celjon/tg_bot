<?php

namespace App\Service\DTO\Bothub;

class ButtonDTO
{
    /** @var string */
    public $id;
    /** @var string */
    public $type;
    /** @var string */
    public $action;
    /** @var string */
    public $mjNativeLabel;

    public function __construct($data)
    {
        $this->id = $data['id'];
        $this->type = $data['type'];
        $this->action = $data['action'];
        $this->mjNativeLabel = $data['mj_native_label'];
    }
}
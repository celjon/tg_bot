<?php

namespace App\Service\DTO\Bothub;

class MessageAttachmentFileDTO
{
    /** @var string */
    public $id;
    /** @var string */
    public $type;
    /** @var string|null */
    public $name;
    /** @var string|null */
    public $url;
    /** @var string */
    public $path;

    /**
     * MessageAttachmentFileDTO constructor.
     * @param array $data
     */
    public function __construct(array $data)
    {
        $this->id = $data['id'];
        $this->type = $data['type'];
        $this->name = $data['name'];
        $this->url = $data['url'];
        $this->path = $data['path'];
    }
}
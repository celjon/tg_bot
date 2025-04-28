<?php

namespace App\Service\DTO\Bothub;

class MessageAttachmentDTO
{
    /** @var string */
    public $id;
    /** @var string */
    public $messageId;
    /** @var string */
    public $fileId;
    /** @var MessageAttachmentFileDTO */
    public $file;
    /** @var ButtonDTO[] */
    public $buttons;

    /**
     * MessageAttachmentDTO constructor.
     * @param array $data
     */
    public function __construct(array $data)
    {
        $this->id = $data['id'];
        $this->messageId = $data['message_id'];
        $this->fileId = $data['file_id'];
        $this->file = !empty($data['file']) ? new MessageAttachmentFileDTO($data['file']) : null;
        $this->buttons = !empty($data['buttons']) ? array_map(function ($button) {
                return new ButtonDTO($button);
            },
            $data['buttons']
        ) : null;
    }
}
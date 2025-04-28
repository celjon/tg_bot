<?php

namespace App\Service\DTO\Bothub;

use Exception;

class MessageDTO
{
    /** @var string|null */
    public $content = null;
    /** @var MessageAttachmentDTO */
    public $attachments = [];
    /** @var float */
    public $tokens = null;

    /**
     * MessageDTO constructor.
     * @param array $data
     * @throws Exception
     */
    public function __construct(array $data)
    {
        if (!empty($data['response'])) {
            $this->content = $data['response']['content'];
            if (!empty($data['response']['attachments'])) {
                foreach ($data['response']['attachments'] as $attachment) {
                    $this->attachments[] = new MessageAttachmentDTO($attachment);
                }
            }
        }
        if (!empty($data['tokens'])) {
            $this->tokens = $data['tokens'];
        }
    }
}
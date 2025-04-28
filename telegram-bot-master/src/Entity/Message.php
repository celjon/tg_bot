<?php

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Class Message
 * @package App\Entity
 * @ORM\Table(name="messages")
 * @ORM\Entity(repositoryClass="App\Repository\MessageRepository")
 */
class Message
{
    public const DIRECTION_REQUEST = 0;
    public const DIRECTION_RESPONSE = 1;

    public const TYPE_NO_ACTION = 0;
    public const TYPE_START = 1;
    public const TYPE_SEND_MESSAGE = 2;
    public const TYPE_GET_USER_INFO = 3;
    public const TYPE_CREATE_NEW_CHAT = 4;
    public const TYPE_LIST_PLANS = 5;
    public const TYPE_CANCEL_BUY_PLAN = 7;
    public const TYPE_CREATE_NEW_IMAGE_GENERATION_CHAT = 9;
    public const TYPE_CONNECT_TELEGRAM = 10;
    public const TYPE_VOICE_MESSAGE = 11;
    public const TYPE_SELECT_PAYMENT_METHOD = 12;
    public const TYPE_GPT_CONFIG = 13;
    public const TYPE_IMAGE_GENERATION_CONFIG = 14;
    public const TYPE_TOOLZ_CONFIG = 15;
    public const TYPE_TOOLZ = 16;
    public const TYPE_CONTEXT_CONFIG = 17;
    public const TYPE_DOCUMENT_MESSAGE = 18;
    public const TYPE_LINKS_PARSING_CONFIG = 19;
    public const TYPE_PRESENT = 20;
    public const TYPE_ADD_TO_CONTEXT = 21;
    public const TYPE_BUFFER_MESSAGE = 22;
    public const TYPE_SEND_BUFFER = 23;
    public const TYPE_CANCEL_ADD_TO_CONTEXT = 24;
    public const TYPE_SET_SYSTEM_PROMPT = 25;
    public const TYPE_SAVE_SYSTEM_PROMPT = 26;
    public const TYPE_CANCEL_SET_SYSTEM_PROMPT = 27;
    public const TYPE_RESET_SYSTEM_PROMPT = 28;
    public const TYPE_SELECT_CHAT = 29;
    public const TYPE_FORMULA_TO_IMAGE_CONFIG = 31;
    public const TYPE_ANSWER_TO_VOICE_CONFIG = 32;
    public const TYPE_REFERRAL = 33;
    public const TYPE_LIST_REFERRAL_TEMPLATES = 34;
    public const TYPE_CREATE_REFERRAL_PROGRAM = 35;
    public const TYPE_PRIVACY = 36;
    public const TYPE_VIDEO_MESSAGE = 37;
    public const TYPE_RESET_CONTEXT = 38;
    public const TYPE_WEB_SEARCH_CONFIG = 39;
    public const TYPE_CHANGE_WEB_SEARCH = 40;
    public const TYPE_CHAT_LIST = 41;
    public const TYPE_CREATE_NEW_CUSTOM_CHAT_WITHOUT_NAME = 42;
    public const TYPE_CANCEL_CREATE_NEW_CUSTOM_CHAT = 43;
    public const TYPE_SET_CHAT_NAME = 44;
    public const TYPE_MIDJOURNEY_BUTTONS = 45;

    public const STATUS_NOT_PROCESSED = 0;
    public const STATUS_PROCESSED = 1;

    /**
     * @var int
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;
    /**
     * @var User
     * @ORM\ManyToOne(targetEntity="User", inversedBy="messages")
     * @ORM\JoinColumn(name="user_id", referencedColumnName="id")
     */
    private $user;
    /**
     * @var int
     * @ORM\Column(type="smallint")
     */
    private $chatIndex;
    /**
     * @var int
     * @ORM\Column(type="integer", name="message_id")
     */
    private $messageId;
    /**
     * @var int
     * @ORM\Column(type="smallint")
     */
    private $direction;
    /**
     * @var int
     * @ORM\Column(type="smallint")
     */
    private $type;
    /**
     * @var int
     * @ORM\Column(type="smallint")
     */
    private $status;
    /**
     * @var int
     * @ORM\Column(type="bigint", name="chat_id")
     */
    private $chatId;
    /**
     * @var string
     * @ORM\Column(type="text")
     */
    private $text;
    /**
     * @var array
     * @ORM\Column(type="json", nullable=true)
     */
    private $data;
    /**
     * @var DateTimeImmutable
     * @ORM\Column(type="datetime_immutable", name="sent_at")
     */
    private $sentAt;
    /**
     * @var DateTimeImmutable
     * @ORM\Column(type="datetime_immutable", name="parsed_at")
     */
    private $parsedAt;
    /**
     * @var int
     * @ORM\Column(type="smallint", nullable=true)
     */
    private $worker;
    /**
     * @var Message
     * @ORM\ManyToOne(targetEntity="Message", inversedBy="relatedMessages")
     * @ORM\JoinColumn(name="related_message_id", referencedColumnName="id", nullable=true)
     */
    private $relatedMessage;
    /**
     * @var Message[]
     * @ORM\OneToMany(targetEntity="Message", mappedBy="relatedMessage")
     */
    private $relatedMessages;

    /**
     * Message constructor.
     */
    public function __construct()
    {
        $this->relatedMessages = new ArrayCollection();
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return User
     */
    public function getUser(): User
    {
        return $this->user;
    }

    /**
     * @return int
     */
    public function getUserId(): int
    {
        return $this->user->getId();
    }

    /**
     * @param User $user
     * @return Message
     */
    public function setUser(User $user): Message
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @return int
     */
    public function getChatIndex(): int
    {
        return $this->chatIndex;
    }

    /**
     * @param int $chatIndex
     * @return Message
     */
    public function setChatIndex(int $chatIndex): Message
    {
        $this->chatIndex = $chatIndex;
        return $this;
    }

    /**
     * @return int
     */
    public function getMessageId(): int
    {
        return $this->messageId;
    }

    /**
     * @param int $messageId
     * @return Message
     */
    public function setMessageId(int $messageId): Message
    {
        $this->messageId = $messageId;
        return $this;
    }

    /**
     * @return int
     */
    public function getDirection(): int
    {
        return $this->direction;
    }

    /**
     * @param int $direction
     * @return Message
     */
    public function setDirection(int $direction): Message
    {
        $this->direction = $direction;
        return $this;
    }

    /**
     * @return int
     */
    public function getType(): int
    {
        return $this->type;
    }

    /**
     * @param int $type
     * @return Message
     */
    public function setType(int $type): Message
    {
        $this->type = $type;
        return $this;
    }

    /**
     * @return int
     */
    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * @param int $status
     * @return Message
     */
    public function setStatus(int $status): Message
    {
        $this->status = $status;
        return $this;
    }

    /**
     * @return int
     */
    public function getChatId(): int
    {
        return $this->chatId;
    }

    /**
     * @param int $chatId
     * @return Message
     */
    public function setChatId(int $chatId): Message
    {
        $this->chatId = $chatId;
        return $this;
    }

    /**
     * @return string
     */
    public function getText(): string
    {
        return $this->text;
    }

    /**
     * @param string $text
     * @return Message
     */
    public function setText(string $text): Message
    {
        $this->text = $text;
        return $this;
    }

    /**
     * @return array|null
     */
    public function getData(): ?array
    {
        return $this->data;
    }

    /**
     * @param array|null $data
     * @return Message
     */
    public function setData(?array $data): Message
    {
        $this->data = $data;
        return $this;
    }

    /**
     * @return DateTimeImmutable
     */
    public function getSentAt(): DateTimeImmutable
    {
        return $this->sentAt;
    }

    /**
     * @param DateTimeImmutable $sentAt
     * @return Message
     */
    public function setSentAt(DateTimeImmutable $sentAt): Message
    {
        $this->sentAt = $sentAt;
        return $this;
    }

    /**
     * @return DateTimeImmutable
     */
    public function getParsedAt(): DateTimeImmutable
    {
        return $this->parsedAt;
    }

    /**
     * @param DateTimeImmutable $parsedAt
     * @return Message
     */
    public function setParsedAt(DateTimeImmutable $parsedAt): Message
    {
        $this->parsedAt = $parsedAt;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getWorker(): ?int
    {
        return $this->worker;
    }

    /**
     * @param int $worker
     * @return Message
     */
    public function setWorker(int $worker): Message
    {
        $this->worker = $worker;
        return $this;
    }

    /**
     * @return Message
     */
    public function getRelatedMessage(): Message
    {
        return $this->relatedMessage;
    }

    /**
     * @param Message|null $relatedMessage
     * @return Message
     */
    public function setRelatedMessage(?Message $relatedMessage): Message
    {
        $this->relatedMessage = $relatedMessage;
        return $this;
    }

    /**
     * @return Collection|Message[]
     */
    public function getRelatedMessages(): Collection
    {
        return $this->relatedMessages;
    }
}